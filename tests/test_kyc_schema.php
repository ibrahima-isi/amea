<?php
/**
 * Regression checks for the KYC workflow database columns.
 *
 * Run: php tests/test_kyc_schema.php
 */

declare(strict_types=1);

$passed = 0;
$failed = 0;

function expect(string $label, bool $result): void
{
    global $passed, $failed;

    if ($result) {
        echo "\033[32m  ✓ {$label}\033[0m\n";
        $passed++;
        return;
    }

    echo "\033[31m  ✗ {$label}\033[0m\n";
    $failed++;
}

function fileContainsColumn(string $path, string $column): bool
{
    $sql = file_get_contents($path);
    return $sql !== false && preg_match('/`' . preg_quote($column, '/') . '`|\b' . preg_quote($column, '/') . '\b/i', $sql) === 1;
}

echo "\nKYC schema alignment\n";

$root = dirname(__DIR__);
$requiredColumns = ['kyc_status', 'kyc_notes', 'review_token', 'kyc_updated_at'];

foreach (['schema.sql', 'database/init.sql'] as $relativePath) {
    $path = $root . '/' . $relativePath;
    foreach ($requiredColumns as $column) {
        expect("{$relativePath} defines {$column}", fileContainsColumn($path, $column));
    }
}

$migration = file_get_contents($root . '/migrations/migration_add_kyc_workflow.php');
foreach ($requiredColumns as $column) {
    expect(
        "migration checks whether {$column} already exists",
        $migration !== false
            && str_contains($migration, 'function personnesColumnExists')
            && preg_match(
                "/addPersonnesColumnIfMissing\\(\\s*\\\$conn,\\s*'{$column}'/s",
                $migration
            ) === 1
    );
}

echo "\n";
$total = $passed + $failed;
if ($failed === 0) {
    echo "\033[32m  {$passed}/{$total} tests passed\033[0m\n\n";
    exit(0);
}

echo "\033[31m  {$passed}/{$total} tests passed\033[0m\n\n";
exit(1);
