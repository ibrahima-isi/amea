<?php
/**
 * Migration script to create 'settings' table.
 * File: migration_add_settings_table.php
 */

require_once 'config/database.php';

try {
    // Create settings table
    $sql = "CREATE TABLE IF NOT EXISTS settings (
        setting_key VARCHAR(50) PRIMARY KEY,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $conn->exec($sql);
    echo "Table 'settings' created or already exists.<br>";

    // Default values
    $defaults = [
        'contact_email' => 'admin@aeesgs.org',
        'contact_phone' => '+221 XX XXX XX XX',
        'organization_name' => 'AEESGS'
    ];

    $stmt = $conn->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (:key, :value)");

    foreach ($defaults as $key => $value) {
        $stmt->execute([':key' => $key, ':value' => $value]);
        if ($stmt->rowCount() > 0) {
            echo "Inserted default setting: $key<br>";
        } else {
            echo "Setting already exists: $key<br>";
        }
    }

    echo "Migration completed successfully.";

} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>