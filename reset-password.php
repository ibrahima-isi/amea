<?php

require_once 'config/session.php';
require_once 'config/database.php';
require_once 'functions/utility-functions.php';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    header('Location: login.php');
    exit();
}

// Verify the token
$stmt = $conn->prepare("SELECT * FROM password_resets WHERE token = ?");
$stmt->execute([$token]);
$reset = $stmt->fetch();

if (!$reset) {
    setFlashMessage('error', 'Ce jeton de réinitialisation de mot de passe n\'est pas valide.');
    header('Location: login.php');
    exit();
} else {
    $expires = new DateTime($reset['expires_at']);
    $now = new DateTime();

    if ($now > $expires) {
        setFlashMessage('error', 'Ce jeton de réinitialisation de mot de passe a expiré.');
        header('Location: login.php');
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'La session a expiré. Veuillez réessayer.');
    } else {
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($password)) {
            setFlashMessage('error', 'Veuillez remplir tous les champs.');
        } elseif ($password !== $confirm_password) {
            setFlashMessage('error', 'Les mots de passe ne correspondent pas.');
        } else {
            // Update the password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
            $stmt->execute([$hashedPassword, $reset['email']]);

            // Delete the token
            $stmt = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
            $stmt->execute([$token]);

            setFlashMessage('success', 'Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter.');
            header('Location: login.php');
            exit();
        }
    }
    header('Location: reset-password.php?token=' . $token);
    exit();
}

$templatePath = __DIR__ . '/templates/reset-password.html';
if (!is_file($templatePath)) {
    http_response_code(500);
    exit('Template introuvable.');
}

$tpl = file_get_contents($templatePath);

$headerTpl = file_get_contents(__DIR__ . '/templates/partials/header.html');
$footerTpl = file_get_contents(__DIR__ . '/templates/partials/footer.html');
$flash = getFlashMessage();
$flash_json = '';
if ($flash) {
    $flash_json = json_encode($flash);
}

$headerHtml = strtr($headerTpl, [
    '{{index_active}}' => '',
    '{{register_active}}' => '',
    '{{login_active}}' => '',
]);

$output = strtr($tpl, [
    '{{header}}' => $headerHtml,
    '{{footer}}' => $footerTpl,
    '{{csrf_token}}' => generateCsrfToken(),
    '{{feedback_block}}' => '', // Handled by flash messages
    '{{token}}' => htmlspecialchars($token, ENT_QUOTES, 'UTF-8'),
    '{{error_password}}' => '', // No longer used
    '{{error_confirm_password}}' => '', // No longer used
    '{{flash_json}}' => $flash_json,
]);

echo $output;

