<?php
/**
 * Add server-side session invalidation version to users.
 */

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

require_once __DIR__ . '/../config/database.php';

function usersColumnExists(PDO $conn, string $column): bool
{
    $stmt = $conn->query("SHOW COLUMNS FROM users LIKE " . $conn->quote($column));
    return $stmt !== false && $stmt->rowCount() > 0;
}

try {
    echo "Starting migration: add users.session_version...\n";

    if (usersColumnExists($conn, 'session_version')) {
        echo "Column 'session_version' already exists. Skipping.\n";
        exit(0);
    }

    $conn->exec("ALTER TABLE users ADD COLUMN session_version INT NOT NULL DEFAULT 1");
    $conn->exec("UPDATE users SET session_version = 1 WHERE session_version IS NULL OR session_version < 1");

    echo "Column 'session_version' added successfully.\n";
    echo "Migration completed successfully.\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
