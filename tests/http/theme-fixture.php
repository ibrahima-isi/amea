<?php
// CLI-only permissions for synthetic theme export-preview checks.
if (PHP_SAPI !== 'cli' || !str_starts_with(basename(dirname(__DIR__, 2)), 'amea-http-')) {
    exit(1);
}
$db = new PDO('sqlite:' . dirname(__DIR__, 2) . '/fixture.sqlite');
$stmt = $db->prepare('UPDATE users SET permissions = ? WHERE username = ?');
$stmt->execute(['["slider","export"]', 'fixture-admin']);
