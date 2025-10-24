<?php

require_once 'config/session.php';
require_once 'config/database.php';
require_once 'functions/utility-functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$role = $_SESSION['role'];
$nom = $_SESSION['nom'];
$prenom = $_SESSION['prenom'];

// Obtenir des statistiques de base
$statsSql = "SELECT 
    COUNT(*) as total, 
    SUM(CASE WHEN sexe = 'Masculin' THEN 1 ELSE 0 END) as hommes,
    SUM(CASE WHEN sexe = 'Féminin' THEN 1 ELSE 0 END) as femmes,
    SUM(CASE WHEN statut = 'Élève' THEN 1 ELSE 0 END) as eleves,
    SUM(CASE WHEN statut = 'Étudiant' THEN 1 ELSE 0 END) as etudiants,
    SUM(CASE WHEN statut = 'Stagiaire' THEN 1 ELSE 0 END) as stagiaires
FROM personnes";
$statsStmt = $conn->prepare($statsSql);
$statsStmt->execute();
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Obtenir une répartition par établissement (top 5)
$etablissementStatsSql = "SELECT etablissement, COUNT(*) as nombre FROM personnes GROUP BY etablissement ORDER BY nombre DESC LIMIT 5";
$etablissementStatsStmt = $conn->prepare($etablissementStatsSql);
$etablissementStatsStmt->execute();
$etablissementStats = $etablissementStatsStmt->fetchAll(PDO::FETCH_ASSOC);

// Données pour graphiques (top écoles)
$labels = [];
$values = [];
foreach ($etablissementStats as $stat) {
    $labels[] = "'" . addslashes($stat['etablissement']) . "'";
    $values[] = (int)$stat['nombre'];
}

// Rendu du template HTML
$layoutPath = __DIR__ . '/templates/admin/layout.html';
$contentPath = __DIR__ . '/templates/admin/pages/dashboard.html';
if (!is_file($layoutPath) || !is_file($contentPath)) {
    http_response_code(500);
    exit('Template introuvable.');
}

ob_start();
include 'includes/sidebar.php';
$sidebarHtml = ob_get_clean();

$contentTpl = file_get_contents($contentPath);
$contentHtml = strtr($contentTpl, [
    '{{user_fullname}}' => htmlspecialchars($prenom . ' ' . $nom, ENT_QUOTES, 'UTF-8'),
    '{{role_label}}' => $role == 'admin' ? 'administrateur' : 'utilisateur',
    '{{stats_total}}' => (string)$stats['total'],
    '{{stats_hommes}}' => (string)$stats['hommes'],
    '{{stats_femmes}}' => (string)$stats['femmes'],
    '{{stats_etudiants}}' => (string)$stats['etudiants'],
    '{{stats_eleves}}' => (string)$stats['eleves'],
    '{{stats_stagiaires}}' => (string)$stats['stagiaires'],
    '{{recent_week}}' => (string)(function() use ($conn) { $q = $conn->prepare("SELECT COUNT(*) FROM personnes WHERE date_enregistrement >= DATE_SUB(NOW(), INTERVAL 7 DAY)"); $q->execute(); return $q->fetchColumn(); })(),
    '{{school_labels}}' => implode(', ', $labels),
    '{{school_values}}' => implode(', ', $values),
]);

$layoutTpl = file_get_contents($layoutPath);
$output = strtr($layoutTpl, [
    '{{title}}' => 'AEESGS - Tableau de Bord',
    '{{sidebar}}' => $sidebarHtml,
    '{{admin_topbar}}' => strtr(file_get_contents(__DIR__ . '/templates/admin/partials/topbar.html'), [
        '{{user_fullname}}' => htmlspecialchars($prenom . ' ' . $nom, ENT_QUOTES, 'UTF-8'),
    ]),
    '{{content}}' => $contentHtml,
    '{{admin_footer}}' => strtr(file_get_contents(__DIR__ . '/templates/admin/partials/footer.html'), [
        '{{year}}' => date('Y'),
    ]),
]);

echo $output;