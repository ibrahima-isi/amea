<?php
/**
 * tests/test_oop_unit.php
 *
 * Unit tests for all Amea\ src/ classes.
 * Uses SQLite in-memory — no MySQL required. Runs in CI with php-version 8.0 + pdo_sqlite.
 *
 * IMPORTANT: Session tests manipulate $_SESSION directly. Tests that exercise
 * CsrfGuard/Flash/Session create fresh objects to reset any static state.
 */

require_once __DIR__ . '/../vendor/autoload.php';

if (PHP_SAPI === 'cli' && ob_get_level() === 0) {
    ob_start();
}

// ─── Tiny test runner ─────────────────────────────────────────────────────────
$passed = 0;
$failed = 0;

function expect(string $name, bool $result): void
{
    global $passed, $failed;
    if ($result) {
        echo "\033[32m  ✓ {$name}\033[0m\n";
        $passed++;
    } else {
        echo "\033[31m  ✗ {$name}\033[0m\n";
        $failed++;
    }
}

// ─── SQLite in-memory DB setup ────────────────────────────────────────────────
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$db->exec("
    CREATE TABLE users (
        id_user INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        email TEXT NOT NULL DEFAULT '',
        nom TEXT NOT NULL DEFAULT '',
        prenom TEXT NOT NULL DEFAULT '',
        password TEXT NOT NULL DEFAULT '',
        role TEXT NOT NULL DEFAULT 'user',
        permissions TEXT DEFAULT NULL,
        est_actif INTEGER NOT NULL DEFAULT 1,
        date_creation TEXT NOT NULL DEFAULT (datetime('now')),
        derniere_connexion TEXT DEFAULT NULL
    );

    CREATE TABLE personnes (
        id_personne INTEGER PRIMARY KEY AUTOINCREMENT,
        nom TEXT NOT NULL DEFAULT '',
        prenom TEXT NOT NULL DEFAULT '',
        sexe TEXT NOT NULL DEFAULT '',
        date_naissance TEXT NOT NULL DEFAULT '',
        lieu_residence TEXT NOT NULL DEFAULT '',
        etablissement TEXT NOT NULL DEFAULT '',
        statut TEXT NOT NULL DEFAULT '',
        domaine_etudes TEXT NOT NULL DEFAULT '',
        niveau_etudes TEXT NOT NULL DEFAULT '',
        telephone TEXT NOT NULL DEFAULT '',
        email TEXT NOT NULL DEFAULT '',
        annee_arrivee INTEGER DEFAULT NULL,
        type_logement TEXT NOT NULL DEFAULT '',
        precision_logement TEXT DEFAULT NULL,
        projet_apres_formation TEXT DEFAULT NULL,
        identite TEXT DEFAULT NULL,
        nationalites TEXT DEFAULT NULL,
        cv_path TEXT DEFAULT NULL,
        date_enregistrement TEXT NOT NULL DEFAULT (datetime('now')),
        date_diplomation TEXT DEFAULT NULL,
        is_locked INTEGER NOT NULL DEFAULT 0,
        consent_privacy INTEGER NOT NULL DEFAULT 0
    );

    CREATE TABLE settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        setting_key TEXT NOT NULL UNIQUE,
        setting_value TEXT NOT NULL DEFAULT ''
    );
");

// Start session for tests that need it
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ─── 1. Model\User ────────────────────────────────────────────────────────────
echo "\nModel\\User\n";

use Amea\Model\User;

$row = [
    'id_user'           => 42,
    'username'          => 'jdoe',
    'email'             => 'j@doe.com',
    'nom'               => 'Doe',
    'prenom'            => 'John',
    'role'              => 'admin',
    'est_actif'         => 1,
    'permissions'       => json_encode(['students', 'export']),
    'date_creation'     => '2024-01-01',
    'derniere_connexion' => null,
    'password'          => 'hash',
];
$u = User::fromRow($row);

