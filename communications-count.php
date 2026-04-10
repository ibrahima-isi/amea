<?php
/**
 * AJAX endpoint — returns the number of recipients matching the given filters.
 * Used by the communications page "Aperçu des destinataires" button.
 */

require_once 'config/session.php';
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['count' => 0]);
    exit();
}

require_once 'config/database.php';

$statut  = $_GET['statut_filter']  ?? '';
$consent = $_GET['consent_filter'] ?? '';

$where  = ["email IS NOT NULL AND email != ''"];
$params = [];

if (!empty($statut)) {
    $where[]          = 'statut = :statut';
    $params[':statut'] = $statut;
}
if ($consent === 'consented') {
    $where[] = 'consent_privacy = 1';
} elseif ($consent === 'not_consented') {
    $where[] = '(consent_privacy = 0 OR consent_privacy IS NULL)';
}

$sql  = 'SELECT COUNT(*) FROM personnes WHERE ' . implode(' AND ', $where);
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$count = (int)$stmt->fetchColumn();

header('Content-Type: application/json');
echo json_encode(['count' => $count]);
