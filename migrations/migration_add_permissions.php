<?php
/**
 * Migration to add permissions to the users table.
 * File: migrations/migration_add_permissions.php
 */

require_once __DIR__ . '/../config/database.php';

try {
    // 1. Add permissions column (JSON string)
    // We use TEXT for compatibility, will store JSON array like ["documents", "users"]
    $conn->exec("ALTER TABLE users ADD COLUMN permissions TEXT NULL AFTER role");
    echo "Column 'permissions' added successfully.\n";

    // 2. Set Super Admin (ID 1) with all permissions
    // Module keys: "students", "export", "users", "slider", "upgrade", "documents", "communications", "settings"
    $allPermissions = json_encode(["students", "export", "users", "slider", "upgrade", "documents", "communications", "settings"]);
    $stmt = $conn->prepare("UPDATE users SET permissions = ? WHERE id_user = 1");
    $stmt->execute([$allPermissions]);
    echo "Super Admin (ID 1) permissions initialized.\n";

    // 3. Set existing admins to have all permissions except 'documents' by default
    // or as per your requirement. Let's give them all for now except documents.
    $defaultAdminPermissions = json_encode(["students", "export", "users", "slider", "upgrade", "communications", "settings"]);
    $stmt = $conn->prepare("UPDATE users SET permissions = ? WHERE role = 'admin' AND id_user != 1");
    $stmt->execute([$defaultAdminPermissions]);
    echo "Other admins permissions initialized (excluding documents).\n";

} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
