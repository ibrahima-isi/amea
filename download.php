<?php
/**
 * Secure file download handler for student documents.
 * Requires admin session. Streams the file with proper headers.
 * URL: download.php?id=STUDENT_ID&type=identite|cv
 */

require_once 'config/session.php';
require_once 'config/database.php';
require_once 'functions/utility-functions.php';

// Auth guard
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Accès refusé.');
}

$studentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$type      = $_GET['type'] ?? '';

if ($studentId <= 0 || !in_array($type, ['identite', 'cv'], true)) {
    http_response_code(400);
    exit('Paramètres invalides.');
}

// Fetch the student record
$stmt = $conn->prepare('SELECT nom, prenom, identite, cv_path FROM personnes WHERE id_personne = ?');
$stmt->execute([$studentId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    http_response_code(404);
    exit('Étudiant introuvable.');
}

$filePath = $type === 'identite' ? ($student['identite'] ?? '') : ($student['cv_path'] ?? '');

if (empty($filePath)) {
    http_response_code(404);
    exit('Aucun document disponible.');
}

// Resolve path — stored as relative from project root
$absolutePath = __DIR__ . '/' . ltrim($filePath, '/');

// 404 first: if the file simply doesn't exist on disk
if (!is_file($absolutePath)) {
    http_response_code(404);
    exit('Fichier introuvable.');
}

// Security: resolved path must be inside uploads/ (prevents path traversal)
$uploadsDir = realpath(__DIR__ . '/uploads');
$realFile   = realpath($absolutePath);
if ($uploadsDir === false || $realFile === false || strpos($realFile, $uploadsDir) !== 0) {
    http_response_code(403);
    exit('Accès interdit.');
}

// Detect MIME type from actual content
$finfo    = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $realFile);
finfo_close($finfo);

$allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
if (!in_array($mimeType, $allowedMimes, true)) {
    http_response_code(415);
    exit('Type de fichier non supporté.');
}

// Build a clean filename: Prenom_Nom_type.ext
$ext      = pathinfo($realFile, PATHINFO_EXTENSION);
$label    = $type === 'identite' ? 'identite' : 'cv';
$cleanName = preg_replace('/[^a-z0-9_\-]/i', '_',
    $student['prenom'] . '_' . $student['nom'] . '_' . $label) . '.' . $ext;

// Stream — clean output buffer first to prevent corruption
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . $cleanName . '"');
header('Content-Length: ' . filesize($realFile));
header('Cache-Control: no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

readfile($realFile);
exit();
