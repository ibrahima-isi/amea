<?php

/**
 * Admin dashboard.
 * File: dashboard.php
 */

require_once 'config/session.php';
require_once 'config/database.php';
require_once 'functions/utility-functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$role   = $_SESSION['role'];
$nom    = $_SESSION['nom'];
$prenom = $_SESSION['prenom'];

// Statistiques globales
$statsStmt = $conn->prepare("SELECT
    COUNT(*) as total,
    SUM(CASE WHEN sexe = 'Masculin'  THEN 1 ELSE 0 END) as hommes,
    SUM(CASE WHEN sexe = 'Féminin'   THEN 1 ELSE 0 END) as femmes,
    SUM(CASE WHEN statut = 'Élève'     THEN 1 ELSE 0 END) as eleves,
    SUM(CASE WHEN statut = 'Étudiant'  THEN 1 ELSE 0 END) as etudiants,
    SUM(CASE WHEN statut = 'Stagiaire' THEN 1 ELSE 0 END) as stagiaires
FROM personnes");
$statsStmt->execute();
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Nouveaux inscrits — 7 jours
$recentWeekStmt = $conn->prepare("SELECT COUNT(*) FROM personnes WHERE date_enregistrement >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$recentWeekStmt->execute();
$recentWeek = (int)$recentWeekStmt->fetchColumn();

// Nouveaux inscrits — 30 jours
$recentMonthStmt = $conn->prepare("SELECT COUNT(*) FROM personnes WHERE date_enregistrement >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$recentMonthStmt->execute();
$recentMonth = (int)$recentMonthStmt->fetchColumn();

// Top 5 établissements
$etablissementStmt = $conn->prepare("SELECT etablissement, COUNT(*) as nombre FROM personnes GROUP BY etablissement ORDER BY nombre DESC LIMIT 5");
$etablissementStmt->execute();
$etablissementStats = $etablissementStmt->fetchAll(PDO::FETCH_ASSOC);

$labels = [];
$values = [];
foreach ($etablissementStats as $stat) {
    $labels[] = "'" . addslashes($stat['etablissement']) . "'";
    $values[] = (int)$stat['nombre'];
}

// Rendu
$layoutPath  = __DIR__ . '/templates/admin/layout.html';
$contentPath = __DIR__ . '/templates/admin/pages/dashboard.html';
if (!is_file($layoutPath) || !is_file($contentPath)) {
    http_response_code(500);
    exit('Template introuvable.');
}

ob_start(); include 'includes/sidebar.php'; $sidebarHtml = ob_get_clean();

$contentTpl  = file_get_contents($contentPath);

$studentsBtn = hasPermission('students') 
    ? '<a href="students.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-user-graduate me-1"></i> Étudiants</a>' 
    : '';
$exportBtn = hasPermission('export') 
    ? '<a href="export.php" class="btn btn-primary btn-sm"><i class="fas fa-file-export me-1"></i> Exporter</a>' 
    : '';

$contentHtml = strtr($contentTpl, [
    '{{user_fullname}}'    => htmlspecialchars($prenom . ' ' . $nom, ENT_QUOTES, 'UTF-8'),
    '{{role_label}}'       => $role === 'admin' ? 'Administrateur' : 'Utilisateur',
    '{{students_btn}}'     => $studentsBtn,
    '{{export_btn}}'       => $exportBtn,
    '{{stats_total}}'      => number_format((int)$stats['total'], 0, ',', "\u{202F}"),
    '{{stats_hommes}}'     => (string)$stats['hommes'],
    '{{stats_femmes}}'     => (string)$stats['femmes'],
    '{{stats_etudiants}}'  => (string)$stats['etudiants'],
    '{{stats_eleves}}'     => (string)$stats['eleves'],
    '{{stats_stagiaires}}' => (string)$stats['stagiaires'],
    '{{recent_week}}'      => (string)$recentWeek,
    '{{recent_month}}'     => (string)$recentMonth,
    '{{school_labels}}'    => implode(', ', $labels),
    '{{school_values}}'    => implode(', ', $values),
]);

$flash      = getFlashMessage();
$flash_json = $flash ? json_encode($flash) : '';

$layoutTpl = file_get_contents($layoutPath);
$output    = strtr($layoutTpl, [
    '{{title}}'                  => 'AEESGS — Tableau de bord',
    '{{sidebar}}'                => $sidebarHtml,
    '{{flash_json}}'             => $flash_json,
    '{{validation_errors_json}}' => '',
    '{{admin_topbar}}'           => strtr(file_get_contents(__DIR__ . '/templates/admin/partials/topbar.html'), [
        '{{user_fullname}}' => htmlspecialchars($prenom . ' ' . $nom, ENT_QUOTES, 'UTF-8'),
    ]),
    '{{content}}'                => $contentHtml,
    '{{admin_footer}}'           => strtr(file_get_contents(__DIR__ . '/templates/admin/partials/footer.html'), getFooterReplacements()),
]);

echo addVersionToAssets($output);
