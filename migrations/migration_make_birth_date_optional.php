<?php
/**
 * Make date of birth optional for registration and profile updates.
 */

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

require_once __DIR__ . '/../config/database.php';

function personnesColumnExists(PDO $conn, string $column): bool
{
    $stmt = $conn->query("SHOW COLUMNS FROM personnes LIKE " . $conn->quote($column));
    return $stmt !== false && $stmt->rowCount() > 0;
}

try {
    echo "Starting migration: make date_naissance optional...\n";

    if (!personnesColumnExists($conn, 'date_naissance')) {
        echo "Column 'date_naissance' does not exist. Skipping.\n";
        exit(0);
    }

    $originalSqlMode = (string)$conn->query('SELECT @@SESSION.sql_mode')->fetchColumn();
    $migrationSqlMode = implode(',', array_filter(
        explode(',', $originalSqlMode),
        fn($mode) => !in_array($mode, ['STRICT_TRANS_TABLES', 'STRICT_ALL_TABLES', 'NO_ZERO_DATE', 'NO_ZERO_IN_DATE'], true)
    ));
    $conn->exec('SET SESSION sql_mode = ' . $conn->quote($migrationSqlMode));

    $conn->exec("ALTER TABLE personnes MODIFY COLUMN `date_naissance` DATE NULL DEFAULT NULL");
    $conn->exec("UPDATE personnes SET `date_naissance` = NULL WHERE `date_naissance` = '0000-00-00'");
    $conn->exec('SET SESSION sql_mode = ' . $conn->quote($originalSqlMode));

    echo "Column 'date_naissance' is now nullable.\n";
    echo "Migration completed successfully.\n";
} catch (PDOException $e) {
    if (isset($originalSqlMode)) {
        $conn->exec('SET SESSION sql_mode = ' . $conn->quote($originalSqlMode));
    }
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
