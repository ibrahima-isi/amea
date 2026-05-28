<?php
/**
 * Regression tests for the simplified registration flow.
 *
 * Run: php tests/test_registration_without_id_number.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../functions/utility-functions.php';

use Amea\Core\FileUploader;
use Amea\Repository\StudentRepository;
use Amea\Service\StudentService;

$passed = 0;
$failed = 0;

function expect(string $label, bool $result): void
{
    global $passed, $failed;

    if ($result) {
        echo "\033[32m  ✓ {$label}\033[0m\n";
        $passed++;
        return;
    }

    echo "\033[31m  ✗ {$label}\033[0m\n";
    $failed++;
}

class RegistrationTestUploader extends FileUploader
{
    public array $calls = [];

    public function __construct()
    {
        parent::__construct(dirname(__DIR__));
    }

    public function handle(array $fileInput, array $allowedExtensions, int $maxBytes, string $uploadDir): array
    {
        $this->calls[] = [
            'file' => $fileInput,
            'allowedExtensions' => $allowedExtensions,
            'maxBytes' => $maxBytes,
            'uploadDir' => $uploadDir,
        ];

        return [
            'success' => true,
            'filepath' => 'uploads/students/test-id-document.pdf',
            'message' => null,
        ];
    }
}

function makeRegistrationRepository(): StudentRepository
{
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $db->exec("
        CREATE TABLE personnes (
            id_personne INTEGER PRIMARY KEY AUTOINCREMENT,
            nom TEXT,
            prenom TEXT,
            telephone TEXT,
            email TEXT,
            identite TEXT
        );

        CREATE TABLE pays (
            id_pays INTEGER PRIMARY KEY AUTOINCREMENT,
            nom_fr TEXT NOT NULL UNIQUE
        );

        INSERT INTO pays (nom_fr) VALUES ('Guinée');
    ");

    return new StudentRepository($db);
}

echo "\nRegistration without typed ID number\n";

$uploader = new RegistrationTestUploader();
$service = new StudentService(makeRegistrationRepository(), $uploader);

$result = $service->sanitizeAndValidate([
    'nom' => 'Diallo',
    'prenom' => 'Aminata',
    'sexe' => 'Féminin',
    'telephone' => '770000001',
    'email' => 'aminata.registration@example.test',
    'nationalites' => json_encode([['value' => 'Guinée']], JSON_UNESCAPED_UNICODE),
    'consent_privacy' => '1',
], [
    'photo' => [
        'name' => 'identity.pdf',
        'tmp_name' => '/tmp/identity.pdf',
        'size' => 1000,
        'error' => UPLOAD_ERR_OK,
    ],
]);

expect('registration accepts missing typed ID number', !isset($result['errors']['numero_identite']));
expect('registration does not keep numero_identite in sanitized input', !array_key_exists('numero_identite', $result['input']));
expect('registration does not send numero_identite to persistence', !array_key_exists('numero_identite', $result['db_data']));
expect('registration accepts missing date of birth', !isset($result['errors']['date_naissance']));
expect('registration stores missing date of birth as null', $result['db_data']['date_naissance'] === null);
expect('ID document upload is still preserved', $result['db_data']['identite'] === 'uploads/students/test-id-document.pdf');
expect('ID document upload still targets uploads/students', ($uploader->calls[0]['uploadDir'] ?? '') === 'uploads/students');
expect('ID document upload still allows PDFs', in_array('pdf', $uploader->calls[0]['allowedExtensions'] ?? [], true));
expect('registration requires status', isset($result['errors']['statut']) && $result['errors']['statut'] === 'Le statut est requis.');

$validStatusResult = $service->sanitizeAndValidate([
    'nom' => 'Barry',
    'prenom' => 'Mamadou',
    'sexe' => 'Masculin',
    'statut' => 'ETUDIANT',
    'telephone' => '770000002',
    'email' => 'mamadou.registration@example.test',
    'nationalites' => json_encode([['value' => 'Guinée']], JSON_UNESCAPED_UNICODE),
    'consent_privacy' => '1',
], []);
expect('registration accepts uppercase non-accent status value', !isset($validStatusResult['errors']['statut']));
expect('registration persists uppercase non-accent status value', $validStatusResult['db_data']['statut'] === 'ETUDIANT');

echo "\nRegistration UI and schema\n";

$root = dirname(__DIR__);
$registerTwig = file_get_contents($root . '/templates/register.html.twig');
$registerLegacy = file_get_contents($root . '/templates/register.html');
$registrationEdit = file_get_contents($root . '/templates/registration-edit.html');
$adminEditStudent = file_get_contents($root . '/templates/admin/pages/edit-student.html');
$kycDetail = file_get_contents($root . '/templates/admin/pages/kyc-detail.html.twig');
$studentDetailsPhp = file_get_contents($root . '/student-details.php');
$registerJs = file_get_contents($root . '/assets/js/register.js');
$validationJs = file_get_contents($root . '/assets/js/form-validation-alerts.js');
$studentServicePhp = file_get_contents($root . '/src/Service/StudentService.php');
$editStudentPhp = file_get_contents($root . '/edit-student.php');
$viewPhp = file_get_contents($root . '/src/Core/View.php');
$initSql = file_get_contents($root . '/database/init.sql');
$schemaSql = file_get_contents($root . '/schema.sql');
$migrationPath = $root . '/migrations/migration_remove_registration_id_number.php';
$migration = is_file($migrationPath) ? file_get_contents($migrationPath) : '';
$dobMigrationPath = $root . '/migrations/migration_make_birth_date_optional.php';
$dobMigration = is_file($dobMigrationPath) ? file_get_contents($dobMigrationPath) : '';
$statusMigrationPath = $root . '/migrations/migration_make_registration_status_required.php';
$statusMigration = is_file($statusMigrationPath) ? file_get_contents($statusMigrationPath) : '';

foreach ([
    'templates/register.html.twig' => $registerTwig,
    'templates/register.html' => $registerLegacy,
] as $path => $contents) {
    expect("{$path} removes typed ID number field", $contents !== false && !str_contains($contents, 'name="numero_identite"'));
    expect("{$path} removes old typed ID label", $contents !== false && !str_contains($contents, 'Identité / Passeport'));
    expect("{$path} keeps ID file upload", $contents !== false && str_contains($contents, 'name="photo"'));
    expect("{$path} labels upload as an ID document", $contents !== false && str_contains($contents, "Pièce d'identité"));
    expect("{$path} keeps status in personal information section", $contents !== false && strpos($contents, 'name="statut"') !== false && strpos($contents, 'name="statut"') < strpos($contents, 'contactResidenceSection'));
    expect("{$path} marks status required in the UI", $contents !== false && preg_match('/<label for="statut"[\s\S]{0,120}text-danger[\s\S]{0,20}\*[\s\S]{0,260}<select[\s\S]{0,180}name="statut"[\s\S]{0,120}\brequired\b/u', $contents));
}

foreach ([
    'templates/register.html.twig' => $registerTwig,
    'templates/register.html' => $registerLegacy,
    'templates/registration-edit.html' => $registrationEdit,
    'templates/admin/pages/edit-student.html' => $adminEditStudent,
] as $path => $contents) {
    expect("{$path} keeps date of birth field", $contents !== false && str_contains($contents, 'name="date_naissance"'));
    expect("{$path} does not require date of birth input", $contents !== false && !preg_match('/name="date_naissance"[\s\S]{0,220}\srequired\b/', $contents));
    expect("{$path} does not mark date of birth label required", $contents !== false && !preg_match('/Date de naissance[\s\S]{0,80}text-danger[\s\S]{0,20}\*/u', $contents));
}

