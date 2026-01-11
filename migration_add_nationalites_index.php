<?php
require_once 'config/database.php';

try {
    // 1. Ensure the column exists (redundant if migration_add_nationalites.php ran, but safe)
    $stmt = $conn->prepare(
        "SELECT COUNT(*) 
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = :db_name 
        AND TABLE_NAME = 'personnes' 
        AND COLUMN_NAME = 'nationalites'"
    );
    $stmt->execute([':db_name' => DB_NAME]);
    
    if ($stmt->fetchColumn() == 0) {
        $conn->exec("ALTER TABLE personnes ADD COLUMN nationalites JSON DEFAULT NULL COMMENT 'Liste des nationalités supplémentaires'");
        echo "Column 'nationalites' added successfully.\n";
    }

    // 2. Check if the Multi-Valued Index exists
    $stmt = $conn->prepare(
        "SELECT COUNT(*)
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = :db_name
        AND TABLE_NAME = 'personnes'
        AND INDEX_NAME = 'idx_nationalites'"
    );
    $stmt->execute([':db_name' => DB_NAME]);

    if ($stmt->fetchColumn() == 0) {
        // Add the Multi-Valued Index
        // CAST(nationalites AS CHAR(100) ARRAY) extracts the strings from the JSON array
        $sql = "ALTER TABLE personnes ADD INDEX idx_nationalites ( (CAST(nationalites AS CHAR(100) ARRAY)) )";
        $conn->exec($sql);
        echo "Multi-Valued Index 'idx_nationalites' added successfully.\n";
    } else {
        echo "Index 'idx_nationalites' already exists.\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

