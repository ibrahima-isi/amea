<?php

require_once 'config/database.php';
require_once 'functions/utility-functions.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Veuillez fournir une adresse e-mail valide.';
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
            $resetLink = getenv('APP_URL') . '/reset-password.php?token=' . $token;
            $emailBody = "<h1>Réinitialisation de votre mot de passe</h1>";
            $emailBody .= "<p>Cliquez sur le lien ci-dessous pour réinitialiser votre mot de passe:</p>";
            $emailBody .= "<a href='{$resetLink}'>{$resetLink}</a>";

            // For now, we'll just log the email to a file
            $logMessage = "To: {$email}\nSubject: Password Reset\nBody: {$emailBody}\n";
            file_put_contents('logs/mail.log', $logMessage, FILE_APPEND);

            $success = 'Un lien de réinitialisation de mot de passe a été envoyé à votre adresse e-mail.';
        } else {
            $error = 'Aucun utilisateur trouvé avec cette adresse e-mail.';
        }
    }
}

$templatePath = __DIR__ . '/templates/forgot-password.html';
if (!is_file($templatePath)) {
    http_response_code(500);
    exit('Template introuvable.');
}

$feedback = '';
if (!empty($success)) {
    $feedback = '<div class="alert alert-success">' . htmlspecialchars($success, ENT_QUOTES, 'UTF-8') . '</div>';
} elseif (!empty($error)) {
    $feedback = '<div class="alert alert-danger">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</div>';
}

$tpl = file_get_contents($templatePath);

$headerTpl = file_get_contents(__DIR__ . '/templates/partials/header.html');
$footerTpl = file_get_contents(__DIR__ . '/templates/partials/footer.html');

$headerHtml = strtr($headerTpl, [
    '{{index_active}}' => '',
    '{{register_active}}' => '',
    '{{login_active}}' => '',
]);

$output = strtr($tpl, [
    '{{header}}' => $headerHtml,
    '{{footer}}' => $footerTpl,
    '{{feedback_block}}' => $feedback,
]);

echo $output;

