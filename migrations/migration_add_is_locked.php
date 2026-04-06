<?php
/**
 * Migration script to add 'is_locked' column to 'personnes' table.
 * Locks a student record after they finalize their registration.
 * Once locked, only admins can edit the record.
 */

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

require_once __DIR__ . '/../config/database.php';

try {
    $stmt = $conn->query("SHOW COLUMNS FROM personnes LIKE 'is_locked'");
    if ($stmt->rowCount() == 0) {
        $conn->exec("ALTER TABLE personnes ADD COLUMN is_locked TINYINT(1) NOT NULL DEFAULT 0 AFTER consent_privacy_date");
        echo "Column 'is_locked' added successfully.\n";
    } else {
        echo "Column 'is_locked' already exists. Skipping.\n";
    }

    echo "Migration completed successfully.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
