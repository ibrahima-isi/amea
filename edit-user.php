<?php

/**
 * Edit user page.
 * File: edit-user.php
 */

require_once 'config/session.php';
require_once 'functions/utility-functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if ($_SESSION['role'] !== 'admin') {
    setFlashMessage('error', 'Accès non autorisé.');
    header('Location: dashboard.php');
    exit();
}

require_once 'config/database.php';

$user_id_to_edit = $_GET['id'] ?? null;

if (!$user_id_to_edit) {
    header('Location: users.php');
    exit();
}

// Fetch user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id_user = ?");
$stmt->execute([$user_id_to_edit]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: users.php');
    exit();
}

$errors = [];
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'La session a expiré. Veuillez réessayer.');
        header('Location: edit-user.php?id=' . $user_id_to_edit);
        exit();
    }
    $formData = [
        'username' => trim($_POST['username'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'role' => $_POST['role'] ?? 'user',
        'est_actif' => $_POST['est_actif'] ?? 0
    ];

    if (empty($formData['username'])) {
        $errors['username'] = 'Le nom d\'utilisateur est requis.';
    }

    if (empty($formData['email'])) {
        $errors['email'] = 'L\'adresse email est requise.';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'L\'adresse email n\'est pas valide.';
    }

    if (empty($errors)) {
        $sql = "UPDATE users SET 
            username = :username, 
            email = :email, 
            role = :role, 
            est_actif = :est_actif
        WHERE id_user = :id_user";

        $stmt = $conn->prepare($sql);
        $stmt->execute(array_merge($formData, ['id_user' => $user_id_to_edit]));

        setFlashMessage('success', 'Les informations de l\'utilisateur ont été mises à jour avec succès.');
        header('Location: users.php');
        exit();
    }
}

$role = $_SESSION['role'];
$nom = $_SESSION['nom'];
$prenom = $_SESSION['prenom'];

// Rendu du template HTML
$layoutPath = __DIR__ . '/templates/admin/layout.html';
$templatePath = __DIR__ . '/templates/admin/pages/edit-user.html';
if (!is_file($layoutPath) || !is_file($templatePath)) {
    http_response_code(500);
    exit('Template introuvable.');
}

ob_start();
include 'includes/sidebar.php';
$sidebarHtml = ob_get_clean();

$template = file_get_contents($templatePath);

$sel = function ($value, $options) {
    return in_array($value, (array)$options) ? 'selected' : '';
};

$validation_errors_json = '';
if (!empty($errors)) {
    $validation_errors_json = json_encode($errors);
}

$contentHtml = strtr($template, [
    '{{feedback_block}}' => '',
    '{{form_action}}' => 'edit-user.php?id=' . $user_id_to_edit,
    '{{csrf_token}}' => generateCsrfToken(),
    '{{username}}' => htmlspecialchars($formData['username'] ?? $user['username'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{email}}' => htmlspecialchars($formData['email'] ?? $user['email'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{role_sel_admin}}' => $sel('admin', $formData['role'] ?? $user['role']),
    '{{role_sel_user}}' => $sel('user', $formData['role'] ?? $user['role']),
    '{{est_actif_sel_1}}' => $sel(1, $formData['est_actif'] ?? $user['est_actif']),
    '{{est_actif_sel_0}}' => $sel(0, $formData['est_actif'] ?? $user['est_actif']),
    '{{error_username}}' => $errors['username'] ?? '',
    '{{error_email}}' => $errors['email'] ?? '',
    '{{is_invalid_username}}' => isset($errors['username']) ? 'is-invalid' : '',
    '{{is_invalid_email}}' => isset($errors['email']) ? 'is-invalid' : '',
]);

$flash = getFlashMessage();
$flash_json = '';
if ($flash) {
    $flash_json = json_encode($flash);
}

$layoutTpl = file_get_contents($layoutPath);
$output = strtr($layoutTpl, [
    '{{flash_json}}' => $flash_json,
    '{{validation_errors_json}}' => $validation_errors_json,
    '{{title}}' => 'AEESGS - Modifier l\'utilisateur',
    '{{sidebar}}' => $sidebarHtml,
    '{{admin_topbar}}' => strtr(file_get_contents(__DIR__ . '/templates/admin/partials/topbar.html'), [
        '{{user_fullname}}' => htmlspecialchars($prenom . ' ' . $nom, ENT_QUOTES, 'UTF-8'),
    ]),
    '{{content}}' => $contentHtml,
    '{{admin_footer}}' => strtr(file_get_contents(__DIR__ . '/templates/admin/partials/footer.html'), getFooterReplacements()),
]);

echo addVersionToAssets($output);
