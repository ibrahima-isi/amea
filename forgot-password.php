<?php

/**
 * Forgot password page.
 * File: forgot-password.php
 */

require_once 'config/session.php';
require_once 'config/database.php';
require_once 'functions/utility-functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'La session a expiré. Veuillez réessayer.');
    } else {
        $email = trim($_POST['email'] ?? '');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlashMessage('error', 'Veuillez fournir une adresse e-mail valide.');
        } else {
            // Check if user exists
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // Generate a token
                $token = bin2hex(random_bytes(50));

                // Set expiration date
                $expires = new DateTime('+1 hour');
                $expires_at = $expires->format('Y-m-d H:i:s');

                // Store the token in the database
                $stmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
                $stmt->execute([$email, $token, $expires_at]);

                // Send the email
                $resetLink = env('APP_URL', 'http://localhost') . '/reset-password.php?token=' . $token;
                $subject = 'Réinitialisation de votre mot de passe';
                $emailBody = "<h1>Réinitialisation de votre mot de passe</h1>";
                $emailBody .= "<p>Cliquez sur le lien ci-dessous pour réinitialiser votre mot de passe:</p>";
                $emailBody .= "<a href='{$resetLink}'>{$resetLink}</a>";

                $headers = "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                $headers .= 'From: no-reply@aeesgs.org' . "\r\n";

                if (mail($email, $subject, $emailBody, $headers)) {
                    setFlashMessage('success', 'Un lien de réinitialisation de mot de passe a été envoyé à votre adresse e-mail.');
                } else {
                    setFlashMessage('error', 'Impossible d\'envoyer l\'e-mail de réinitialisation. Veuillez contacter un administrateur.');
                }
            } else {
                // To prevent user enumeration, show the same message whether the user exists or not.
                setFlashMessage('success', 'Si un compte avec cette adresse e-mail existe, un lien de réinitialisation de mot de passe a été envoyé.');
            }
        }
    }
    header('Location: forgot-password.php');
    exit();
}

$templatePath = __DIR__ . '/templates/forgot-password.html';
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
    '{{flash_json}}' => $flash_json,
]);

echo addVersionToAssets($output);

