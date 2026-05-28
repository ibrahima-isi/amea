<?php

/**
 * Migration: Add KYC workflow fields to the students table.
 * Statuses: PENDING_CONFIRMATION, UNDER_REVIEW, APPROVED, NEEDS_CLARIFICATION, REJECTED
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

function addPersonnesColumnIfMissing(PDO $conn, string $column, string $definition): bool
{
    if (studentsColumnExists($conn, $column)) {
        echo "Column '{$column}' already exists. Skipping.\n";
        return false;
    }

    $conn->exec("ALTER TABLE students ADD COLUMN {$definition}");
    echo "Added '{$column}' column.\n";
    return true;
}

try {
    echo "Starting migration: Add KYC workflow fields...\n";

    $addedKycStatus = addPersonnesColumnIfMissing(
        $conn,
        'kyc_status',
        "kyc_status ENUM('PENDING_CONFIRMATION', 'UNDER_REVIEW', 'APPROVED', 'NEEDS_CLARIFICATION', 'REJECTED') NOT NULL DEFAULT 'PENDING_CONFIRMATION'"
    );
    addPersonnesColumnIfMissing($conn, 'kyc_notes', 'kyc_notes TEXT DEFAULT NULL');
    addPersonnesColumnIfMissing($conn, 'review_token', 'review_token VARCHAR(64) DEFAULT NULL UNIQUE');
    addPersonnesColumnIfMissing(
        $conn,
        'kyc_updated_at',
        'kyc_updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
    );

    if ($addedKycStatus) {
        $conn->exec("UPDATE students SET kyc_status = 'APPROVED'");
        echo "Marked existing records as 'APPROVED'.\n";
    }

    echo "Migration completed successfully.\n";

} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