expect('register.js has no typed ID validation', $registerJs !== false && !str_contains($registerJs, 'numero_identite'));
expect('validation alert JS has no typed ID validation', $validationJs !== false && !str_contains($validationJs, 'numero_identite'));
expect('StudentService does not require date of birth', $studentServicePhp !== false && !preg_match("/'date_naissance'\\s*=>\\s*'La date de naissance est requise\\.'/", $studentServicePhp));
expect('StudentService requires status', $studentServicePhp !== false && preg_match("/'statut'\\s*=>\\s*'Le statut est requis\\.'/", $studentServicePhp));
expect('edit-student.php does not require date of birth', $editStudentPhp !== false && !preg_match("/'date_naissance'\\s*=>\\s*'La date de naissance est requise\\.'/", $editStudentPhp));
expect(
    'KYC detail does not show 0 ans for missing date of birth',
    $kycDetail !== false
        && str_contains($kycDetail, '{% if student.dateNaissance %}')
        && str_contains($kycDetail, 'Non renseignée')
);
expect(
    'student detail does not show 0 ans for missing date of birth',
    $studentDetailsPhp !== false
        && str_contains($studentDetailsPhp, "if (!empty(\$student['date_naissance']))")
        && str_contains($studentDetailsPhp, "'Non renseignée'")
);
expect(
    'Twig reloads changed templates outside dev so stale registration fields do not persist',
    $viewPhp !== false && preg_match("/'auto_reload'\\s*=>\\s*true/", $viewPhp) === 1
);
expect('database/init.sql no longer defines numero_identite', $initSql !== false && !preg_match('/\bnumero_identite\b/i', $initSql));
expect('schema.sql no longer adds numero_identite', $schemaSql !== false && !preg_match('/\bnumero_identite\b/i', $schemaSql));
expect('database/init.sql makes date_naissance nullable', $initSql !== false && preg_match('/`date_naissance`\s+date\s+DEFAULT NULL/i', $initSql));
expect('schema.sql makes date_naissance nullable', $schemaSql !== false && preg_match('/`date_naissance`\s+date\s+DEFAULT NULL/i', $schemaSql));
expect('database/init.sql makes statut required', $initSql !== false && preg_match('/`statut`\s+enum\([^)]+\)\s+NOT NULL/i', $initSql));
expect('schema.sql makes statut required', $schemaSql !== false && preg_match('/`statut`\s+enum\s*\([^)]+\)\s+NOT NULL/i', $schemaSql));
expect('database/init.sql uses uppercase non-accent statut enum', $initSql !== false && str_contains($initSql, "`statut` enum('ELEVE','ETUDIANT','STAGIAIRE') NOT NULL"));
expect('schema.sql uses uppercase non-accent statut enum', $schemaSql !== false && str_contains($schemaSql, "`statut`                 enum ('ELEVE','ETUDIANT','STAGIAIRE') NOT NULL"));
expect('register.js validates required status before submit', $registerJs !== false && str_contains($registerJs, 'validateRequiredStatus') && str_contains($registerJs, 'Veuillez sélectionner votre statut.'));
expect('migration exists to remove numero_identite', is_file($migrationPath));
expect(
    'migration drops numero_identite only when present',
    $migration !== false
        && str_contains($migration, 'personnesColumnExists')
        && preg_match('/DROP\s+COLUMN\s+`?numero_identite`?/i', $migration) === 1
);
expect('migration exists to make date_naissance optional', is_file($dobMigrationPath));
expect(
    'date_naissance migration makes column nullable when present',
    $dobMigration !== false
        && str_contains($dobMigration, 'personnesColumnExists')
        && preg_match('/MODIFY\s+COLUMN\s+`date_naissance`\s+DATE\s+NULL\s+DEFAULT\s+NULL/i', $dobMigration) === 1
);
expect('migration exists to make statut required', is_file($statusMigrationPath));
expect(
    'statut migration makes column not null when present',
    $statusMigration !== false
        && str_contains($statusMigration, 'personnesColumnExists')
        && preg_match('/MODIFY\s+COLUMN\s+`statut`\s+ENUM\([^)]+\)\s+NOT\s+NULL/i', $statusMigration) === 1
);
expect(
    'statut migration remaps legacy enum literals before tightening',
    $statusMigration !== false
        && str_contains($statusMigration, "WHERE `statut` = 'Élève'")
        && str_contains($statusMigration, "WHERE `statut` = 'Étudiant'")
        && str_contains($statusMigration, "WHERE `statut` = 'Stagiaire'")
);
expect('schema.sql does not re-null statut later', $schemaSql !== false && !preg_match('/MODIFY\s+statut\s+ENUM\([^)]+\)\s+NULL\s+DEFAULT\s+NULL/i', $schemaSql));

echo "\n";
$total = $passed + $failed;
if ($failed === 0) {
    echo "\033[32m  {$passed}/{$total} tests passed\033[0m\n\n";
    exit(0);
}

echo "\033[31m  {$passed}/{$total} tests passed\033[0m\n\n";
exit(1);
