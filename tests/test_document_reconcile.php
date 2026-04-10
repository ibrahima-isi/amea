<?php
/**
 * TDD tests for document reconciliation logic.
 * Run from project root: php tests/test_document_reconcile.php
 */

require_once __DIR__ . '/../functions/document-reconcile.php';

// ─── Minimal test runner ──────────────────────────────────────────────────────
$passed = 0;
$failed = 0;

function expect(string $name, bool $result): void {
    global $passed, $failed;
    if ($result) {
        echo "\033[32m  ✓ $name\033[0m\n";
        $passed++;
    } else {
        echo "\033[31m  ✗ $name\033[0m\n";
        $failed++;
    }
}

// ─── Fixtures ─────────────────────────────────────────────────────────────────
$tmpDir = sys_get_temp_dir() . '/reconcile_test_' . uniqid();
mkdir("$tmpDir/uploads", 0777, true);
mkdir("$tmpDir/uploads/students", 0777, true);
mkdir("$tmpDir/uploads/students/cvs", 0777, true);

// Create test files in various locations
file_put_contents("$tmpDir/uploads/students/abc123.jpg",       'img');
file_put_contents("$tmpDir/uploads/abc456.pdf",                'pdf');  // flat in uploads/
file_put_contents("$tmpDir/uploads/students/def789.png",       'img');
file_put_contents("$tmpDir/uploads/students/cvs/cv001.pdf",    'cv');

$searchDirs = ['uploads/', 'uploads/students/', 'uploads/students/cvs/'];

// ─── Test: dbPathExists ───────────────────────────────────────────────────────
echo "\ndbPathExists()\n";

expect(
    'returns true when file is at exact DB path',
    dbPathExists('uploads/students/abc123.jpg', $tmpDir)
);
expect(
    'returns false for wrong path',
    !dbPathExists('uploads/students/missing.jpg', $tmpDir)
);
expect(
    'returns false for null/empty path',
    !dbPathExists('', $tmpDir)
);

// ─── Test: findAlternativePath ────────────────────────────────────────────────
echo "\nfindAlternativePath()\n";

expect(
    'finds file when it moved to uploads/ root',
    findAlternativePath('uploads/students/abc456.pdf', $tmpDir, $searchDirs) === 'uploads/abc456.pdf'
);
expect(
    'finds file in uploads/students/ when DB says cvs/',
    findAlternativePath('uploads/students/cvs/def789.png', $tmpDir, $searchDirs) === 'uploads/students/def789.png'
);
expect(
    'returns null when file not found anywhere',
    findAlternativePath('uploads/students/ghost.jpg', $tmpDir, $searchDirs) === null
);
expect(
    'returns null for empty DB path',
    findAlternativePath('', $tmpDir, $searchDirs) === null
);
expect(
    'does not return the same path as the DB (only alternatives)',
    findAlternativePath('uploads/students/abc123.jpg', $tmpDir, $searchDirs) === null
);

// ─── Test: scanUploadFiles ────────────────────────────────────────────────────
echo "\nscanUploadFiles()\n";

$allFiles = scanUploadFiles($tmpDir, $searchDirs);
expect(
    'finds files in uploads/students/',
    in_array('uploads/students/abc123.jpg', $allFiles)
);
expect(
    'finds files in uploads/ root',
    in_array('uploads/abc456.pdf', $allFiles)
);
expect(
    'finds files in uploads/students/cvs/',
    in_array('uploads/students/cvs/cv001.pdf', $allFiles)
);
expect(
    'does not include directories',
    !in_array('uploads/students', $allFiles) && !in_array('uploads/students/', $allFiles)
);

// ─── Test: findOrphanedFiles ──────────────────────────────────────────────────
echo "\nfindOrphanedFiles()\n";

$dbPaths = [
    'uploads/students/abc123.jpg',  // exists, linked
    null,                           // null entry
    'uploads/students/cvs/cv001.pdf', // exists, linked
];
$orphans = findOrphanedFiles($allFiles, $dbPaths);

expect(
    'detects abc456.pdf as orphaned (not in DB)',
    in_array('uploads/abc456.pdf', $orphans)
);
expect(
    'detects def789.png as orphaned (not in DB)',
    in_array('uploads/students/def789.png', $orphans)
);
expect(
    'does not mark abc123.jpg as orphaned (it IS in DB)',
    !in_array('uploads/students/abc123.jpg', $orphans)
);
expect(
    'does not mark cv001.pdf as orphaned (it IS in DB)',
    !in_array('uploads/students/cvs/cv001.pdf', $orphans)
);

// ─── Test: classifyDocument ──────────────────────────────────────────────────
echo "\nclassifyDocument()\n";

expect(
    'classifies null DB path as "null"',
    classifyDocument(null, $tmpDir, $searchDirs)['status'] === 'null'
);
expect(
    'classifies existing-at-exact-path as "ok"',
    classifyDocument('uploads/students/abc123.jpg', $tmpDir, $searchDirs)['status'] === 'ok'
);
expect(
    'classifies wrong-path-but-found-elsewhere as "fixable"',
    classifyDocument('uploads/students/cvs/abc456.pdf', $tmpDir, $searchDirs)['status'] === 'fixable'
);
expect(
    'fixable result includes the found_at path',
    classifyDocument('uploads/students/cvs/abc456.pdf', $tmpDir, $searchDirs)['found_at'] === 'uploads/abc456.pdf'
);
expect(
    'classifies truly missing file as "missing"',
    classifyDocument('uploads/students/ghost.jpg', $tmpDir, $searchDirs)['status'] === 'missing'
);

// ─── Cleanup ─────────────────────────────────────────────────────────────────
// Cleanup — files only, then dirs deepest-first
foreach (glob("$tmpDir/uploads/students/cvs/*") as $f) { if (is_file($f)) unlink($f); }
foreach (glob("$tmpDir/uploads/students/*")     as $f) { if (is_file($f)) unlink($f); }
foreach (glob("$tmpDir/uploads/*")              as $f) { if (is_file($f)) unlink($f); }
@rmdir("$tmpDir/uploads/students/cvs");
@rmdir("$tmpDir/uploads/students");
@rmdir("$tmpDir/uploads");
@rmdir($tmpDir);

// ─── Summary ─────────────────────────────────────────────────────────────────
echo "\n";
$total = $passed + $failed;
echo "\033[" . ($failed > 0 ? '31' : '32') . "m  $passed/$total tests passed\033[0m\n\n";
exit($failed > 0 ? 1 : 0);
