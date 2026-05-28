<?php
/**
 * Password reset security tests.
 *
 * Uses SQLite in-memory so it can run in CI without MySQL.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$passed = 0;
$failed = 0;

function expect(string $name, bool $result): void
{
    global $passed, $failed;
    if ($result) {
        echo "\033[32m  ✓ {$name}\033[0m\n";
        $passed++;
    } else {
        echo "\033[31m  ✗ {$name}\033[0m\n";
        $failed++;
    }
}

function latestReset(PDO $db, string $email): ?array
{
    $stmt = $db->prepare('SELECT * FROM password_resets WHERE email = ? ORDER BY expires_at DESC LIMIT 1');
    $stmt->execute([$email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

echo "\nPasswordResetService security\n";

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$db->exec("
    CREATE TABLE users (
        id_user INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        email TEXT NOT NULL UNIQUE,
        nom TEXT NOT NULL DEFAULT '',
        prenom TEXT NOT NULL DEFAULT '',
        password TEXT NOT NULL DEFAULT '',
        role TEXT NOT NULL DEFAULT 'user',
        permissions TEXT DEFAULT NULL,
        est_actif INTEGER NOT NULL DEFAULT 1,
        session_version INTEGER NOT NULL DEFAULT 1,
        date_creation TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        derniere_connexion TEXT DEFAULT NULL
    );

    CREATE TABLE password_resets (
        email TEXT NOT NULL,
        token TEXT NOT NULL PRIMARY KEY,
        expires_at TEXT NOT NULL
    );
");

$db->prepare("INSERT INTO users (username, email, nom, prenom, password, role, est_actif)
              VALUES ('reset_user', 'reset.user@test.local', 'User', 'Reset', ?, 'admin', 1)")
   ->execute([password_hash('OldPassword#2026', PASSWORD_DEFAULT)]);
$userId = (int)$db->lastInsertId();

$sent = [];
$mailer = function (string $to, string $subject, string $body) use (&$sent): bool {
    $sent[] = ['to' => $to, 'subject' => $subject, 'body' => $body];
    return true;
};

$service = new \Amea\Service\PasswordResetService($db, 'https://amea.example.test', __DIR__ . '/..');

$requestOk = $service->requestForEmail('reset.user@test.local', $mailer);
$reset = latestReset($db, 'reset.user@test.local');
$emailBody = $sent[0]['body'] ?? '';
preg_match('/token=([a-f0-9]{64})/i', $emailBody, $match);
$rawToken = $match[1] ?? '';

expect('requestForEmail() succeeds for existing active user', $requestOk === true);
expect('reset email contains a 64-hex raw token link', strlen($rawToken) === 64 && ctype_xdigit($rawToken));
expect('database stores token hash, not raw token', $reset !== null && $reset['token'] === hash('sha256', $rawToken) && $reset['token'] !== $rawToken);
expect('stored token hash is 64 hex chars', $reset !== null && strlen($reset['token']) === 64 && ctype_xdigit($reset['token']));

$expiresAt = new DateTimeImmutable($reset['expires_at']);
$now = new DateTimeImmutable();
$ttl = $expiresAt->getTimestamp() - $now->getTimestamp();
expect('reset token expires in 5 minutes max', $ttl > 0 && $ttl <= 300);

$unknownOk = $service->requestForEmail('unknown@test.local', $mailer);
$unknownCount = (int)$db->query("SELECT COUNT(*) FROM password_resets WHERE email = 'unknown@test.local'")->fetchColumn();
expect('unknown email returns generic success', $unknownOk === true);
expect('unknown email does not create reset token', $unknownCount === 0);

$complete = $service->resetPassword($rawToken, 'NewPassword#2026', 'NewPassword#2026', $mailer);
$updatedHash = (string)$db->query("SELECT password FROM users WHERE id_user = {$userId}")->fetchColumn();
$sessionVersion = (int)$db->query("SELECT session_version FROM users WHERE id_user = {$userId}")->fetchColumn();
$remainingTokens = (int)$db->query("SELECT COUNT(*) FROM password_resets WHERE email = 'reset.user@test.local'")->fetchColumn();
$confirmationBody = $sent[1]['body'] ?? '';

expect('resetPassword() accepts a valid token and matching strong password', $complete['success'] === true);
expect('resetPassword() updates the password hash', password_verify('NewPassword#2026', $updatedHash));
expect('resetPassword() increments session_version', $sessionVersion === 2);
expect('resetPassword() deletes all reset tokens for the email', $remainingTokens === 0);
expect('confirmation email does not include raw reset token', !str_contains($confirmationBody, $rawToken));
expect('confirmation email does not include new password', !str_contains($confirmationBody, 'NewPassword#2026'));

$reuse = $service->resetPassword($rawToken, 'AnotherPassword#2026', 'AnotherPassword#2026', $mailer);
expect('reset token cannot be reused', $reuse['success'] === false);

$service->requestForEmail('reset.user@test.local', $mailer);
$secondBody = $sent[2]['body'] ?? '';
preg_match('/token=([a-f0-9]{64})/i', $secondBody, $secondMatch);
$secondToken = $secondMatch[1] ?? '';
$weakComposition = $service->resetPassword($secondToken, 'alllowercasepassword', 'alllowercasepassword', $mailer);
$validAfterWeak = $service->resetPassword($secondToken, 'AnotherStrong#2026', 'AnotherStrong#2026', $mailer);
expect('resetPassword() rejects passwords without mixed character classes', $weakComposition['success'] === false);
expect('weak password attempt does not consume the reset token', $validAfterWeak['success'] === true);

$service->requestForEmail('reset.user@test.local', $mailer);
$thirdBody = $sent[4]['body'] ?? '';
preg_match('/token=([a-f0-9]{64})/i', $thirdBody, $thirdMatch);
$thirdToken = $thirdMatch[1] ?? '';
$mismatch = $service->resetPassword($thirdToken, 'MismatchPassword#2026', 'DifferentPassword#2026', $mailer);
$short = $service->resetPassword($thirdToken, 'short', 'short', $mailer);
expect('resetPassword() rejects mismatched confirmation', $mismatch['success'] === false);
expect('resetPassword() rejects short password', $short['success'] === false);

$expiredHash = hash('sha256', str_repeat('a', 64));
$db->prepare('INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)')
   ->execute(['reset.user@test.local', $expiredHash, (new DateTimeImmutable('-1 minute'))->format('Y-m-d H:i:s')]);
$expired = $service->resetPassword(str_repeat('a', 64), 'ExpiredPassword#2026', 'ExpiredPassword#2026', $mailer);
expect('resetPassword() rejects expired tokens', $expired['success'] === false);

$schema = file_get_contents(__DIR__ . '/../schema.sql') ?: '';
$init = file_get_contents(__DIR__ . '/../database/init.sql') ?: '';
expect('schema.sql defines users.session_version', str_contains($schema, 'session_version'));
expect('database/init.sql defines users.session_version', str_contains($init, 'session_version'));
expect('schema.sql uses the application role enum values', str_contains($schema, "enum ('admin','user')") && str_contains($schema, "DEFAULT 'user'"));
expect('database/init.sql uses the application role enum values', str_contains($init, "enum('admin','user')") && str_contains($init, "DEFAULT 'user'"));
expect('migration exists for users.session_version', is_file(__DIR__ . '/../migrations/migration_add_user_session_version.php'));

echo "\n";
$total = $passed + $failed;
echo "\033[" . ($failed > 0 ? '31' : '32') . "m  {$passed}/{$total} tests passed\033[0m\n\n";
exit($failed > 0 ? 1 : 0);
