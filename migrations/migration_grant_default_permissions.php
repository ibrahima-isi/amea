<?php
/**
 * Grant all permissions to admin accounts that have NULL permissions.
 *
 * Safe to run multiple times — only updates rows where permissions IS NULL.
 * Does NOT touch ID 1 (Super Admin) or accounts that already have permissions set.
 *
 * This migration is needed because the permissions system requires an explicit
 * JSON array in the permissions column; a NULL value blocks all access.
 */

require_once __DIR__ . '/../config/database.php';

$allPermissions = json_encode([
    'students', 'export', 'users', 'slider',
    'upgrade', 'documents', 'communications', 'settings',
]);

try {
    $stmt = $conn->prepare(
        "UPDATE users SET permissions = ? WHERE role = 'admin' AND id_user != 1 AND permissions IS NULL"
    );
    $stmt->execute([$allPermissions]);
    $count = $stmt->rowCount();
    echo "Migration completed: $count admin account(s) updated with full permissions.\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
