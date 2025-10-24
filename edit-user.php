<?php

require_once 'config/session.php';
require_once 'config/database.php';
require_once 'functions/utility-functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

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

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'username' => trim($_POST['username'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'role' => $_POST['role'] ?? 'user',
        'est_actif' => $_POST['est_actif'] ?? 0
    ];

    $sql = "UPDATE users SET 
        username = :username, 
        email = :email, 
        role = :role, 
        est_actif = :est_actif
    WHERE id_user = :id_user";

    $stmt = $conn->prepare($sql);
    $stmt->execute(array_merge($formData, ['id_user' => $user_id_to_edit]));

    $success = 'Les informations de l\'utilisateur ont été mises à jour avec succès.';

    // Refresh user data
    $stmt = $conn->prepare("SELECT * FROM users WHERE id_user = ?");
    $stmt->execute([$user_id_to_edit]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
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

$contentHtml = strtr($template, [
    '{{feedback_block}}' => $success ? '<div class="alert alert-success">' . $success . '</div>' : ($error ? '<div class="alert alert-danger">' . $error . '</div>' : ''),
    '{{form_action}}' => 'edit-user.php?id=' . $user_id_to_edit,
    '{{csrf_token}}' => generateCsrfToken(),
    '{{username}}' => htmlspecialchars($user['username'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{email}}' => htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{role_sel_admin}}' => $sel('admin', $user['role']),
    '{{role_sel_user}}' => $sel('user', $user['role']),
    '{{est_actif_sel_1}}' => $sel(1, $user['est_actif']),
    '{{est_actif_sel_0}}' => $sel(0, $user['est_actif']),
]);

$layoutTpl = file_get_contents($layoutPath);
$output = strtr($layoutTpl, [
    '{{title}}' => 'AEESGS - Modifier l\'utilisateur',
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
