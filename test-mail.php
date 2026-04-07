<?php
/**
 * Temporary email diagnostic script — DELETE after testing.
 * Access: https://aeesgs.org/test-mail.php?to=your@email.com
 */

use PHPMailer\PHPMailer\PHPMailer;

if (empty($_GET['to'])) {
    die('Usage: ?to=your@email.com');
}

require_once __DIR__ . '/config/database.php';

$to = filter_var($_GET['to'], FILTER_VALIDATE_EMAIL);
if (!$to) {
    die('Invalid email address.');
}

echo '<pre>';

// 1. Check vendor/autoload.php
$autoload = __DIR__ . '/vendor/autoload.php';
echo "1. vendor/autoload.php exists: ";
if (!file_exists($autoload)) {
    echo "NO — composer install probably failed\n";
} else {
    echo "YES\n";
    require_once $autoload;
}

// 2. Check MAIL_USER / MAIL_PASS
echo "2. MAIL_USER set: " . (env('MAIL_USER') ? 'YES (' . env('MAIL_USER') . ")\n" : "NO\n");
echo "3. MAIL_PASS set: " . (env('MAIL_PASS') ? "YES\n" : "NO\n");

// 3. Try sending — show PHPMailer error inline
echo "4. Sending test email to $to ... ";
try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp-relay.brevo.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = env('MAIL_USER');
    $mail->Password   = env('MAIL_PASS');
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';
    $mail->setFrom('no-reply@aeesgs.org', 'AEESGS');
    $mail->addAddress($to);
    $mail->isHTML(true);
    $mail->Subject = 'Test email – AEESGS';
    $mail->Body    = '<p>Ceci est un email de test.</p>';
    $mail->send();
    echo "SUCCESS\n";
} catch (Exception $e) {
    echo "FAILED\n";
    echo "PHPMailer Error: " . $e->getMessage() . "\n";
}

echo '</pre>';
