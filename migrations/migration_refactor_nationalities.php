<?php
/**
 * Migration script to refactor nationalities into a relational structure.
 * File: migrations/migration_refactor_nationalities.php
 */

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

require_once __DIR__ . '/../config/database.php';

echo "Starting Nationalities Refactoring Migration...\n";

try {
    $conn->beginTransaction();

    // 1. Create 'pays' table
    echo "Creating 'pays' table...\n";
    $conn->exec("CREATE TABLE IF NOT EXISTS pays (
        id_pays INT AUTO_INCREMENT PRIMARY KEY,
        nom_fr VARCHAR(100) NOT NULL UNIQUE,
        code_iso2 VARCHAR(2) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 2. Create 'personne_pays' pivot table
    echo "Creating 'personne_pays' pivot table...\n";
    $conn->exec("CREATE TABLE IF NOT EXISTS personne_pays (
        id_personne INT NOT NULL,
        id_pays INT NOT NULL,
        PRIMARY KEY (id_personne, id_pays),
        FOREIGN KEY (id_personne) REFERENCES personnes(id_personne) ON DELETE CASCADE,
        FOREIGN KEY (id_pays) REFERENCES pays(id_pays) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 3. Populate 'pays' table from JSON
    echo "Populating 'pays' table...\n";
    $jsonFile = __DIR__ . '/../assets/json/countries.json';
    if (file_exists($jsonFile)) {
        $countries = json_decode(file_get_contents($jsonFile), true);
        if (is_array($countries)) {
            $stmt = $conn->prepare("INSERT IGNORE INTO pays (nom_fr) VALUES (:nom)");
            foreach ($countries as $country) {
                $stmt->execute([':nom' => trim($country)]);
            }
            echo "Inserted " . count($countries) . " countries.\n";
        } else {
            echo "Error: Invalid JSON format in countries.json\n";
        }
    } else {
        echo "Warning: assets/json/countries.json not found.\n";
    }

    // 4. Migrate existing data
    echo "Migrating existing student data...\n";
    
    // Check if 'nationalites' column exists
    $checkCol = $conn->query("SHOW COLUMNS FROM personnes LIKE 'nationalites'");
    if ($checkCol->rowCount() > 0) {
        $stmtUsers = $conn->query("SELECT id_personne, nationalites FROM personnes WHERE nationalites IS NOT NULL AND nationalites != ''");
        $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

        $stmtGetPays = $conn->prepare("SELECT id_pays FROM pays WHERE nom_fr = :nom");
        $stmtInsertPivot = $conn->prepare("INSERT IGNORE INTO personne_pays (id_personne, id_pays) VALUES (:pid, :cid)");

        foreach ($users as $user) {
            $nats = json_decode($user['nationalites'], true);
            
            // Handle Tagify format [{"value":"France"}] or simple array ["France"]
            if (is_array($nats)) {
                foreach ($nats as $nat) {
                    $countryName = '';
                    if (is_string($nat)) {
                        $countryName = $nat;
                    } elseif (is_array($nat) && isset($nat['value'])) {
                        $countryName = $nat['value'];
                    }

                    if ($countryName) {
                        $stmtGetPays->execute([':nom' => $countryName]);
                        $paysId = $stmtGetPays->fetchColumn();

                        if ($paysId) {
                            $stmtInsertPivot->execute([
                                ':pid' => $user['id_personne'],
                                ':cid' => $paysId
                            ]);
                        } else {
                            echo "Warning: Country '$countryName' for user ID {$user['id_personne']} not found in 'pays' table.\n";
                        }
                    }
                }
            }
        }
        echo "Data migration complete.\n";
    } else {
        echo "Column 'nationalites' does not exist, skipping data migration.\n";
    }

    $conn->commit();
    echo "Migration successfully finished.\n";

} catch (PDOException $e) {
    $conn->rollBack();
    echo "Error during migration: " . $e->getMessage() . "\n";
    exit(1);
}
