<?php
// Schema compatibility is qualified only against this disposable database.
if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'test' || getenv('DB_NAME') !== 'amea_rehearsal') {
    exit(1);
}
require '/opt/amea/config/database.php';
if (($argv[1] ?? '') === 'before') {
    $conn->exec("ALTER TABLE students MODIFY housing_type ENUM('With family','Shared housing','University residence','Other') COLLATE utf8mb4_unicode_ci NULL DEFAULT 'With family' COMMENT 'legacy fixture'");
    exit;
}
$column = $conn->query("SHOW FULL COLUMNS FROM students LIKE 'housing_type'")->fetch(PDO::FETCH_ASSOC);
$legacy = $conn->query("SELECT gender,status,housing_type FROM students WHERE last_name='Legacy' AND first_name='Synthetic'")->fetch(PDO::FETCH_ASSOC);
$checks = [
    'migration preserves legacy records' => $legacy === ['gender' => 'Male', 'status' => 'PUPIL', 'housing_type' => 'With family'],
    'migration preserves existing column default' => $column['Default'] === 'With family',
    'migration preserves existing column collation' => $column['Collation'] === 'utf8mb4_unicode_ci',
    'migration preserves existing column comment' => $column['Comment'] === 'legacy fixture',
];
foreach ($checks as $message => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "PASS: {$message}\n";
}
