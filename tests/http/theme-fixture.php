<?php
// CLI-only settings for the synthetic theme and export-preview checks.
if (PHP_SAPI !== 'cli' || !str_starts_with(basename(dirname(__DIR__, 2)), 'amea-http-')) {
    exit(1);
}
$db = new PDO('sqlite:' . dirname(__DIR__, 2) . '/fixture.sqlite');
$stmt = $db->prepare('UPDATE users SET permissions = ? WHERE username = ?');
$stmt->execute(['["slider","export"]', 'fixture-admin']);
$db->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('contact_email', 'contact@example.invalid')");
