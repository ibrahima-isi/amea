<?php
/**
 * Make registration status required at the database level.
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
    echo "Starting migration: make statut required...\n";

    if (!personnesColumnExists($conn, 'statut')) {
        echo "Column 'statut' does not exist. Skipping.\n";
        exit(0);
    }

    // Check current enum
    $stmt = $conn->query("SHOW COLUMNS FROM personnes LIKE 'statut'");
    $col = $stmt->fetch();
    $currentType = $col['Type'];

    // If it already only contains uppercase values and is NOT NULL, we are done
    if ($currentType === "enum('ELEVE','ETUDIANT','STAGIAIRE')" && $col['Null'] === 'NO') {
        echo "Column 'statut' is already required and normalized. Skipping.\n";
        exit(0);
    }

    // If it does not contain the accented values, we can skip the first ALTER
    if (!str_contains($currentType, 'Élève')) {
        echo "Current enum does not contain accented values. Normalizing data...\n";
    } else {
        $conn->exec("ALTER TABLE personnes MODIFY COLUMN `statut` ENUM('Élève','Étudiant','Stagiaire','ELEVE','ETUDIANT','STAGIAIRE') NULL DEFAULT NULL");
    }

    $conn->exec("UPDATE personnes SET `statut` = 'ELEVE' WHERE `statut` = 'Élève'");
    $conn->exec("UPDATE personnes SET `statut` = 'ETUDIANT' WHERE `statut` = 'Étudiant'");
    $conn->exec("UPDATE personnes SET `statut` = 'STAGIAIRE' WHERE `statut` = 'Stagiaire'");
    $conn->exec("UPDATE personnes SET `statut` = 'ETUDIANT' WHERE `statut` IS NULL OR `statut` = ''");
    $conn->exec("ALTER TABLE personnes MODIFY COLUMN `statut` ENUM('ELEVE','ETUDIANT','STAGIAIRE') NOT NULL");

    echo "Column 'statut' is now required.\n";
    echo "Migration completed successfully.\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