expect('getId() returns int 42',                   $u->getId() === 42);
expect('getUsername() correct',                    $u->getUsername() === 'jdoe');
expect('getEmail() correct',                       $u->getEmail() === 'j@doe.com');
expect('getFullName() = "John Doe"',               $u->getFullName() === 'John Doe');
expect('getRole() = admin',                        $u->getRole() === 'admin');
expect('isActif() true',                           $u->isActif() === true);
expect('isSuperAdmin() false for id=42',           $u->isSuperAdmin() === false);
expect('getPassword() returns hash',               $u->getPassword() === 'hash');
expect('getPermissions() returns decoded array',   $u->getPermissions() === ['students', 'export']);
expect('hasPermission(students) true',             $u->hasPermission('students'));
expect('hasPermission(users) false',               !$u->hasPermission('users'));

$super = User::fromRow(array_merge($row, ['id_user' => 1, 'permissions' => null]));
expect('isSuperAdmin() true for id=1',             $super->isSuperAdmin());
expect('hasPermission() always true for id=1',     $super->hasPermission('anything'));
expect('getPermissions() returns [] for null JSON', $super->getPermissions() === []);

$malformed = User::fromRow(array_merge($row, ['permissions' => 'not-json']));
expect('getPermissions() returns [] for bad JSON', $malformed->getPermissions() === []);

// ─── 2. Model\Student ────────────────────────────────────────────────────────
echo "\nModel\\Student\n";

use Amea\Model\Student;

$sRow = [
    'id_personne'          => 7,
    'nom'                  => 'Diallo',
    'prenom'               => 'Ibrahima',
    'sexe'                 => 'Masculin',
    'date_naissance'       => '2000-06-15',
    'lieu_residence'       => 'Dakar',
    'etablissement'        => 'UCAD',
    'statut'               => 'En cours',
    'domaine_etudes'       => 'Informatique',
    'niveau_etudes'        => 'Master 1',
    'telephone'            => '771234567',
    'email'                => 'i@d.sn',
    'annee_arrivee'        => 2022,
    'type_logement'        => 'Cité U',
    'precision_logement'   => null,
    'projet_apres_formation' => 'Doctorat',
    'identite'             => 'uploads/students/photo.jpg',
    'nationalites'         => json_encode(['Sénégalaise']),
    'cv_path'              => null,
    'date_enregistrement'  => '2024-03-01',
    'date_diplomation'     => null,
    'is_locked'            => 0,
    'consent_privacy'      => 1,
];
$s = Student::fromRow($sRow);

expect('getId() returns 7',                        $s->getId() === 7);
expect('getFullName() = "Ibrahima Diallo"',        $s->getFullName() === 'Ibrahima Diallo');
expect('getNationalites() decoded',                $s->getNationalites() === ['Sénégalaise']);
expect('isDiplome() false for En cours',           !$s->isDiplome());
expect('isLocked() false',                         !$s->isLocked());
expect('hasConsentPrivacy() true',                 $s->hasConsentPrivacy());
expect('getIdentitePath() correct',               $s->getIdentitePath() === 'uploads/students/photo.jpg');

$diplome = Student::fromRow(array_merge($sRow, ['statut' => 'Diplômé(e)']));
expect('isDiplome() true for Diplômé(e)',          $diplome->isDiplome());

$noNat = Student::fromRow(array_merge($sRow, ['nationalites' => null]));
expect('getNationalites() returns [] for null',    $noNat->getNationalites() === []);

expect('getAge() is a non-negative int',           $s->getAge() >= 0);

// ─── 3. Model\Setting ─────────────────────────────────────────────────────────
echo "\nModel\\Setting\n";

use Amea\Model\Setting;

$setting = Setting::fromRow(['setting_key' => 'contact_email', 'setting_value' => 'admin@test.sn']);
expect('getKey() returns contact_email',           $setting->getKey() === 'contact_email');
expect('getValue() returns admin@test.sn',         $setting->getValue() === 'admin@test.sn');

