<?php

require_once 'config/session.php';
require_once 'config/database.php';
require_once 'functions/utility-functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$error = '';
$success = '';
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "La session a expiré. Veuillez soumettre à nouveau le formulaire.";
    } else {
        $formData = [
            'username' => trim($_POST['username'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'nom' => trim($_POST['nom'] ?? ''),
            'prenom' => trim($_POST['prenom'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'confirm_password' => $_POST['confirm_password'] ?? '',
            'role' => $_POST['role'] ?? 'utilisateur',
            'est_actif' => $_POST['est_actif'] ?? 0
        ];

        // Validation
        if (empty($formData['username']) || empty($formData['email']) || empty($formData['nom']) || empty($formData['prenom']) || empty($formData['password']) || empty($formData['confirm_password'])) {
            $error = 'Veuillez remplir tous les champs.';
        } elseif ($formData['password'] !== $formData['confirm_password']) {
            $error = 'Les mots de passe ne correspondent pas.';
        } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
            $error = 'L\'adresse email n\'est pas valide.';
        } else {
            // Check for existing username or email
            $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$formData['username'], $formData['email']]);
            if ($stmt->fetch()) {
                $error = 'Ce nom d\'utilisateur ou cet email est déjà utilisé.';
            } else {
                // Hash password
                $hashedPassword = password_hash($formData['password'], PASSWORD_DEFAULT);

                $sql = "INSERT INTO users (username, email, nom, prenom, password, role, est_actif, date_creation) VALUES (:username, :email, :nom, :prenom, :password, :role, :est_actif, NOW())";

                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    'username' => $formData['username'],
                    'email' => $formData['email'],
                    'nom' => $formData['nom'],
                    'prenom' => $formData['prenom'],
                    'password' => $hashedPassword,
                    'role' => $formData['role'],
                    'est_actif' => $formData['est_actif']
                ]);

                $success = 'L\'utilisateur a été ajouté avec succès.';
                $formData = []; // Clear form data after successful insertion
            }
        }
    }
}

$role = $_SESSION['role'];
$nom = $_SESSION['nom'];
$prenom = $_SESSION['prenom'];

// Rendu du template HTML
$layoutPath = __DIR__ . '/templates/admin/layout.html';
$templatePath = __DIR__ . '/templates/admin/pages/add-user.html';
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
    '{{form_action}}' => 'add-user.php',
    '{{csrf_token}}' => generateCsrfToken(),
    '{{username}}' => htmlspecialchars($formData['username'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{email}}' => htmlspecialchars($formData['email'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{nom}}' => htmlspecialchars($formData['nom'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{prenom}}' => htmlspecialchars($formData['prenom'] ?? '', ENT_QUOTES, 'UTF-8'),
    '{{role_sel_admin}}' => $sel('admin', $formData['role'] ?? 'user'),
    '{{role_sel_user}}' => $sel('user', $formData['role'] ?? 'user'),
    '{{est_actif_sel_1}}' => $sel(1, $formData['est_actif'] ?? 1),
    '{{est_actif_sel_0}}' => $sel(0, $formData['est_actif'] ?? 1),
]);

$layoutTpl = file_get_contents($layoutPath);
$output = strtr($layoutTpl, [
    '{{title}}' => 'AEESGS - Ajouter un utilisateur',
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

