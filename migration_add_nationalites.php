<?php
global $conn;
require_once 'config/database.php';

try {
    // Check if column exists
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = :db_name 
        AND TABLE_NAME = 'personnes' 
        AND COLUMN_NAME = 'nationalites'
    ");
    $stmt->execute([':db_name' => DB_NAME]);
    
    if ($stmt->fetchColumn() == 0) {
        // Add the column
        $conn->exec("ALTER TABLE personnes ADD COLUMN nationalites JSON DEFAULT NULL COMMENT 'Liste des nationalités supplémentaires'");
        echo "Column 'nationalites' added successfully.\n";
    } else {
        echo "Column 'nationalites' already exists.\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

