<?php

/**
 * Reset password page.
 * File: reset-password.php
 */

require_once 'config/session.php';
require_once 'config/database.php';
require_once 'functions/utility-functions.php';
require_once 'functions/email-service.php';

$token = trim($_GET['token'] ?? '');
$resetService = new \Amea\Service\PasswordResetService(
    $conn,
    (string)env('APP_URL', 'http://localhost'),
    __DIR__
);

if (empty($token)) {
    header('Location: login.php');
    exit();
}

if (!$resetService->isTokenUsable($token)) {
    setFlashMessage('error', 'Ce lien de réinitialisation est invalide ou expiré.');
    header('Location: login.php');
    exit();
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors['csrf'] = 'La session a expiré. Veuillez réessayer.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($password) || empty($confirm_password)) {
            $errors['password'] = 'Veuillez remplir tous les champs.';
        } else {
            $result = $resetService->resetPassword($token, $password, $confirm_password, 'sendMail');

            if ($result['success']) {
                $_SESSION = [];
                session_regenerate_id(true);
                $_SESSION['last_activity'] = time();
                setFlashMessage('success', $result['message']);
                header('Location: login.php');
            } else {
                $errors['password'] = $result['message'];
            }
            if ($result['success']) {
                exit();
            }
        }
    }
}

$templatePath = __DIR__ . '/templates/reset-password.html';
if (!is_file($templatePath)) {
    http_response_code(500);
    exit('Template introuvable.');
}

$tpl = file_get_contents($templatePath);

$headerTpl = file_get_contents(__DIR__ . '/templates/partials/header.html');
$footerTpl = strtr(file_get_contents(__DIR__ . '/templates/partials/footer.html'), getFooterReplacements());
$flash = getFlashMessage();
$flash_json = '';
if ($flash) {
    $flash_json = json_encode($flash);
}

$validation_errors_json = '';
if (!empty($errors)) {
    $validation_errors_json = json_encode($errors);
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
    '{{error_password}}' => $errors['password'] ?? '',
    '{{error_confirm_password}}' => $errors['confirm_password'] ?? '',
    '{{flash_json}}' => $flash_json,
    '{{validation_errors_json}}' => $validation_errors_json,
]);

echo addVersionToAssets($output);
