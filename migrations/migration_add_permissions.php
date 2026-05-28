<?php
/**
 * Migration to add permissions to the users table.
 * File: migrations/migration_add_permissions.php
 */

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

require_once __DIR__ . '/../config/database.php';

try {
    // 1. Add permissions column (JSON string) if missing
    $stmt = $conn->query("SHOW COLUMNS FROM users LIKE 'permissions'");
    if ($stmt->rowCount() == 0) {
        $conn->exec("ALTER TABLE users ADD COLUMN permissions TEXT NULL AFTER role");
        echo "Column 'permissions' added successfully.\n";
    } else {
        echo "Column 'permissions' already exists. Skipping addition.\n";
    }

    // 2. Set Super Admin (ID 1) with all permissions if NULL
    $allPermissions = json_encode(["students", "export", "users", "slider", "upgrade", "documents", "communications", "settings"]);
    $stmt = $conn->prepare("UPDATE users SET permissions = ? WHERE id_user = 1 AND permissions IS NULL");
    $stmt->execute([$allPermissions]);
    if ($stmt->rowCount() > 0) {
        echo "Super Admin (ID 1) permissions initialized.\n";
    }

    // 3. Set existing admins to have all permissions except 'documents' by default
    $defaultAdminPermissions = json_encode(["students", "export", "users", "slider", "upgrade", "communications", "settings"]);
    $stmt = $conn->prepare("UPDATE users SET permissions = ? WHERE role = 'admin' AND id_user != 1 AND permissions IS NULL");
    $stmt->execute([$defaultAdminPermissions]);
    if ($stmt->rowCount() > 0) {
        echo "Other admins permissions initialized (excluding documents).\n";
    }

} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
