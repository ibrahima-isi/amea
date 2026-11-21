<?php

declare(strict_types=1);

use Amea\Migration\MigrationRuntime;

require __DIR__ . '/../vendor/autoload.php';

// Run only against a disposable MariaDB instance with two empty databases.
if (PHP_SAPI !== 'cli' || getenv('DB_NAME') !== 'amea_doctrine_fresh'
    || !(getenv('DB_HOST') === '127.0.0.1' || (getenv('DB_HOST') === 'db' && getenv('APP_ENV') === 'test'))) {
    throw new RuntimeException('Select the disposable amea_doctrine_fresh database on localhost or the APP_ENV=test Compose db service.');
}

function runMigrationCommand(string $command, string $database = 'amea_doctrine_fresh'): array
{
    $process = proc_open(
        [PHP_BINARY, __DIR__ . '/../scripts/migrate.php', $command],
        [1 => ['pipe', 'w'], 2 => ['redirect', 1]],
        $pipes,
        null,
        array_replace(getenv(), ['DB_NAME' => $database])
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start the migration command.');
    }
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    return [proc_close($process), $output];
}

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

function connectToTestDatabase(string $name): PDO
{
    return new PDO(
        'mysql:host=' . getenv('DB_HOST') . ';port=' . (getenv('DB_PORT') ?: '3306') . ";dbname={$name};charset=utf8mb4",
        (string) getenv('DB_USER'),
        (string) getenv('DB_PASS'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
}

function readTableDefinitions(PDO $database): array
{
    $tables = $database->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $definitions = [];
    foreach (array_diff($tables, [MigrationRuntime::LEDGER_TABLE, MigrationRuntime::RECOVERY_TABLE]) as $table) {
        $definition = $database->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM)[1];
        $definitions[$table] = preg_replace('/ AUTO_INCREMENT=\d+\b/', '', $definition);
    }
    ksort($definitions);

    return $definitions;
}

$fresh = connectToTestDatabase('amea_doctrine_fresh');
$baseline = connectToTestDatabase('amea_doctrine_baseline');
check(str_contains($fresh->getAttribute(PDO::ATTR_SERVER_VERSION), '10.11.'), 'Use the Compose MariaDB version');
check(
    $fresh->query('SHOW FULL TABLES')->fetchAll() === []
        && $baseline->query('SHOW FULL TABLES')->fetchAll() === [],
    'Start with two empty databases'
);

foreach ([
    ['PROCEDURE', 'CREATE PROCEDURE existing_object() SELECT 1', 'ROUTINES', 'ROUTINE_SCHEMA'],
    ['FUNCTION', 'CREATE FUNCTION existing_object() RETURNS INT DETERMINISTIC RETURN 1', 'ROUTINES', 'ROUTINE_SCHEMA'],
    ['EVENT', 'CREATE EVENT existing_object ON SCHEDULE EVERY 1 DAY DISABLE DO SELECT 1', 'EVENTS', 'EVENT_SCHEMA'],
] as [$kind, $create, $catalog, $schemaColumn]) {
    $fresh->exec($create);
    [$exit, $output] = runMigrationCommand('migrate');
    check($exit !== 0 && str_contains($output, 'without a recorded baseline'), "Refuse a database containing only a {$kind} without enrollment\n{$output}");
    check($fresh->query('SHOW FULL TABLES')->fetchAll() === [], "{$kind} refusal creates no schema or ledger tables");
    check(
        (int) $fresh->query("SELECT COUNT(*) FROM information_schema.{$catalog} WHERE {$schemaColumn} = DATABASE() AND "
            . ($catalog === 'EVENTS' ? 'EVENT_NAME' : 'ROUTINE_NAME') . " = 'existing_object'")->fetchColumn() === 1,
        "{$kind} refusal preserves the existing object"
    );
    $fresh->exec("DROP {$kind} existing_object");
}

[$exit, $output] = runMigrationCommand('migrate');
check($exit === 0 && str_contains($output, '4 migrations executed'), "Fresh migration applies all four versions\n{$output}");
check(
    $fresh->query('SELECT setting_key, setting_value, setting_group FROM settings ORDER BY setting_key')
        ->fetchAll(PDO::FETCH_NUM) === [
        ['contact_email', 'admin@aeesgs.org', 'general'],
        ['contact_phone', '+221 XX XXX XX XX', 'general'],
        ['organization_name', 'AEESGS', 'general'],
    ],
    'Fresh initialization installs the three legacy settings'
);

check($fresh->query('SELECT id FROM member_file_lock')->fetchAll(PDO::FETCH_COLUMN) === [1], 'Transactional member lock initialization runs once');

$import = $baseline->query(file_get_contents(__DIR__ . '/../schema.sql'));
while ($import->nextRowset()) {
    // Consume every result to surface errors in the schema import.
}
check(readTableDefinitions($fresh) === readTableDefinitions($baseline), 'Fresh schema matches schema.sql');

$ledgerQuery = 'SELECT version, executed_at, execution_time FROM doctrine_migration_versions ORDER BY version';
$ledger = $fresh->query($ledgerQuery)->fetchAll(PDO::FETCH_ASSOC);
$versions = [...MigrationRuntime::BASELINE_VERSIONS, ...MigrationRuntime::MEMBER_FILE_VERSIONS];
check(array_column($ledger, 'version') === $versions, 'Record the schema and data versions separately');
check(
    $fresh->query("SHOW TABLE STATUS WHERE Name = 'doctrine_migration_versions'")->fetch(PDO::FETCH_ASSOC)['Engine'] === 'InnoDB',
    'The version ledger uses a transactional storage engine'
);

[$exit, $output] = runMigrationCommand('status');
check(
    $exit === 0 && str_contains($output, $versions[count($versions) - 2]) && str_contains($output, $versions[count($versions) - 1]),
    "Doctrine reports recorded schema and data versions\n{$output}"
);

$fresh->exec("UPDATE settings SET setting_value = 'custom@aeesgs.invalid' WHERE setting_key = 'contact_email'");
[$exit, $output] = runMigrationCommand('migrate');
check($exit === 0 && str_contains($output, 'Already at the latest version'), "Second migration applies no versions\n{$output}");
check($fresh->query($ledgerQuery)->fetchAll(PDO::FETCH_ASSOC) === $ledger, 'Second migration leaves the ledger unchanged');
check(
    $fresh->query("SELECT setting_value FROM settings WHERE setting_key = 'contact_email'")->fetchColumn() === 'custom@aeesgs.invalid',
    'Second migration preserves an edited setting'
);

[$exit, $output] = runMigrationCommand('migrate', 'amea_doctrine_baseline');
check($exit !== 0 && str_contains($output, 'without a recorded baseline'), 'Refuse an existing SQL import without enrollment');
check(
    $baseline->query('SHOW FULL TABLES')->rowCount() === count(readTableDefinitions($fresh)),
    'Refusal leaves the imported database without a ledger'
);