// ─── 4. Repository\UserRepository ─────────────────────────────────────────────
echo "\nRepository\\UserRepository\n";

use Amea\Repository\UserRepository;

$userRepo = new UserRepository($db);

// Reserve id=1 for the Super Admin so test users get id≥2.
// UserRepository tests must not get id=1 or User::hasPermission() always returns true.
$db->exec("INSERT INTO users (id_user, username, email, nom, prenom, password, role, permissions, est_actif)
           VALUES (1, '_superadmin', 'sa@test.internal', 'SA', 'Test', 'x', 'admin', NULL, 1)");

// Insert via repository
$id1 = $userRepo->save([
    'username'    => 'alice',
    'email'       => 'alice@test.sn',
    'nom'         => 'Alice',
    'prenom'      => 'Test',
    'password'    => password_hash('secret', PASSWORD_DEFAULT),
    'role'        => 'admin',
    'permissions' => json_encode(['students', 'users']),
    'est_actif'   => 1,
]);
$id2 = $userRepo->save([
    'username'    => 'bob',
    'email'       => 'bob@test.sn',
    'nom'         => 'Bob',
    'prenom'      => 'Test',
    'password'    => password_hash('pass', PASSWORD_DEFAULT),
    'role'        => 'user',
    'permissions' => null,
    'est_actif'   => 0,
]);

expect('save() returns valid int ID',              $id1 > 0);
expect('save() returns incrementing IDs',          $id2 > $id1);

$found = $userRepo->findById($id1);
expect('findById() returns User',                  $found instanceof \Amea\Model\User);
expect('findById() username is alice',             $found->getUsername() === 'alice');
expect('findById() permissions decoded',           $found->hasPermission('students'));

expect('findById() returns null for missing',      $userRepo->findById(9999) === null);

$byName = $userRepo->findByUsername('alice');
expect('findByUsername() returns alice',           $byName?->getUsername() === 'alice');
expect('findByUsername() null for unknown',        $userRepo->findByUsername('nobody') === null);

$byEmail = $userRepo->findByEmail('alice@test.sn');
expect('findByEmail() returns alice',              $byEmail?->getEmail() === 'alice@test.sn');

// bob is inactive (est_actif=0) — findActiveByUsername should return null
expect('findActiveByUsername() null for inactive', $userRepo->findActiveByUsername('bob') === null);
expect('findActiveByUsername() returns active',    $userRepo->findActiveByUsername('alice') !== null);

$all = $userRepo->findAll();
expect('findAll() returns 3 users',                count($all) === 3); // includes pre-inserted super admin

$userRepo->update($id1, [
    'username'    => 'alice',
    'email'       => 'alice@test.sn',
    'role'        => 'admin',
    'permissions' => json_encode(['settings']),
    'est_actif'   => 1,
]);
$updated = $userRepo->findById($id1);
expect('update() changes permissions',             $updated->hasPermission('settings'));
expect('update() removes old permission',          !$updated->hasPermission('students'));

expect('existsByUsername() true for alice',        $userRepo->existsByUsername('alice'));
expect('existsByUsername() false for ghost',       !$userRepo->existsByUsername('ghost'));
expect('existsByUsername() false when excluded',   !$userRepo->existsByUsername('alice', $id1));

expect('existsByEmail() true for alice@test.sn',  $userRepo->existsByEmail('alice@test.sn'));
expect('existsByEmail() false when excluded',      !$userRepo->existsByEmail('alice@test.sn', $id1));

$userRepo->updateLastLogin($id1);
$afterLogin = $userRepo->findById($id1);
expect('updateLastLogin() sets derniere_connexion', $afterLogin->getDerniereConnexion() !== null);

$userRepo->delete($id2);
expect('delete() removes user',                    $userRepo->findById($id2) === null);
expect('findAll() now returns 2 users',            count($userRepo->findAll()) === 2); // super admin + alice

// ─── 5. Repository\StudentRepository ──────────────────────────────────────────
echo "\nRepository\\StudentRepository\n";

use Amea\Repository\StudentRepository;

$stuRepo = new StudentRepository($db);

$baseStudent = [
    'nom'                  => 'Ndiaye',
    'prenom'               => 'Fatou',
    'sexe'                 => 'Féminin',
    'date_naissance'       => '2001-03-10',
    'lieu_residence'       => 'Saint-Louis',
    'etablissement'        => 'UGB',
    'statut'               => 'En cours',
    'domaine_etudes'       => 'Droit',
    'niveau_etudes'        => 'Licence 3',
    'telephone'            => '771110001',
    'email'                => 'fatou@test.sn',
    'annee_arrivee'        => 2021,
    'type_logement'        => 'Famille',
    'precision_logement'   => null,
    'projet_apres_formation' => null,
    'identite'             => null,
    'nationalites'         => json_encode(['Sénégalaise']),
    'cv_path'              => null,
    'consent_privacy'      => 0,
];

$sid1 = $stuRepo->save($baseStudent);
$sid2 = $stuRepo->save(array_merge($baseStudent, [
    'nom'       => 'Fall',
    'prenom'    => 'Moussa',
    'sexe'      => 'Masculin',
    'telephone' => '771110002',
    'email'     => 'moussa@test.sn',
    'statut'    => 'Diplômé(e)',
]));

expect('save() returns valid ID',                  $sid1 > 0);

$stu = $stuRepo->findById($sid1);
expect('findById() returns Student',               $stu instanceof \Amea\Model\Student);
expect('findById() nom is Ndiaye',                 $stu->getNom() === 'Ndiaye');

expect('findById() null for missing',              $stuRepo->findById(9999) === null);

$all = $stuRepo->findAll();
expect('findAll() returns 2 students',             count($all) === 2);

expect('countAll() returns 2',                     $stuRepo->countAll() === 2);
expect('countAll() with filter returns 1',         $stuRepo->countAll(['statut' => 'Diplômé(e)']) === 1);

$page = $stuRepo->findPaginated(1, 1);
expect('findPaginated() items has 1 entry',        count($page['items']) === 1);
expect('findPaginated() total is 2',               $page['total'] === 2);

$page2 = $stuRepo->findPaginated(2, 1);
expect('findPaginated() page 2 works',             count($page2['items']) === 1);

$filtered = $stuRepo->findPaginated(1, 10, ['sexe' => 'Masculin']);
expect('findPaginated() filter by sexe',           count($filtered['items']) === 1);

$stuRepo->update($sid1, ['statut' => 'Diplômé(e)', 'date_diplomation' => '2025-06-01']);
$afterUpdate = $stuRepo->findById($sid1);
expect('update() changes statut',                  $afterUpdate->getStatut() === 'Diplômé(e)');
expect('update() sets date_diplomation',           $afterUpdate->getDateDiplomation() === '2025-06-01');

$threw = false;
try {
    $stuRepo->update($sid1, ['evil_column' => 'x']);
} catch (\InvalidArgumentException) {
    $threw = true;
}
expect('update() throws for invalid column',       $threw);

expect('existsByEmail() true',                     $stuRepo->existsByEmail('fatou@test.sn'));
expect('existsByEmail() false when excluded',      !$stuRepo->existsByEmail('fatou@test.sn', $sid1));
expect('existsByPhone() true',                     $stuRepo->existsByPhone('771110001'));
expect('existsByPhone() false when excluded',      !$stuRepo->existsByPhone('771110001', $sid1));

$stuRepo->delete($sid2);
expect('delete() removes student',                 $stuRepo->findById($sid2) === null);

// getStats — SQLite SUM with = comparisons returns string integers
$stats = $stuRepo->getStats();
expect('getStats() returns total >= 1',            (int)$stats['total'] >= 1);
expect('getStats() diplomes count correct',        (int)$stats['diplomes'] === 1); // only sid1 after update

// ─── 6. Repository\SettingRepository ─────────────────────────────────────────
echo "\nRepository\\SettingRepository\n";

use Amea\Repository\SettingRepository;

$settingRepo = new SettingRepository($db);
$db->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('contact_email','info@aeesgs.sn')");
$db->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('contact_phone','+221 33 000 0000')");
$db->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('association_name','AEESGS')");

$s = $settingRepo->findByKey('contact_email');
expect('findByKey() returns Setting',              $s instanceof \Amea\Model\Setting);
expect('findByKey() value correct',                $s->getValue() === 'info@aeesgs.sn');

expect('findByKey() null for missing',             $settingRepo->findByKey('nope') === null);
expect('getValue() returns value',                 $settingRepo->getValue('contact_phone') === '+221 33 000 0000');
expect('getValue() returns default for missing',   $settingRepo->getValue('missing', 'def') === 'def');

$replacements = $settingRepo->getFooterReplacements();
expect('getFooterReplacements() has contact_email key', isset($replacements['{{contact_email}}']));
expect('getFooterReplacements() has year key',     isset($replacements['{{year}}']));
expect('getFooterReplacements() year is current',  $replacements['{{year}}'] === (string)date('Y'));

// ─── 7. Service\UserService ───────────────────────────────────────────────────
echo "\nService\\UserService\n";

use Amea\Service\UserService;

$userSvc = new UserService($userRepo);

$clean = $userSvc->sanitizePermissions(['students', 'export', 'evil_module', 'users']);
expect('sanitizePermissions() removes evil_module', !in_array('evil_module', $clean));
expect('sanitizePermissions() keeps valid modules', in_array('students', $clean));
expect('sanitizePermissions() re-indexes array',   array_is_list($clean));

// buildPermissionsJson: non-admin role → null
$json = $userSvc->buildPermissionsJson('user', ['students'], false, false, '[]', 99);
expect('buildPermissionsJson() null for user role', $json === null);

// buildPermissionsJson: target is super admin (id=1) → all modules
$json = $userSvc->buildPermissionsJson('admin', [], false, false, '[]', 1);
$decoded = json_decode($json, true);
expect('buildPermissionsJson() all modules for id=1', count($decoded) === count(UserService::MODULES));

// buildPermissionsJson: self-edit without super admin session → preserve existing
$json = $userSvc->buildPermissionsJson('admin', ['users', 'evil'], true, false, '["students"]', 5);
expect('buildPermissionsJson() returns existingJson on self-edit', $json === '["students"]');

// buildPermissionsJson: super admin session editing others → whitelist applied
$json = $userSvc->buildPermissionsJson('admin', ['students', 'evil', 'export'], false, true, '[]', 5);
$decoded = json_decode($json, true);
expect('buildPermissionsJson() whitelists for super-admin edit', !in_array('evil', $decoded));
expect('buildPermissionsJson() keeps valid modules',              in_array('students', $decoded));

// createUser() round-trip
$newId = $userSvc->createUser([
    'username'    => 'charlie',
    'email'       => 'charlie@test.sn',
    'nom'         => 'Charlie',
    'prenom'      => 'Test',
    'password'    => 'password123',
    'role'        => 'admin',
    'permissions' => ['documents', 'evil'],
    'est_actif'   => 1,
]);
$charlie = $userRepo->findById($newId);
expect('createUser() saves to DB',                 $charlie !== null);
expect('createUser() hashes password',             password_verify('password123', $charlie->getPassword()));
expect('createUser() whitelists permissions',      $charlie->hasPermission('documents'));
expect('createUser() rejects evil permission',     !$charlie->hasPermission('evil'));

// updateUser() changes role to user → permissions become null
$userSvc->updateUser($newId, [
    'username'    => 'charlie',
    'email'       => 'charlie@test.sn',
    'role'        => 'user',
    'permissions' => ['documents'],
    'est_actif'   => 1,
], false, false);
$charlie2 = $userRepo->findById($newId);
expect('updateUser() demote to user → no permissions', !$charlie2->hasPermission('documents'));

// ─── 8. Service\AuthService ──────────────────────────────────────────────────
echo "\nService\\AuthService\n";

use Amea\Service\AuthService;
use Amea\Core\{Session, Flash};

// Use a clean session for auth tests
$_SESSION = [];
$sess  = new Session();
$flash = new Flash($sess);
$auth  = new AuthService($userRepo, $sess, $flash);

// attempt() with wrong password
$ok = $auth->attempt('alice', 'wrongpass');
expect('attempt() false for wrong password',       $ok === false);
expect('attempt() does not populate session',      !isset($_SESSION['user_id']));

// attempt() with inactive user
$ok = $auth->attempt('bob', 'pass'); // bob is deleted already — null from DB = false
expect('attempt() false for non-existent user',    $ok === false);

// Create a fresh active user for login test
$loginId = $userRepo->save([
    'username'    => 'diana',
    'email'       => 'diana@test.sn',
    'nom'         => 'Diana',
    'prenom'      => 'Test',
    'password'    => password_hash('correct', PASSWORD_DEFAULT),
    'role'        => 'admin',
    'permissions' => json_encode(['students']),
    'est_actif'   => 1,
]);

$ok = $auth->attempt('diana', 'correct');
expect('attempt() true for valid credentials',     $ok === true);
expect('attempt() sets user_id in session',        $_SESSION['user_id'] === $loginId);
expect('attempt() sets role in session',           $_SESSION['role'] === 'admin');

// hasPermission()
expect('hasPermission() true for granted module',  $auth->hasPermission('students'));
expect('hasPermission() false for missing module', !$auth->hasPermission('users'));

// Super Admin bypass — simulate session for id=1
$_SESSION['user_id'] = 1;
$_SESSION['role']    = 'admin';
$sess2 = new Session();
$auth2 = new AuthService($userRepo, $sess2, new Flash($sess2));
expect('hasPermission() true for super admin (id=1)', $auth2->hasPermission('anything'));

// logout() clears session
$_SESSION['user_id'] = $loginId;
$auth->logout();
expect('logout() destroys session', session_status() === PHP_SESSION_NONE || !isset($_SESSION['user_id']));

// ─── 9. Core\CsrfGuard ────────────────────────────────────────────────────────
echo "\nCore\\CsrfGuard\n";

use Amea\Core\CsrfGuard;

if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION = [];
$csrfSess  = new Session();
$guard     = new CsrfGuard($csrfSess);

$token = $guard->getToken();
expect('getToken() returns 64-char hex string',    strlen($token) === 64 && ctype_xdigit($token));
expect('getToken() same token on second call',     $guard->getToken() === $token);
expect('verify() true for correct token',          $guard->verify($token));
expect('verify() false for empty string',          !$guard->verify(''));
expect('verify() false for wrong token',           !$guard->verify('wrong'));

$guard->regenerate();
$newToken = $guard->getToken();
expect('regenerate() produces new token',          $newToken !== $token);
expect('verify() old token rejected after regen',  !$guard->verify($token));

// ─── 10. Core\Flash ──────────────────────────────────────────────────────────
echo "\nCore\\Flash\n";

use Amea\Core\Flash as FlashClass;

$_SESSION = [];
$flashSess = new Session();
$fl        = new FlashClass($flashSess);

expect('get() returns null when empty',            $fl->get() === null);

$fl->set('success', 'Saved!');
$msg = $fl->get();
expect('get() returns correct type',               $msg['type'] === 'success');
expect('get() returns correct message',            $msg['message'] === 'Saved!');
expect('get() clears after read',                  $fl->get() === null);

$fl->set('error', 'Oops');
expect('cssClass(success) = alert-success',        $fl->cssClass('success') === 'alert-success');
expect('cssClass(error) = alert-danger',           $fl->cssClass('error') === 'alert-danger');
expect('cssClass(warning) = alert-warning',        $fl->cssClass('warning') === 'alert-warning');
expect('cssClass(unknown) = alert-secondary',      $fl->cssClass('unknown') === 'alert-secondary');

// ─── 11. Core\Session ────────────────────────────────────────────────────────
echo "\nCore\\Session\n";

if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION = [];
$sess3 = new Session();

$sess3->set('foo', 'bar');
expect('set/get round-trip',                       $sess3->get('foo') === 'bar');
expect('get() default for missing key',            $sess3->get('missing', 'def') === 'def');
expect('has() true for set key',                   $sess3->has('foo'));
expect('has() false for missing key',              !$sess3->has('missing'));

$sess3->remove('foo');
expect('remove() deletes key',                     !$sess3->has('foo'));

expect('userId() returns 0 when not set',          $sess3->userId() === 0);
expect('isLoggedIn() false when user_id absent',   !$sess3->isLoggedIn());

$sess3->set('user_id', 5);
expect('userId() returns 5',                       $sess3->userId() === 5);
expect('isLoggedIn() true when user_id set',       $sess3->isLoggedIn());

expect('role() returns empty string when absent',  $sess3->role() === '');
$sess3->set('role', 'admin');
expect('role() returns admin',                     $sess3->role() === 'admin');

// ─── 12. Core\TemplateEngine ─────────────────────────────────────────────────
echo "\nCore\\TemplateEngine\n";

use Amea\Core\TemplateEngine;

// render() with empty path returns ''
$tpl = new TemplateEngine(__DIR__ . '/..');
expect('render() with empty path returns ""',      $tpl->render('') === '');

// versionAssets() appends ?v= to local CSS
$html    = '<link href="assets/css/app.css">';
$result  = TemplateEngine::versionAssets($html, __DIR__ . '/..');
$hasVersion = (bool)preg_match('/assets\/css\/app\.css\?v=\d+/', $result);
expect('versionAssets() appends ?v= to CSS',       $hasVersion);

// versionAssets() skips CDN URLs
$cdn  = '<link href="https://cdn.example.com/style.css">';
expect('versionAssets() skips CDN URLs',           TemplateEngine::versionAssets($cdn, '/') === $cdn);

// versionAssets() skips protocol-relative URLs
$proto = '<script src="//cdn.example.com/lib.js"></script>';
expect('versionAssets() skips // URLs',            TemplateEngine::versionAssets($proto, '/') === $proto);

// render() with a real template and vars
$tmpTpl = sys_get_temp_dir() . '/amea_test_tpl_' . uniqid() . '.html';
file_put_contents($tmpTpl, '<p>Hello {{name}}! Role: {{role}}</p>');
$tplDir = dirname($tmpTpl);
$tplFile = basename($tmpTpl);
$tplEngine = new TemplateEngine($tplDir);
$out = $tplEngine->render($tplFile, ['name' => 'World', 'role' => 'admin']);
expect('render() substitutes {{name}}',            str_contains($out, 'Hello World!'));
expect('render() substitutes {{role}}',            str_contains($out, 'Role: admin'));
@unlink($tmpTpl);

// render() with missing template throws RuntimeException
$threw = false;
try {
    $tpl->render('nonexistent/file.html');
} catch (\RuntimeException) {
    $threw = true;
}
expect('render() throws RuntimeException for missing template', $threw);

// ─── 13. Core\FileUploader ────────────────────────────────────────────────────
echo "\nCore\\FileUploader\n";

use Amea\Core\FileUploader;

$uploader = new FileUploader(__DIR__ . '/..');

// safeDelete() returns false for null/empty path
expect('safeDelete() false for null',              !$uploader->safeDelete(null));
expect('safeDelete() false for empty string',      !$uploader->safeDelete(''));

// safeDelete() blocks path traversal
expect('safeDelete() blocks ../etc/passwd',        !$uploader->safeDelete('../../../etc/passwd'));
expect('safeDelete() blocks absolute path',        !$uploader->safeDelete('/etc/passwd'));

// safeDelete() returns false for non-existent file in uploads/
expect('safeDelete() false for non-existent file', !$uploader->safeDelete('uploads/nonexistent_xyz.jpg'));

// handle() returns error for UPLOAD_ERR_NO_FILE
$result = $uploader->handle(['error' => UPLOAD_ERR_NO_FILE], ['jpg'], 5 * 1024 * 1024, 'uploads/test');
expect('handle() returns success=false for no file',    !$result['success']);
expect('handle() message not null for no file',          $result['message'] !== null);

// handle() returns error for oversized file
$result = $uploader->handle(
    ['error' => UPLOAD_ERR_OK, 'size' => 100 * 1024 * 1024, 'name' => 'big.jpg', 'tmp_name' => ''],
    ['jpg'], 5 * 1024 * 1024, 'uploads/test'
);
expect('handle() returns success=false for oversized',  !$result['success']);

// handle() returns error for disallowed extension
$result = $uploader->handle(
    ['error' => UPLOAD_ERR_OK, 'size' => 100, 'name' => 'script.php', 'tmp_name' => ''],
    ['jpg', 'png'], 5 * 1024 * 1024, 'uploads/test'
);
expect('handle() rejects disallowed extension',         !$result['success']);

// ─── 14. Service\ExportService ────────────────────────────────────────────────
echo "\nService\\ExportService\n";

use Amea\Service\ExportService;

$exportSvc = new ExportService($db);
expect('cleanForCsv() null → empty string',        $exportSvc->cleanForCsv(null) === '');
expect('cleanForCsv() strips newlines',             $exportSvc->cleanForCsv("a\nb") === 'a b');
expect('cleanForCsv() strips tabs',                 $exportSvc->cleanForCsv("a\tb") === 'a b');
expect('cleanForCsv() escapes double quotes',       $exportSvc->cleanForCsv('say "hi"') === 'say ""hi""');

// ─── 15. Service\DocumentReconcileService ─────────────────────────────────────
echo "\nService\\DocumentReconcileService\n";

use Amea\Service\DocumentReconcileService;

$root = __DIR__ . '/..';
$svc  = new DocumentReconcileService(null, $root);

// classifyDocument: null path
$r = $svc->classifyDocument(null, []);
expect('classifyDocument() null path → status null', $r['status'] === 'null');

// classifyDocument: missing file
$r = $svc->classifyDocument('uploads/nonexistent_xyz.jpg', ['uploads/students']);
expect('classifyDocument() missing file → status missing', $r['status'] === 'missing');

// classifyDocument: existing file
$tmpFile = __DIR__ . '/../uploads/.htaccess'; // always present
if (is_file($tmpFile)) {
    $r = $svc->classifyDocument('uploads/.htaccess', []);
    expect('classifyDocument() existing file → status ok', $r['status'] === 'ok');
}

// findOrphanedFiles
$onDisk = ['uploads/a.jpg', 'uploads/b.jpg', 'uploads/c.jpg'];
$inDb   = ['uploads/a.jpg'];
$orphans = $svc->findOrphanedFiles($onDisk, $inDb);
expect('findOrphanedFiles() finds b.jpg and c.jpg', count($orphans) === 2);

// dbPathExists: non-existent file returns false
expect('dbPathExists() false for non-existent',    !$svc->dbPathExists('uploads/nonexistent_xyz.jpg'));

// ─── Summary ──────────────────────────────────────────────────────────────────
echo "\n";
$total = $passed + $failed;
echo "\033[" . ($failed > 0 ? '31' : '32') . "m  {$passed}/{$total} tests passed\033[0m\n\n";
exit($failed > 0 ? 1 : 0);
