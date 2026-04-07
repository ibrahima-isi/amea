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

$autoload = __DIR__ . '/vendor/autoload.php';
echo "1. vendor/autoload.php exists: ";
if (!file_exists($autoload)) {
    die("NO\n");
}
echo "YES\n";
require_once $autoload;

$user = env('MAIL_USER');
$pass = env('MAIL_PASS');
echo "2. MAIL_USER: " . ($user ?: 'NOT SET') . "\n";
echo "3. MAIL_PASS length: " . strlen($pass) . " chars\n";
echo "4. MAIL_PASS first/last char codes: " . ord($pass[0]) . " / " . ord($pass[strlen($pass)-1]) . " (should be alphanumeric, not 10/13/32)\n";

// Try port 587 STARTTLS
echo "\n5. Trying port 587 (STARTTLS)... ";
try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp-relay.brevo.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = $user;
    $mail->Password   = $pass;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';
    $mail->setFrom('no-reply@aeesgs.org', 'AEESGS');
    $mail->addAddress($to);
    $mail->isHTML(true);
    $mail->Subject = 'Test email – AEESGS (587)';
    $mail->Body    = '<p>Test via port 587.</p>';
    $mail->send();
    echo "SUCCESS\n";
} catch (Exception $e) {
    echo "FAILED — " . $e->getMessage() . "\n";

    // Try port 465 SSL as fallback
    echo "6. Trying port 465 (SSL)... ";
    try {
        $mail2 = new PHPMailer(true);
        $mail2->isSMTP();
        $mail2->Host       = 'smtp-relay.brevo.com';
        $mail2->SMTPAuth   = true;
        $mail2->Username   = $user;
        $mail2->Password   = $pass;
        $mail2->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail2->Port       = 465;
        $mail2->CharSet    = 'UTF-8';
        $mail2->setFrom('no-reply@aeesgs.org', 'AEESGS');
        $mail2->addAddress($to);
        $mail2->isHTML(true);
        $mail2->Subject = 'Test email – AEESGS (465)';
        $mail2->Body    = '<p>Test via port 465.</p>';
        $mail2->send();
        echo "SUCCESS\n";
    } catch (Exception $e2) {
        echo "FAILED — " . $e2->getMessage() . "\n";
    }
}

echo '</pre>';
