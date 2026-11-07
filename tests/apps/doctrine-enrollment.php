<?php

declare(strict_types=1);

use Amea\Migration\MigrationRuntime;

if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'test' || getenv('DB_NAME') !== 'amea_doctrine_enrollment') {
    exit(2);
}

require __DIR__ . '/../../vendor/autoload.php';

function checkCommand(array $arguments, int $expectedExit, string $expectedOutput): void
{
    $process = proc_open([PHP_BINARY, ...$arguments], [1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes, dirname(__DIR__, 2));
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start enrollment test command.');
    }
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $status = proc_close($process);
    if ($status !== $expectedExit || !str_contains($output, $expectedOutput)) {
        throw new RuntimeException("Expected exit {$expectedExit} and '{$expectedOutput}', got {$status}: {$output}");
    }
}

$connection = MigrationRuntime::fromEnvironment()->connection();
$connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
foreach ($connection->fetchFirstColumn('SHOW FULL TABLES') as $table) {
    $connection->executeStatement('DROP TABLE ' . $connection->getDatabasePlatform()->quoteSingleIdentifier($table));
}
$connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
checkCommand(
    ['scripts/enroll-migrations.php', '--database=another_database', '--writers-stopped', '--evidence=/unused.json'],
    1,
    'does not match DB_NAME'
);
echo "PASS: enrollment refuses a database other than the explicit target.\n";

$evidence = tempnam(sys_get_temp_dir(), 'amea-enrollment-');
if ($evidence === false) {
    throw new RuntimeException('Cannot create fixture evidence file.');
}
file_put_contents($evidence, json_encode([
    'database' => getenv('DB_NAME'),
    'host' => getenv('DB_HOST'),
    'port' => (int) (getenv('DB_PORT') ?: 3306),
    'source' => 'schema-sql',
    'reference' => 'Test fixture imported schema.sql into a disposable empty database',
    'imported_into_empty_database' => true,
    'schema_sha256' => hash_file('sha256', __DIR__ . '/../../schema.sql'),
], JSON_THROW_ON_ERROR));
$enroll = ['scripts/enroll-migrations.php', '--database=amea_doctrine_enrollment', '--writers-stopped', '--evidence=' . $evidence];

