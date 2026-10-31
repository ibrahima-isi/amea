<?php
// A synthetic pending session in the pre-refactor registration format.
if (PHP_SAPI !== 'cli' || !str_starts_with(basename(dirname(__DIR__, 2)), 'amea-http-')) {
    exit(1);
}
$root = dirname(__DIR__, 2);
mkdir($root . '/uploads/students/pending', 0775, true);
file_put_contents($root . '/uploads/students/pending/previous.pdf', "%PDF-1.4\nPrevious synthetic identity\n%%EOF\n");
$db = new PDO('sqlite:' . $root . '/fixture.sqlite');
$db->exec("INSERT INTO countries VALUES (2, 'Sénégal')");
session_save_path($root . '/sessions');
session_id('amea-previous-registration');
session_start();
$_SESSION['pending_registration'] = [
    'input' => ['last_name' => 'PreviousFixture', 'first_name' => 'Aminata'],
    'db_data' => [
        'last_name' => 'PreviousFixture', 'first_name' => 'Aminata',
        'gender' => 'Féminin', 'status' => 'ETUDIANT',
        'phone' => '771234560', 'email' => 'previous@example.invalid',
        'birth_date' => null, 'residence' => 'Dakar', 'institution' => 'Université Fixture',
        'study_field' => 'Biologie', 'study_level' => 'Licence', 'arrival_year' => null,
        'housing_type' => null, 'housing_details' => null, 'post_training_project' => null,
        'identity_document' => 'uploads/students/pending/previous.pdf', 'cv_path' => null,
        'nationalities' => '["Guinée","Sénégal"]', 'consent_privacy' => 1,
    ],
    'valid_pays_ids' => [1, 2],
];
session_write_close();
