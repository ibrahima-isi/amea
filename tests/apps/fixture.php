<?php
// CLI-only fixture; mounted/piped by the local runner, never shipped in either app.
if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'test' || getenv('DB_NAME') !== 'amea_rehearsal') {
    exit(1);
}
require '/opt/amea/config/database.php';
// This legacy MySQL fixture initializes the member tables from the exact new
// version sources. Canonical MariaDB runner/backup/recovery is qualified by
// doctrine-compose.sh; this file only provisions synthetic test fixtures.
if ($conn->query("SHOW TABLES LIKE 'member_file_lock'")->fetchColumn() === false) {
    $connection = \Doctrine\DBAL\DriverManager::getConnection([
        'driver' => 'pdo_mysql', 'host' => getenv('DB_HOST'), 'dbname' => getenv('DB_NAME'),
        'user' => getenv('DB_USER'), 'password' => getenv('DB_PASS'), 'charset' => 'utf8mb4',
    ]);
    foreach ([\Amea\Migration\Version\Version20261113000000::class,
        \Amea\Migration\Version\Version20261113000001::class] as $version) {
        $migration = new $version($connection, new \Psr\Log\NullLogger());
        $migration->up(new \Doctrine\DBAL\Schema\Schema());
        if ($migration->isTransactional()) { $connection->beginTransaction(); }
        foreach ($migration->getSql() as $query) {
            $connection->executeStatement($query->getStatement(), $query->getParameters(), $query->getTypes());
        }
        if ($migration->isTransactional()) { $connection->commit(); }
    }
}
$conn->exec('DELETE FROM password_resets; DELETE FROM users; DELETE FROM students');
$conn->exec("INSERT IGNORE INTO countries (code_iso,name_fr,name_en) VALUES ('GN','Guinée','Guinea')");
$insert = $conn->prepare('INSERT INTO users (username,password,last_name,first_name,email,role) VALUES (?,?,?,?,?,?)');
foreach (['valid', 'expired', 'revoked'] as $name) {
    $insert->execute([$name, password_hash('Fixture-password-123!', PASSWORD_DEFAULT), 'Fixture', 'Staff', $name . '@example.invalid', 'admin']);
    $conn->prepare('INSERT INTO password_resets (email,token,expires_at) VALUES (?,?,?)')->execute([
        $name . '@example.invalid', hash('sha256', str_repeat($name === 'valid' ? 'a' : ($name === 'expired' ? 'b' : 'c'), 64)),
        date('Y-m-d H:i:s', time() + ($name === 'expired' ? -60 : 300)),
    ]);
}
$conn->exec("INSERT INTO students (last_name,first_name,gender,status,housing_type) VALUES ('Legacy','Synthetic','Male','PUPIL','With family')");
ini_set('session.save_path', '/var/lib/amea/sessions');
session_name('AMEA_STAFF');
session_id('syntheticexpiredstaffsession');
session_start();
$_SESSION = ['user_id' => (int)$conn->query("SELECT id FROM users WHERE username='revoked'")->fetchColumn(), 'last_activity' => time() - 1801];
session_write_close();
foreach (['syntheticexpiredstafflink' => [time() - 1801, 1], 'syntheticrevokedstafflink' => [time(), 0]] as $id => [$activity, $version]) {
    session_id($id);
    session_start();
    $_SESSION = ['user_id' => (int)$conn->query("SELECT id FROM users WHERE username='revoked'")->fetchColumn(), 'session_version' => $version, 'last_activity' => $activity];
    session_write_close();
}
echo "Synthetic fixture ready\n";
@mkdir('/opt/amea/uploads/slider', 0770, true);
copy('/opt/amea/assets/img/presidents.jpg', '/opt/amea/uploads/slider/synthetic-public.jpg');
file_put_contents('/opt/amea/uploads/slider/synthetic-private', 'Synthetic non-image must stay private');
file_put_contents('/opt/amea/uploads/slider/synthetic.php.jpg', '<?php echo "must-not-execute";');
