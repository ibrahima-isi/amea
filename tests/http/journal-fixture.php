<?php
// CLI-only accounts in the disposable HTTP copy; never use a configured database.
$root = dirname(__DIR__, 2);
if (PHP_SAPI !== 'cli' || !str_starts_with(basename($root), 'amea-http-') || !is_file($root . '/fixture.sqlite')) {
    http_response_code(404);
    exit(1);
}

$db = new PDO('sqlite:' . $root . '/fixture.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$insert = $db->prepare('INSERT INTO users (username,password,last_name,first_name,email,role,permissions) VALUES (?,?,?,?,?,?,?)');
foreach (['journal-admin' => ['admin', ['journal']], 'journal-denied' => ['admin', []], 'journal-reader' => ['user', ['journal']]] as $name => [$role, $permissions]) {
    $insert->execute([$name, password_hash('Fixture-password-123!', PASSWORD_DEFAULT), 'Journal', 'Synthetic', $name . '@example.invalid', $role, json_encode($permissions)]);
}
