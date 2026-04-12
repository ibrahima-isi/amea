<?php
/**
 * Fix role ENUM: replace 'utilisateur' with 'user' to match application code.
 *
 * The PHP application and HTML templates have always used the value 'user'
 * for non-admin accounts, but the original DB schema had 'utilisateur'.
 * This migration aligns the schema with the rest of the codebase.
 *
 * Safe to run multiple times (idempotent via MODIFY COLUMN).
 */

require_once __DIR__ . '/../config/database.php';

try {
    $conn->exec("ALTER TABLE users MODIFY role ENUM('admin','user') NOT NULL DEFAULT 'user'");
    echo "Migration completed: role column is now ENUM('admin','user').\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
