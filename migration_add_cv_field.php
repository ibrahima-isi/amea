<?php
/**
 * Migration script to add 'cv_path' column to 'personnes' table.
 * File: migration_add_cv_field.php
 */

require_once 'config/database.php';

echo "Adding 'cv_path' column to 'personnes' table...\n";

try {
    // Check if the column already exists to prevent errors on re-running
    $stmt = $conn->query("SHOW COLUMNS FROM personnes LIKE 'cv_path'");
    if ($stmt->rowCount() == 0) {
        $conn->exec("ALTER TABLE personnes ADD COLUMN cv_path VARCHAR(255) NULL AFTER identite");
        echo "Column 'cv_path' added successfully.\n";
    } else {
        echo "Column 'cv_path' already exists. Skipping.\n";
    }

    echo "Migration completed successfully.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

