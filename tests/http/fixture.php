<?php
// CLI-only synthetic fixture. Called only in the runner's disposable application copy.
if (PHP_SAPI !== 'cli' || !str_starts_with(basename(dirname(__DIR__, 2)), 'amea-http-')) { http_response_code(404); exit; }
require __DIR__ . '/../bootstrap.php';
$db = makeTestDb();
$db->exec("CREATE TABLE countries (id INTEGER PRIMARY KEY, name_fr TEXT);
INSERT INTO countries VALUES (1, 'Guinée');
CREATE TABLE student_country (student_id INTEGER, country_id INTEGER);
CREATE TABLE study_fields (id INTEGER PRIMARY KEY, name TEXT);
CREATE TABLE study_levels (id INTEGER PRIMARY KEY, name TEXT);
CREATE TABLE locations (id INTEGER PRIMARY KEY, region TEXT, name TEXT);
DELETE FROM users;");
$stmt = $db->prepare('INSERT INTO users (username,password,role,email) VALUES (?,?,?,?)');
$stmt->execute(['fixture-admin', password_hash('fixture-password', PASSWORD_DEFAULT), 'admin', 'staff@example.invalid']);
$db->exec("VACUUM INTO 'fixture.sqlite'");
