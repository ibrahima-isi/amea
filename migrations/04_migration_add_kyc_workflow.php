<?php
/**
 * Add the KYC workflow columns to students:
 * kyc_status, kyc_notes, review_token, kyc_updated_at.
 *
 * Idempotent: each column is only added if missing, so the script can be
 * re-run safely on databases created from schema.sql (which already has them).
 */

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

require_once __DIR__ . '/../config/database.php';

function studentsColumnExists(PDO $conn, string $column): bool
{
    $stmt = $conn->query("SHOW COLUMNS FROM students LIKE " . $conn->quote($column));
    return $stmt !== false && $stmt->rowCount() > 0;
}

function addStudentsColumnIfMissing(PDO $conn, string $column, string $definition): void
{
    if (studentsColumnExists($conn, $column)) {
        echo "Column '{$column}' already exists. Skipping.\n";
        return;
    }

    $conn->exec("ALTER TABLE students ADD COLUMN {$column} {$definition}");
    echo "Column '{$column}' added successfully.\n";
}

try {
    echo "Starting migration: add KYC workflow columns to students...\n";

    addStudentsColumnIfMissing(
        $conn,
        'kyc_status',
        "ENUM('PENDING_CONFIRMATION','UNDER_REVIEW','APPROVED','NEEDS_CLARIFICATION','REJECTED') NOT NULL DEFAULT 'PENDING_CONFIRMATION'"
    );
    addStudentsColumnIfMissing(
        $conn,
        'kyc_notes',
        "TEXT DEFAULT NULL"
    );
    addStudentsColumnIfMissing(
        $conn,
        'review_token',
        "VARCHAR(64) DEFAULT NULL UNIQUE"
    );
    addStudentsColumnIfMissing(
        $conn,
        'kyc_updated_at',
        "DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
    );

    // Existing members were registered before the KYC workflow: consider their
    // dossiers already reviewed rather than re-opening them for validation.
    $updated = $conn->exec(
        "UPDATE students SET kyc_status = 'APPROVED'
         WHERE kyc_status = 'PENDING_CONFIRMATION' AND is_locked = 1"
    );
    if ($updated > 0) {
        echo "Marked {$updated} pre-KYC locked dossier(s) as APPROVED.\n";
    }

    echo "Migration completed successfully.\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
