<?php
// Synthetic token records only in the HTTP runner's disposable copy.
if (PHP_SAPI !== 'cli' || !str_starts_with(basename(dirname(__DIR__, 2)), 'amea-http-')) { exit(1); }
$db = new PDO('sqlite:' . dirname(__DIR__, 2) . '/fixture.sqlite');
$stmt = $db->prepare('INSERT INTO students (last_name,first_name,gender,status,phone,email,review_token,kyc_status,kyc_notes,deleted_at) VALUES (?,?,?,?,?,?,?,?,?,?)');
foreach (['a','b','c','d'] as $key) {
    $stmt->execute(['Fixture', 'Aminata <safe>', 'Féminin', 'ETUDIANT', '77123456' . ord($key) % 10, $key . '@example.invalid', $key === 'd' ? null : str_repeat($key,64), $key === 'b' ? 'UNDER_REVIEW' : 'NEEDS_CLARIFICATION', 'Vérifier <identité>', $key === 'c' ? '2026-01-01' : null]);
}
