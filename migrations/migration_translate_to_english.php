<?php
/**
 * Migration: Translate all tables and columns from French to English.
 */

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

require_once __DIR__ . '/../config/database.php';

function tableExists(PDO $conn, string $table): bool
{
    $stmt = $conn->query("SHOW TABLES LIKE " . $conn->quote($table));
    return $stmt !== false && $stmt->rowCount() > 0;
}

function columnExists(PDO $conn, string $table, string $column): bool
{
    if (!tableExists($conn, $table)) {
        return false;
    }
    $stmt = $conn->query("SHOW COLUMNS FROM `$table` LIKE " . $conn->quote($column));
    return $stmt !== false && $stmt->rowCount() > 0;
}

function renameTable(PDO $conn, string $old, string $new): void
{
    if (tableExists($conn, $old)) {
        echo "Renaming table '$old' to '$new'...\n";
        $conn->exec("RENAME TABLE `$old` TO `$new` ");
    } else {
        echo "Table '$old' does not exist. Skipping.\n";
    }
}

function renameColumn(PDO $conn, string $table, string $old, string $new, string $definition): void
{
    if (columnExists($conn, $table, $old)) {
        echo "Renaming column '$table.$old' to '$new'...\n";
        $conn->exec("ALTER TABLE `$table` CHANGE COLUMN `$old` `$new` $definition");
    } elseif (columnExists($conn, $table, $new)) {
        echo "Column '$table.$new' already exists. Skipping.\n";
    } else {
        echo "Column '$table.$old' does not exist. Skipping.\n";
    }
}

try {
    // 1. Rename Tables
    renameTable($conn, 'personnes', 'students');
    renameTable($conn, 'pays', 'countries');
    renameTable($conn, 'personne_pays', 'student_country');
    renameTable($conn, 'domaines_etudes', 'study_fields');
    renameTable($conn, 'etablissements', 'institutions');
    renameTable($conn, 'niveaux_etudes', 'study_levels');

    // 2. Rename Columns in 'students'
    renameColumn($conn, 'students', 'id_personne', 'id', 'INT AUTO_INCREMENT');
    renameColumn($conn, 'students', 'nom', 'last_name', 'VARCHAR(100) NOT NULL');
    renameColumn($conn, 'students', 'prenom', 'first_name', 'VARCHAR(100) NOT NULL');
    renameColumn($conn, 'students', 'sexe', 'gender', "ENUM('Masculin', 'Féminin') NOT NULL");
    renameColumn($conn, 'students', 'date_naissance', 'birth_date', 'DATE DEFAULT NULL');
    renameColumn($conn, 'students', 'lieu_residence', 'residence', 'VARCHAR(100) DEFAULT NULL');
    renameColumn($conn, 'students', 'etablissement', 'institution', 'VARCHAR(150) DEFAULT NULL');
    renameColumn($conn, 'students', 'statut', 'status', "ENUM('ELEVE', 'ETUDIANT', 'STAGIAIRE') NOT NULL");
    renameColumn($conn, 'students', 'domaine_etudes', 'study_field', 'VARCHAR(150) DEFAULT NULL');
    renameColumn($conn, 'students', 'niveau_etudes', 'study_level', 'VARCHAR(100) DEFAULT NULL');
    renameColumn($conn, 'students', 'telephone', 'phone', 'VARCHAR(20) DEFAULT NULL');
    renameColumn($conn, 'students', 'annee_arrivee', 'arrival_year', 'INT DEFAULT NULL');
    renameColumn($conn, 'students', 'type_logement', 'housing_type', 'VARCHAR(100) DEFAULT NULL');
    renameColumn($conn, 'students', 'precision_logement', 'housing_details', 'VARCHAR(255) DEFAULT NULL');
    renameColumn($conn, 'students', 'projet_apres_formation', 'post_training_project', 'TEXT DEFAULT NULL');
    renameColumn($conn, 'students', 'identite', 'identity_document', 'VARCHAR(255) DEFAULT NULL');
    
    // Handle 'nationalites' which has a functional index
    if (columnExists($conn, 'students', 'nationalites')) {
        echo "Renaming column 'students.nationalites' to 'nationalities'...\n";
        try {
            $conn->exec("ALTER TABLE `students` DROP INDEX `idx_nationalites`");
        } catch (PDOException $e) {
            // Ignore if index doesn't exist
        }
        $conn->exec("ALTER TABLE `students` CHANGE COLUMN `nationalites` `nationalities` JSON DEFAULT NULL");
        try {
            $conn->exec("ALTER TABLE `students` ADD INDEX `idx_nationalities` ( (CAST(`nationalities` AS CHAR(100) ARRAY)) )");
        } catch (PDOException $e) {
            // Ignore if DB doesn't support multi-valued indexes
        }
    }

    renameColumn($conn, 'students', 'date_enregistrement', 'registration_date', 'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP');
    renameColumn($conn, 'students', 'date_diplomation', 'graduation_date', 'DATE DEFAULT NULL');

    // 3. Rename Columns in 'users'
    renameColumn($conn, 'users', 'id_user', 'id', 'INT AUTO_INCREMENT');
    renameColumn($conn, 'users', 'nom', 'last_name', 'VARCHAR(100) NOT NULL');
    renameColumn($conn, 'users', 'prenom', 'first_name', 'VARCHAR(100) NOT NULL');
    renameColumn($conn, 'users', 'est_actif', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1');
    renameColumn($conn, 'users', 'derniere_connexion', 'last_login', 'TIMESTAMP NULL DEFAULT NULL');
    renameColumn($conn, 'users', 'date_creation', 'created_at', 'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP');

    // 4. Rename Columns in 'countries'
    renameColumn($conn, 'countries', 'id_pays', 'id', 'INT AUTO_INCREMENT');
    renameColumn($conn, 'countries', 'nom_fr', 'name_fr', 'VARCHAR(100) NOT NULL');
    renameColumn($conn, 'countries', 'code_iso2', 'iso2_code', 'VARCHAR(2) DEFAULT NULL');

    // 5. Rename Columns in 'student_country'
    renameColumn($conn, 'student_country', 'id_personne', 'student_id', 'INT NOT NULL');
    renameColumn($conn, 'student_country', 'id_pays', 'country_id', 'INT NOT NULL');

    // 6. Rename Columns in 'pending_level_upgrades'
    renameColumn($conn, 'pending_level_upgrades', 'personne_id', 'student_id', 'INT NOT NULL');
    renameColumn($conn, 'pending_level_upgrades', 'ancien_niveau', 'old_level', 'VARCHAR(100) NOT NULL');
    renameColumn($conn, 'pending_level_upgrades', 'nouveau_niveau', 'new_level', 'VARCHAR(100) NOT NULL');

    // 7. Rename Columns in 'study_fields', 'institutions', 'study_levels'
    renameColumn($conn, 'study_fields', 'nom', 'name', 'VARCHAR(100) NOT NULL');
    renameColumn($conn, 'institutions', 'nom', 'name', 'VARCHAR(100) NOT NULL');
    renameColumn($conn, 'study_levels', 'nom', 'name', 'VARCHAR(100) NOT NULL');

    echo "Migration completed successfully.\n";

} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
