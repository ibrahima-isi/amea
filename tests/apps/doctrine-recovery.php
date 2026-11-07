<?php

declare(strict_types=1);

use Amea\Migration\MigrationRuntime;

require __DIR__ . '/../../vendor/autoload.php';

if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'test' || getenv('DB_NAME') !== 'amea_doctrine_recovery') {
    throw new RuntimeException('Select the disposable amea_doctrine_recovery database and set APP_ENV=test.');
}

function check(bool $condition, string $message, string $details = ''): void
{
    if (!$condition) {
        throw new RuntimeException($message . "\n" . $details);
    }
    echo "PASS: {$message}\n";
}

function startMigration(string $command, bool $backupConfirmed = false): array
{
    $environment = getenv();
    $environment['AMEA_SCHEMA_BACKUP_CONFIRMED'] = $backupConfirmed ? '1' : '0';
    $process = proc_open([PHP_BINARY, $command, 'migrate'], [
        0 => ['file', '/dev/null', 'r'],
        1 => ['pipe', 'w'],
        2 => ['redirect', 1],
    ], $pipes, null, $environment);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start migration command.');
    }

    return [$process, $pipes[1]];
}

function finishMigration(array $run): array
{
    [$process, $output] = $run;
    $text = stream_get_contents($output);
    fclose($output);

    return [proc_close($process), $text];
}

$command = __DIR__ . '/../../scripts/migrate.php';
$runtime = MigrationRuntime::fromEnvironment();
$db = $runtime->connection();
if ($db->fetchFirstColumn('SHOW FULL TABLES') !== []) {
    throw new RuntimeException('The disposable fixture database must be empty before the rehearsal.');
}

// Pause the first process after schema SQL, with the first ledger row still uncommitted.
$runtime->doctrine->getMetadataStorage()->ensureInitialized();
$db->executeStatement(<<<'SQL'
    CREATE TRIGGER pause_schema_version BEFORE INSERT ON doctrine_migration_versions
    FOR EACH ROW
    BEGIN
        IF RIGHT(NEW.version, 14) = '20261106000000' THEN DO SLEEP(3); END IF;
    END
    SQL);

