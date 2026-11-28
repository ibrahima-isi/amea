<?php
// Synthetic tools users; never shipped into the runtime image.
if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'test' || getenv('DB_NAME') !== 'amea_rehearsal') exit(1);
require '/opt/amea/vendor/autoload.php';
$conn = \Amea\Core\Container::db();
$insert = $conn->prepare('INSERT INTO users (username,password,last_name,first_name,email,role,permissions) VALUES (?,?,?,?,?,?,?)');
foreach (['toolsadmin' => ['admin', ['slider','communications','consent','export','settings','users','upgrade']], 'toolsrestricted' => ['admin', []], 'toolsuser' => ['user', []]] as $name => [$role,$permissions]) {
    $insert->execute([$name,password_hash('Fixture-password-123!',PASSWORD_DEFAULT),'Tools','Synthetic',$name.'@example.invalid',$role,json_encode($permissions)]);
}
$conn->exec("UPDATE students SET email='synthetic-member@example.invalid' WHERE last_name='Legacy'");
$conn->exec("INSERT INTO communications (subject, body, status, total_recipients, failed_count)
    VALUES ('Synthetic failure diagnostics', '', 'COMPLETED_WITH_ERRORS', 26, 26)");
$campaign = (int) $conn->lastInsertId();
$failed = $conn->prepare("INSERT INTO communication_recipients
    (communication_id, email, status, attempts, last_error, next_attempt_at)
    VALUES (?, ?, 'FAILED', 5, ?, CURRENT_TIMESTAMP)");
for ($i = 1; $i <= 26; $i++) {
    $failed->execute([$campaign, "diagnostic-{$i}@example.invalid", "Synthetic SMTP diagnostic {$i}"]);
}
echo "Synthetic tools users ready\n";
