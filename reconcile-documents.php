<?php
/**
 * Utility script to reconcile and normalize database document paths.
 * Useful if the storage directory changes or if data becomes corrupted.
 */

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

require_once 'config/database.php';

echo "Reconciling Document Paths...\n";

// Function to find the correct path
function findValidPath(string $filename, array $searchDirs): ?string
{
    foreach ($searchDirs as $dir) {
        $path = rtrim($dir, '/') . '/' . ltrim($filename, '/');
        if (file_exists($path)) {
            return $path;
        }
    }
    return null;
}

$dirsToSearchIdentity = [
    'uploads/students',
    'uploads/etudiants',
    'uploads',
];

$dirsToSearchCv = [
    'uploads/students/cvs',
    'uploads/etudiants/cvs',
    'uploads',
];

$updatedIdentityCount = 0;
$updatedCvCount = 0;
$notFoundCount = 0;

try {
    $conn->beginTransaction();

    $stmt = $conn->query("SELECT id, identity_document, cv_path FROM students");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($students as $student) {
        // Identity Document
        if (!empty($student['identity_document'])) {
            $filename = basename($student['identity_document']);
            $validPath = findValidPath($filename, $dirsToSearchIdentity);

            if ($validPath && $validPath !== $student['identity_document']) {
                $upd = $conn->prepare('UPDATE students SET identity_document = ? WHERE id = ?');
                $upd->execute([$validPath, $student['id']]);
                $updatedIdentityCount++;
                echo "Updated Identity: ID {$student['id']} -> $validPath\n";
            } elseif (!$validPath) {
                echo "Warning: Identity file not found for ID {$student['id']} ($filename)\n";
                $notFoundCount++;
            }
        }

        // CV
        if (!empty($student['cv_path'])) {
            $filename = basename($student['cv_path']);
            $validPath = findValidPath($filename, $dirsToSearchCv);

            if ($validPath && $validPath !== $student['cv_path']) {
                $upd = $conn->prepare('UPDATE students SET cv_path = ? WHERE id = ?');
                $upd->execute([$validPath, $student['id']]);
                $updatedCvCount++;
                echo "Updated CV: ID {$student['id']} -> $validPath\n";
            } elseif (!$validPath) {
                echo "Warning: CV file not found for ID {$student['id']} ($filename)\n";
                $notFoundCount++;
            }
        }
    }

    $conn->commit();

    echo "Reconciliation complete.\n";
    echo "Identities updated: $updatedIdentityCount\n";
    echo "CVs updated: $updatedCvCount\n";
    echo "Files not found: $notFoundCount\n";

} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "Error: " . $e->getMessage() . "\n";
}