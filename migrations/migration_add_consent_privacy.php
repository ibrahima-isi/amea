<?php
/**
 * Migration script to add 'consent_privacy' and 'consent_privacy_date' columns to 'personnes' table.
 */

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

require_once __DIR__ . '/../config/database.php';

try {
    // Check if the column already exists to prevent errors on re-running
    $stmt = $conn->query("SHOW COLUMNS FROM personnes LIKE 'consent_privacy'");
    if ($stmt->rowCount() == 0) {
        $conn->exec("ALTER TABLE personnes ADD COLUMN consent_privacy TINYINT(1) NOT NULL DEFAULT 0 AFTER cv_path");
        echo "Column 'consent_privacy' added successfully.\n";
    } else {
        echo "Column 'consent_privacy' already exists. Skipping.\n";
    }

    $stmt = $conn->query("SHOW COLUMNS FROM personnes LIKE 'consent_privacy_date'");
    if ($stmt->rowCount() == 0) {
        $conn->exec("ALTER TABLE personnes ADD COLUMN consent_privacy_date DATETIME NULL AFTER consent_privacy");
        echo "Column 'consent_privacy_date' added successfully.\n";
    } else {
        echo "Column 'consent_privacy_date' already exists. Skipping.\n";
    }

    echo "Migration completed successfully.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

