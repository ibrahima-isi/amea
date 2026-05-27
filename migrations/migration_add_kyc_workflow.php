<?php

/**
 * Migration: Add KYC workflow fields to the personnes table.
 * Statuses: PENDING_CONFIRMATION, UNDER_REVIEW, APPROVED, NEEDS_CLARIFICATION, REJECTED
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Config/Database.php';

use Amea\Config\Database;

try {
    $pdo = Database::fromEnv()->getConnection();

    echo "Starting migration: Add KYC workflow fields...\n";

    // 1. Add kyc_status column
    $pdo->exec("ALTER TABLE personnes 
        ADD COLUMN kyc_status ENUM('PENDING_CONFIRMATION', 'UNDER_REVIEW', 'APPROVED', 'NEEDS_CLARIFICATION', 'REJECTED') 
        NOT NULL DEFAULT 'PENDING_CONFIRMATION'");
    echo "Added 'kyc_status' column.\n";

    // 2. Add kyc_notes column
    $pdo->exec("ALTER TABLE personnes ADD COLUMN kyc_notes TEXT DEFAULT NULL");
    echo "Added 'kyc_notes' column.\n";

    // 3. Add review_token column
    $pdo->exec("ALTER TABLE personnes ADD COLUMN review_token VARCHAR(64) DEFAULT NULL UNIQUE");
    echo "Added 'review_token' column.\n";

    // 4. Add kyc_updated_at column
    $pdo->exec("ALTER TABLE personnes ADD COLUMN kyc_updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    echo "Added 'kyc_updated_at' column.\n";

    // Update existing records to 'APPROVED' if they are already confirmed/active? 
    // Since this is a new workflow, we might want to mark existing ones as APPROVED or UNDER_REVIEW.
    // For now, let's keep it simple and keep the default or maybe set all existing to APPROVED.
    $pdo->exec("UPDATE personnes SET kyc_status = 'APPROVED'");
    echo "Marked existing records as 'APPROVED'.\n";

    echo "Migration completed successfully.\n";

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
