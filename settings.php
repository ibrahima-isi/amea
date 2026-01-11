<?php

/**
 * System settings page.
 * File: settings.php
 */

require_once 'config/session.php';
require_once 'config/database.php';
require_once 'functions/utility-functions.php';

// Vérifier si l'utilisateur est connecté et est un administrateur
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$nom = $_SESSION['nom'];
$prenom = $_SESSION['prenom'];
$role = $_SESSION['role']; // Define role for sidebar

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $settingsToUpdate = [
            'contact_email' => filter_var($_POST['contact_email'] ?? '', FILTER_SANITIZE_EMAIL),
            'contact_phone' => htmlspecialchars($_POST['contact_phone'] ?? '')
        ];

        try {
            $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value) ON DUPLICATE KEY UPDATE setting_value = :value");
            
            foreach ($settingsToUpdate as $key => $value) {
                $stmt->execute([':key' => $key, ':value' => $value]);
            }
            
            setFlashMessage('success', 'Paramètres mis à jour avec succès.');
        } catch (PDOException $e) {
            setFlashMessage('error', 'Erreur lors de la mise à jour des paramètres : ' . $e->getMessage());
        }
    } else {
        setFlashMessage('error', 'Jeton de sécurité invalide.');
    }
    
    // Redirect to avoid resubmission
    header('Location: settings.php');
    exit();
}

// Récupération des valeurs actuelles
$currentSettings = [
    'contact_email' => getSetting('contact_email', 'admin@aeesgs.org'),
    'contact_phone' => getSetting('contact_phone', '+221 XX XXX XX XX')
];

// Template setup
$layoutPath = __DIR__ . '/templates/admin/layout.html';
$contentPath = __DIR__ . '/templates/admin/pages/settings.html';

if (!is_file($layoutPath) || !is_file($contentPath)) {
    http_response_code(500);
    exit('Template introuvable.');
}

ob_start();
include 'includes/sidebar.php';
$sidebarHtml = ob_get_clean();

$flash = getFlashMessage();
$flash_json = $flash ? json_encode($flash) : '';

$contentTpl = file_get_contents($contentPath);
$contentHtml = strtr($contentTpl, [
    '{{contact_email}}' => htmlspecialchars($currentSettings['contact_email']),
    '{{contact_phone}}' => htmlspecialchars($currentSettings['contact_phone']),
    '{{csrf_token}}' => generateCsrfToken()
]);

$layoutTpl = file_get_contents($layoutPath);
$output = strtr($layoutTpl, [
    '{{title}}' => 'Paramètres - AEESGS',
    '{{sidebar}}' => $sidebarHtml,
    '{{flash_json}}' => $flash_json,
    '{{admin_topbar}}' => strtr(file_get_contents(__DIR__ . '/templates/admin/partials/topbar.html'), [
        '{{user_fullname}}' => htmlspecialchars($prenom . ' ' . $nom),
    ]),
    '{{content}}' => $contentHtml,
    '{{admin_footer}}' => strtr(file_get_contents(__DIR__ . '/templates/admin/partials/footer.html'), getFooterReplacements()),
]);

echo addVersionToAssets($output);