<?php
if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'test' || getenv('DB_NAME') !== 'amea_rehearsal') exit(1);
require '/opt/amea/config/database.php';
$insert = $conn->prepare('INSERT INTO users (username,password,last_name,first_name,email,role,permissions) VALUES (?,?,?,?,?,?,?)');
foreach (['journal-admin' => ['admin', ['journal']], 'journal-denied' => ['admin', []], 'journal-reader' => ['user', ['journal']]] as $name => [$role, $permissions]) {
    $insert->execute([$name, password_hash('Fixture-password-123!', PASSWORD_DEFAULT), 'Journal', 'Synthetic', $name . '@example.invalid', $role, json_encode($permissions)]);
}
echo "Synthetic journal users ready\n";
