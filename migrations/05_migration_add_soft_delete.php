<?php
/**
 * Add soft-delete (trash) support to students:
 *   deleted_at  — when the row was moved to the trash (NULL = active)
 *   deleted_by  — id of the admin who trashed it (audit)
 * plus an index on deleted_at so active/trashed filtering stays fast.
 *
 * Idempotent: columns and index are only added if missing, so the script can
 * be re-run safely on databases created from schema.sql (which already has them).
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

function studentsIndexExists(PDO $conn, string $index): bool
{
    $stmt = $conn->query("SHOW INDEX FROM students WHERE Key_name = " . $conn->quote($index));
    return $stmt !== false && $stmt->rowCount() > 0;
}

try {
    echo "Starting migration: add soft-delete columns to students...\n";

    addStudentsColumnIfMissing($conn, 'deleted_at', "DATETIME DEFAULT NULL");
    addStudentsColumnIfMissing($conn, 'deleted_by', "INT DEFAULT NULL");

    if (studentsIndexExists($conn, 'idx_students_deleted_at')) {
        echo "Index 'idx_students_deleted_at' already exists. Skipping.\n";
    } else {
        $conn->exec("CREATE INDEX idx_students_deleted_at ON students (deleted_at)");
        echo "Index 'idx_students_deleted_at' created successfully.\n";
    }

    echo "Migration completed successfully.\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
