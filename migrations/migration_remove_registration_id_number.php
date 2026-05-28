<?php
/**
 * Remove the typed ID/passport number from registrations.
 *
 * The uploaded ID document remains stored in personnes.identite.
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
    echo "Starting migration: remove typed ID number from registrations...\n";

    if (!personnesColumnExists($conn, 'numero_identite')) {
        echo "Column 'numero_identite' does not exist. Skipping.\n";
        exit(0);
    }

    $conn->exec("ALTER TABLE personnes DROP COLUMN `numero_identite`");
    echo "Dropped 'numero_identite' column. Uploaded ID documents remain in 'identite'.\n";
    echo "Migration completed successfully.\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
