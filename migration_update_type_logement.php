<?php
require_once 'config/database.php';

echo "Updating 'type_logement' column...\n";

try {
    // 1. Change column to VARCHAR(100) to accommodate new values and avoid ENUM constraints
    $conn->exec("ALTER TABLE personnes MODIFY COLUMN type_logement VARCHAR(100) NULL");
    echo "Column 'type_logement' converted to VARCHAR(100).\n";

    // 2. Update existing data to match new format (remove 'En ' prefix)
    $updates = [
        'En famille' => 'Famille',
        'En colocation' => 'Colocation',
        'En résidence universitaire' => 'Résidence universitaire'
    ];

    foreach ($updates as $old => $new) {
        $stmt = $conn->prepare("UPDATE personnes SET type_logement = ? WHERE type_logement = ?");
        $stmt->execute([$new, $old]);
        $count = $stmt->rowCount();
        if ($count > 0) {
            echo "Updated $count rows from '$old' to '$new'.\n";
        }
    }

    echo "Migration completed successfully.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