try {
    $connection->getNativeConnection()->exec(file_get_contents(__DIR__ . '/../../schema.sql'));
    checkCommand($enroll, 1, 'Missing initial settings');
    if (in_array('doctrine_migration_versions', $connection->fetchFirstColumn('SHOW FULL TABLES'), true)) {
        throw new RuntimeException('A rejected SQL import unexpectedly provisioned a ledger.');
    }
    echo "PASS: enrollment refuses a schema.sql import missing the initial settings.\n";

    $connection->executeStatement(
        "INSERT INTO settings (setting_key, setting_value) VALUES
            ('contact_email', 'custom@example.invalid'), ('contact_phone', '+221 XX XXX XX XX'), ('organization_name', 'AMEA Test')"
    );
    $connection->executeStatement(
        "INSERT INTO students (last_name, first_name, gender, status, is_locked, identity_document, cv_path) VALUES
            ('Synthetic', 'Locked', 'Masculin', 'ETUDIANT', 1, 'test-id.pdf', 'test-cv.pdf')"
    );
    $connection->executeStatement(
        "INSERT INTO journal_posts (type, slug, title, body, cover_path, created_by, updated_by, created_at, updated_at) VALUES
            ('ANNOUNCEMENT', 'test-draft', 'Draft', 'Body', 'test-cover.jpg', 1, 1, '2026-11-06', '2026-11-06')"
    );
    $proofQueries = ['SELECT * FROM settings ORDER BY id', 'SELECT * FROM students ORDER BY id', 'SELECT * FROM journal_posts ORDER BY id'];
    $snapshot = static fn (): array => array_map($connection->fetchAllAssociative(...), $proofQueries);
    $before = $snapshot();
    checkCommand(['scripts/migrate.php', 'migrate'], 1, 'without a recorded baseline');
    checkCommand($enroll, 0, 'Recorded 2 baseline versions');
    $ledgerSql = 'SELECT * FROM doctrine_migration_versions ORDER BY version';
    $ledger = $connection->fetchAllAssociative($ledgerSql);
    if (array_column($ledger, 'version') !== [
        'Amea\\Migration\\Version\\Version20261106000000',
        'Amea\\Migration\\Version\\Version20261106000001',
    ] || $snapshot() !== $before) {
        throw new RuntimeException('Enrollment must record both baseline versions and preserve all existing data.');
    }
    checkCommand($enroll, 0, 'Baseline already enrolled');
    checkCommand(['scripts/migrate.php', 'migrate'], 0, 'Already at the latest version');
    if ($connection->fetchAllAssociative($ledgerSql) !== $ledger || $snapshot() !== $before) {
        throw new RuntimeException('Enrollment and migration retries must preserve the existing ledger and application data.');
    }
    echo "PASS: an existing SQL import can be enrolled and retried without replaying migrations.\n";

    $connection->executeStatement('DELETE FROM doctrine_migration_versions');
    $connection->executeStatement("ALTER TABLE settings DROP PRIMARY KEY, DROP COLUMN id, DROP COLUMN setting_group, DROP INDEX setting_key, ADD PRIMARY KEY (setting_key), CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $connection->executeStatement("ALTER TABLE students MODIFY housing_type ENUM('Other','With family','Shared housing','University residence','Autre','Colocation','Famille','Hébergement temporaire','Location','Résidence universitaire') COLLATE utf8mb4_unicode_ci NULL DEFAULT 'With family' COMMENT 'legacy fixture'");
    $connection->executeStatement("ALTER TABLE settings MODIFY updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    $connection->executeStatement("ALTER TABLE students MODIFY gender ENUM('Féminin','Masculin','Female','Male') NOT NULL, MODIFY status ENUM('Diplômé(e)','Diplômé','GRADUATE','STAGIAIRE','ETUDIANT','ELEVE','TRAINEE','STUDENT','PUPIL') NOT NULL");
    $legacyBefore = $connection->fetchAllAssociative('SELECT * FROM settings ORDER BY setting_key');
    $memberBefore = $connection->fetchAllAssociative('SELECT * FROM students ORDER BY id');
    checkCommand($enroll, 0, 'Recorded 2 baseline versions');
    if ($connection->fetchAllAssociative('SELECT * FROM settings ORDER BY setting_key') !== $legacyBefore
        || $connection->fetchAllAssociative('SELECT * FROM students ORDER BY id') !== $memberBefore) {
        throw new RuntimeException('Supported legacy variants must preserve every existing row.');
    }
    echo "PASS: enrollment supports historical settings and compatible enum metadata without changing data.\n";

    $connection->executeStatement('DELETE FROM doctrine_migration_versions');
    $connection->executeStatement('CREATE TRIGGER enrollment_unknown BEFORE INSERT ON doctrine_migration_versions FOR EACH ROW SET @enrollment_unknown = 1');
    checkCommand($enroll, 1, 'Unexpected database triggers');
    if ($connection->fetchOne('SELECT COUNT(*) FROM doctrine_migration_versions') !== 0) {
        throw new RuntimeException('Unknown database triggers must be rejected before recording versions.');
    }
    $connection->executeStatement('DROP TRIGGER enrollment_unknown');
    echo "PASS: enrollment refuses unknown database triggers before ledger writes.\n";

    $connection->executeStatement('CREATE PROCEDURE enrollment_unknown() SELECT 1');
    checkCommand($enroll, 1, 'Unexpected database routines');
    $connection->executeStatement('DROP PROCEDURE enrollment_unknown');
    $connection->executeStatement('CREATE EVENT enrollment_unknown ON SCHEDULE EVERY 1 DAY DISABLE DO SET @enrollment_unknown = 1');
    checkCommand($enroll, 1, 'Unexpected database events');
    $connection->executeStatement('DROP EVENT enrollment_unknown');
    $connection->executeStatement('CREATE TABLE enrollment_unknown (id INT)');
    checkCommand($enroll, 1, 'Extra: [enrollment_unknown]');
    $connection->executeStatement('DROP TABLE enrollment_unknown');
    $connection->executeStatement('ALTER TABLE students ADD COLUMN enrollment_unknown INT');
    checkCommand($enroll, 1, 'Unsupported schema for students');
    $connection->executeStatement('ALTER TABLE students DROP COLUMN enrollment_unknown');
    echo "PASS: enrollment refuses unknown routines, events, tables and schema drift.\n";

    $connection->insert('doctrine_migration_versions', ['version' => MigrationRuntime::BASELINE_VERSIONS[0]]);
    checkCommand($enroll, 1, 'Partial migration baseline');
    $connection->executeStatement('DELETE FROM doctrine_migration_versions');
    $connection->insert('doctrine_migration_versions', ['version' => 'UnknownVersion']);
    checkCommand($enroll, 1, 'Unknown recorded versions');
    $connection->executeStatement('DELETE FROM doctrine_migration_versions');
    $connection->executeStatement('ALTER TABLE doctrine_migration_versions ENGINE=MyISAM');
    checkCommand($enroll, 1, 'requires an InnoDB');
    $connection->executeStatement('ALTER TABLE doctrine_migration_versions ENGINE=InnoDB');
    echo "PASS: enrollment refuses partial, unknown and nontransactional ledgers.\n";

    // A real server constraint fails the second insert after the first one has succeeded.
    // No trigger or production-only fault injection switch is needed.
    $connection->executeStatement("ALTER TABLE doctrine_migration_versions ADD CONSTRAINT enrollment_failure CHECK (RIGHT(version, 2) <> '01')");
    checkCommand($enroll, 1, 'CONSTRAINT');
    if ($connection->fetchOne('SELECT COUNT(*) FROM doctrine_migration_versions') !== 0
        || $connection->fetchAllAssociative('SELECT * FROM settings ORDER BY setting_key') !== $legacyBefore
        || $connection->fetchAllAssociative('SELECT * FROM students ORDER BY id') !== $memberBefore) {
        throw new RuntimeException('A failure after the first baseline insert must roll back every version and preserve application rows.');
    }
    $connection->executeStatement('ALTER TABLE doctrine_migration_versions DROP CONSTRAINT enrollment_failure');
    checkCommand($enroll, 0, 'Recorded 2 baseline versions');
    echo "PASS: a second-insert failure rolls back both baseline versions and a retry succeeds.\n";

    checkCommand(['scripts/enroll-migrations.php', '--database=amea_doctrine_enrollment', '--evidence=' . $evidence], 2, '--writers-stopped');
    checkCommand(['scripts/enroll-migrations.php', '--database=amea_doctrine_enrollment', '--writers-stopped', '--evidence=/missing-enrollment-evidence.json'], 1, 'readable local enrollment evidence');
    echo "PASS: enrollment requires an explicit writer-stop acknowledgement and reviewed evidence file.\n";



} finally {
    unlink($evidence);
}
