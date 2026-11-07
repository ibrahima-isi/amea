<?php

declare(strict_types=1);

use Amea\Migration\MigrationRuntime;

require __DIR__ . '/../vendor/autoload.php';

// Run only against a disposable MariaDB instance with two empty databases.
if (PHP_SAPI !== 'cli' || getenv('DB_HOST') !== '127.0.0.1' || getenv('DB_NAME') !== 'amea_doctrine_fresh') {
    throw new RuntimeException('Select the disposable localhost database amea_doctrine_fresh.');
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
        'mysql:host=127.0.0.1;port=' . (getenv('DB_PORT') ?: '3306') . ";dbname={$name};charset=utf8mb4",
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

[$exit, $output] = runMigrationCommand('migrate');
check($exit === 0 && str_contains($output, '2 migrations executed'), "Fresh migration applies two versions\n{$output}");
check(
    $fresh->query('SELECT setting_key, setting_value, setting_group FROM settings ORDER BY setting_key')
        ->fetchAll(PDO::FETCH_NUM) === [
        ['contact_email', 'admin@aeesgs.org', 'general'],
        ['contact_phone', '+221 XX XXX XX XX', 'general'],
        ['organization_name', 'AEESGS', 'general'],
    ],
    'Fresh initialization installs the three legacy settings'
);

$import = $baseline->query(file_get_contents(__DIR__ . '/../schema.sql'));
while ($import->nextRowset()) {
    // Consume every result to surface errors in the schema import.
}
check(readTableDefinitions($fresh) === readTableDefinitions($baseline), 'Fresh schema matches schema.sql');

$ledgerQuery = 'SELECT version, executed_at, execution_time FROM doctrine_migration_versions ORDER BY version';
$ledger = $fresh->query($ledgerQuery)->fetchAll(PDO::FETCH_ASSOC);
$versions = ['Amea\\Migration\\Version\\Version20261106000000', 'Amea\\Migration\\Version\\Version20261106000001'];
check(array_column($ledger, 'version') === $versions, 'Record the schema and data versions separately');
check(
    $fresh->query("SHOW TABLE STATUS WHERE Name = 'doctrine_migration_versions'")->fetch(PDO::FETCH_ASSOC)['Engine'] === 'InnoDB',
    'The version ledger uses a transactional storage engine'
);

[$exit, $output] = runMigrationCommand('status');
check(
    $exit === 0 && str_contains($output, $versions[0]) && str_contains($output, $versions[1]),
    "Doctrine reports both recorded versions\n{$output}"
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
