<?php
// Synthetic token records only in the HTTP runner's disposable copy.
if (PHP_SAPI !== 'cli' || !str_starts_with(basename(dirname(__DIR__, 2)), 'amea-http-')) { exit(1); }
$db = new PDO('sqlite:' . dirname(__DIR__, 2) . '/fixture.sqlite');
$stmt = $db->prepare('INSERT INTO students (last_name,first_name,gender,status,phone,email,review_token,cgu_token,consent_privacy,kyc_status,kyc_notes,deleted_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
foreach (['a','b','c','d'] as $key) {
    $reviewToken = $key === 'd' ? null : str_repeat($key, 64);
    $cguToken = match ($key) {
        'a' => str_repeat('e', 64),
        'b' => str_repeat('f', 64),
        'c' => str_repeat('g', 64),
        default => null,
    };
    $consentPrivacy = $key === 'b' ? 1 : 0;
    $stmt->execute(['Fixture', 'Aminata <safe>', 'Féminin', 'ETUDIANT', '77123456' . ord($key) % 10, $key . '@example.invalid', $reviewToken, $cguToken, $consentPrivacy, $key === 'b' ? 'UNDER_REVIEW' : 'NEEDS_CLARIFICATION', 'Vérifier <identité>', $key === 'c' ? '2026-01-01' : null]);
}
// Extra active member for dedicated deletion-request token test
$stmt->execute(['FixtureDel', 'Kadiatou', 'Féminin', 'ETUDIANT', '771234569', 'del@example.invalid', null, str_repeat('h', 64), 0, 'APPROVED', null, null]);
// Extra active member reserved for the headless Chrome browser journey (tokens-browser.mjs)
$stmt->execute(['FixtureBrowser', 'Mariama', 'Féminin', 'ETUDIANT', '771234568', 'browser@example.invalid', null, str_repeat('i', 64), 0, 'APPROVED', null, null]);