$firstRun = startMigration($command);
$deadline = microtime(true) + 10;
do {
    $paused = $db->fetchOne("SELECT COUNT(*) FROM information_schema.PROCESSLIST
                             WHERE DB = DATABASE() AND STATE = 'User sleep'") > 0;
    if (!$paused) {
        usleep(20000);
    }
} while (!$paused && microtime(true) < $deadline);
check($paused, 'First runner reached the pending ledger write');
$secondRun = startMigration($command);
[$firstExit, $firstOutput] = finishMigration($firstRun);
[$secondExit, $secondOutput] = finishMigration($secondRun);
$db->executeStatement('DROP TRIGGER pause_schema_version');
check($firstExit === 0 && $secondExit === 0, 'Concurrent commands succeed', $firstOutput . $secondOutput);
check($db->fetchFirstColumn('SELECT version FROM doctrine_migration_versions ORDER BY version') === MigrationRuntime::BASELINE_VERSIONS, 'Concurrent runners record each baseline version once');
check((int) $db->fetchOne('SELECT COUNT(*) FROM settings') === 3, 'Concurrent runners seed initial settings once');

// Load test versions through the real CLI from a disposable copy of the migration sources.
$project = realpath(__DIR__ . '/../..');
$fixture = sys_get_temp_dir() . '/amea-migration-test-' . bin2hex(random_bytes(8));
foreach (['scripts', 'src/Migration/Version', 'vendor'] as $directory) {
    mkdir("{$fixture}/{$directory}", 0700, true);
}
register_shutdown_function(static function () use ($fixture): void {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($fixture, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($fixture);
});
foreach (['scripts/migrate.php', ...array_map(
    static fn (string $source): string => substr($source, strlen($project) + 1),
    [...glob("{$project}/src/Migration/*.php"), ...glob("{$project}/src/Migration/Version/Version2026110600000[01].php")]
)] as $source) {
    copy("{$project}/{$source}", "{$fixture}/{$source}");
}
$autoload = <<<'AUTOLOAD'
    <?php
    $loader = require PROJECT_AUTOLOAD;
    foreach (glob(__DIR__ . '/../src/Migration/*.php') as $source) {
        $loader->addClassMap(['Amea\\Migration\\' . basename($source, '.php') => $source]);
    }
    AUTOLOAD;
file_put_contents(
    "{$fixture}/vendor/autoload.php",
    str_replace('PROJECT_AUTOLOAD', var_export("{$project}/vendor/autoload.php", true), $autoload)
);

$fixtureCommand = "{$fixture}/scripts/migrate.php";
copy(__DIR__ . '/fixtures/doctrine/Version20990101000000.php', "{$fixture}/src/Migration/Version/Version20990101000000.php");
$db->executeStatement(<<<'SQL'
    CREATE TRIGGER fail_data_version BEFORE INSERT ON doctrine_migration_versions
    FOR EACH ROW
    BEGIN
        IF RIGHT(NEW.version, 14) = '20990101000000' THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'injected DML ledger failure';
        END IF;
    END
    SQL);
[$dataExit, $dataOutput] = finishMigration(startMigration($fixtureCommand));
check($dataExit !== 0 && str_contains($dataOutput, 'injected DML ledger failure'), 'DML reached the failing ledger write', $dataOutput);
check($db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'organization_name'") === 'AEESGS', 'Ledger failure rolled back the data write');
check($db->fetchFirstColumn('SELECT version FROM doctrine_migration_versions ORDER BY version') === MigrationRuntime::BASELINE_VERSIONS, 'Ledger failure left the pending version unrecorded');
$db->executeStatement('DROP TRIGGER fail_data_version');
[$dataExit, $dataOutput] = finishMigration(startMigration($fixtureCommand));
check($dataExit === 0, 'Data-only migration can retry', $dataOutput);
check($db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'organization_name'") === 'Recovery fixture'
    && (int) $db->fetchOne("SELECT COUNT(*) FROM doctrine_migration_versions WHERE RIGHT(version, 14) = '20990101000000'") === 1,
    'Retry commits the data write and the version together');

copy(__DIR__ . '/fixtures/doctrine/Version20990101000001.php', "{$fixture}/src/Migration/Version/Version20990101000001.php");
$versionsBeforeDdl = $db->fetchFirstColumn('SELECT version FROM doctrine_migration_versions ORDER BY version');
$tablesBeforeDdl = $db->fetchFirstColumn('SHOW FULL TABLES');
$verifiedColumnSql = <<<'SQL'
    SELECT column_type, is_nullable FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings' AND COLUMN_NAME = 'recovery_note'
    SQL;
[$ddlExit, $ddlOutput] = finishMigration(startMigration($fixtureCommand));
check($ddlExit !== 0 && str_contains($ddlOutput, 'AMEA_SCHEMA_BACKUP_CONFIRMED=1'), 'Existing schema changes require backup confirmation', $ddlOutput);
check($db->fetchAssociative($verifiedColumnSql) === false
    && $db->fetchFirstColumn('SELECT version FROM doctrine_migration_versions ORDER BY version') === $versionsBeforeDdl
    && $db->fetchFirstColumn('SHOW FULL TABLES') === $tablesBeforeDdl,
    'Failed backup precondition leaves schema and metadata untouched');

$db->executeStatement(<<<'SQL'
    CREATE TRIGGER fail_schema_version BEFORE INSERT ON doctrine_migration_versions
    FOR EACH ROW
    BEGIN
        IF RIGHT(NEW.version, 14) = '20990101000001' THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'injected DDL ledger failure';
        END IF;
    END
    SQL);
[$ddlExit, $ddlOutput] = finishMigration(startMigration($fixtureCommand, true));
check($ddlExit !== 0 && str_contains($ddlOutput, 'injected DDL ledger failure'), 'DDL reached the failing ledger write', $ddlOutput);
$db->executeStatement('DROP TRIGGER fail_schema_version');
check($db->fetchAssociative($verifiedColumnSql) === ['column_type' => 'varchar(30)', 'is_nullable' => 'YES']
    && $db->fetchFirstColumn('SELECT version FROM doctrine_migration_versions ORDER BY version') === $versionsBeforeDdl,
    'Schema changed while its version remained unrecorded');
[$retryExit, $retryOutput] = finishMigration(startMigration($fixtureCommand, true));
check($retryExit !== 0 && str_contains($retryOutput, 'Reconcile the schema and ledger'), 'Interrupted DDL blocks a normal retry', $retryOutput);

// Operator recovery: compare the entire changed schema and ledger before recording success.
$runtime->withLock(static function () use ($db, $verifiedColumnSql, $versionsBeforeDdl): void {
    $version = 'Amea\\Migration\\Version\\Version20990101000001';
    check($db->fetchFirstColumn('SELECT version FROM ' . MigrationRuntime::RECOVERY_TABLE) === [$version]
        && $db->fetchAssociative($verifiedColumnSql) === ['column_type' => 'varchar(30)', 'is_nullable' => 'YES']
        && $db->fetchFirstColumn('SELECT version FROM doctrine_migration_versions ORDER BY version') === $versionsBeforeDdl,
        'Recovery verifies the interrupted version, actual schema and unchanged ledger');
    $db->transactional(static function () use ($db, $version): void {
        $db->insert(MigrationRuntime::LEDGER_TABLE, ['version' => $version, 'executed_at' => date('Y-m-d H:i:s'), 'execution_time' => null]);
        $db->delete(MigrationRuntime::RECOVERY_TABLE, ['version' => $version]);
    });
});
[$retryExit, $retryOutput] = finishMigration(startMigration($fixtureCommand));
check($retryExit === 0 && str_contains($retryOutput, 'Already at the latest version'), 'Verified reconciliation permits a zero-change retry', $retryOutput);
check((int) $db->fetchOne('SELECT COUNT(*) FROM ' . MigrationRuntime::RECOVERY_TABLE) === 0
    && $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'organization_name'") === 'Recovery fixture',
    'Reconciliation clears the marker and preserves business data');

// Rehearse interruption on the other side: ledger committed, marker cleanup failed.
copy(__DIR__ . '/fixtures/doctrine/Version20990101000002.php', "{$fixture}/src/Migration/Version/Version20990101000002.php");
$db->executeStatement(<<<'SQL'
    CREATE TRIGGER fail_marker_creation BEFORE INSERT ON amea_migration_recovery
    FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'injected marker creation failure'
    SQL);
[$markerExit, $markerOutput] = finishMigration(startMigration($fixtureCommand, true));
check($markerExit !== 0 && str_contains($markerOutput, 'injected marker creation failure')
    && (int) $db->fetchOne("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'settings' AND COLUMN_NAME = 'recovery_extra'") === 0
    && (int) $db->fetchOne("SELECT COUNT(*) FROM doctrine_migration_versions WHERE RIGHT(version, 14) = '20990101000002'") === 0,
    'Failure to persist the marker prevents schema and ledger writes', $markerOutput);
$db->executeStatement('DROP TRIGGER fail_marker_creation');
$db->executeStatement(<<<'SQL'
    CREATE TRIGGER fail_marker_cleanup BEFORE DELETE ON amea_migration_recovery
    FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'injected marker cleanup failure'
    SQL);
[$cleanupExit, $cleanupOutput] = finishMigration(startMigration($fixtureCommand, true));
check($cleanupExit !== 0 && str_contains($cleanupOutput, 'injected marker cleanup failure'), 'Failed cleanup reports a post-ledger interruption', $cleanupOutput);
$db->executeStatement('DROP TRIGGER fail_marker_cleanup');
[$retryExit, $retryOutput] = finishMigration(startMigration($fixtureCommand, true));
check($retryExit !== 0 && str_contains($retryOutput, 'Reconcile the schema and ledger'), 'Committed ledger does not bypass an interrupted marker', $retryOutput);
$runtime->withLock(static function () use ($db): void {
    $version = 'Amea\\Migration\\Version\\Version20990101000002';
    check($db->fetchFirstColumn('SELECT version FROM ' . MigrationRuntime::RECOVERY_TABLE) === [$version]
        && (int) $db->fetchOne("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'settings' AND COLUMN_NAME = 'recovery_extra' AND COLUMN_TYPE = 'int(11)' AND IS_NULLABLE = 'YES'") === 1
        && (int) $db->fetchOne('SELECT COUNT(*) FROM doctrine_migration_versions WHERE version = ?', [$version]) === 1,
        'Recovery verifies schema and the already committed version before clearing its marker');
    $db->delete(MigrationRuntime::RECOVERY_TABLE, ['version' => $version]);
});
[$retryExit, $retryOutput] = finishMigration(startMigration($fixtureCommand));
check($retryExit === 0 && str_contains($retryOutput, 'Already at the latest version'), 'Post-ledger recovery permits a zero-change retry', $retryOutput);
