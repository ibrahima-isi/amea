<?php
/**
 * Make non-personal registration fields optional at the database level.
 */

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

require_once __DIR__ . '/../config/database.php';

/**
 * @return array<string, mixed>|null
 */
function getPersonnesColumn(PDO $conn, string $column): ?array
{
    $stmt = $conn->query("SHOW COLUMNS FROM personnes LIKE " . $conn->quote($column));
    $columnInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    return $columnInfo ?: null;
}

try {
    $optionalColumns = [
        'age',
        'lieu_residence',
        'etablissement',
        'statut',
        'domaine_etudes',
        'niveau_etudes',
        'telephone',
        'email',
        'annee_arrivee',
        'type_logement',
        'precision_logement',
        'projet_apres_formation',
        'identite',
        'cv_path',
    ];

    foreach ($optionalColumns as $column) {
        $columnInfo = getPersonnesColumn($conn, $column);
        if ($columnInfo === null) {
            echo "Column '$column' not found. Skipping.\n";
            continue;
        }

        $type = $columnInfo['Type'];
        $conn->exec("ALTER TABLE personnes MODIFY COLUMN `$column` $type NULL DEFAULT NULL");
        $conn->exec("UPDATE personnes SET `$column` = NULL WHERE `$column` = ''");

        echo "Column '$column' is now nullable.\n";
    }

    echo "Migration completed successfully.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
