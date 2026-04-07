<?php
/**
 * Temporary email diagnostic script — DELETE after testing.
 * Access: https://aeesgs.org/test-mail.php?to=your@email.com
 */

// Only allow local/admin access — remove or restrict before deploying to prod
if (empty($_GET['to'])) {
    die('Usage: ?to=your@email.com');
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/functions/email-service.php';

$to = filter_var($_GET['to'], FILTER_VALIDATE_EMAIL);
if (!$to) {
    die('Invalid email address.');
}

echo '<pre>';

// 1. Check vendor/autoload.php
echo "1. vendor/autoload.php exists: ";
echo file_exists(__DIR__ . '/vendor/autoload.php') ? "YES\n" : "NO — composer install probably failed\n";

// 2. Check MAIL_USER / MAIL_PASS
echo "2. MAIL_USER set: " . (env('MAIL_USER') ? 'YES (' . env('MAIL_USER') . ")\n" : "NO\n");
echo "3. MAIL_PASS set: " . (env('MAIL_PASS') ? "YES\n" : "NO\n");

// 3. Try sending
echo "4. Sending test email to $to ... ";
$body = '<p>Ceci est un email de test envoyé depuis la plateforme AEESGS.</p>';
$result = sendMail($to, 'Test email – AEESGS', $body);
echo $result ? "SUCCESS\n" : "FAILED — check logs/error.log\n";

echo '</pre>';
