<?php
/**
 * Integration tests — OOP layer (Amea\)
 *
 * Tests multiple classes wired together in realistic workflows.
 * Uses SQLite in-memory so no external DB is required.
 *
 * Run: php tests/test_oop_integration.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Amea\Core\{Flash, Session};
use Amea\Model\User;
use Amea\Repository\{StudentRepository, UserRepository};
use Amea\Service\{AuthService, UserService};

// ─── Session bootstrap (CLI-safe) ─────────────────────────────────────────────
// session_regenerate_id() emits a warning when called outside of a real HTTP context;
// suppress session-related warnings to keep test output clean.
set_error_handler(function (int $no, string $str): bool {
    // Silence session warnings only (not fatal errors).
    return str_contains($str, 'session_') || str_contains($str, 'Session');
});
session_save_path(sys_get_temp_dir());
@session_start();

// ─── Helpers ──────────────────────────────────────────────────────────────────
$pass = 0;
$fail = 0;

function expect(string $label, bool $result): void
{
    global $pass, $fail;
    if ($result) {
        echo "\033[32m  ✓ {$label}\033[0m\n";
        $pass++;
    } else {
        echo "\033[31m  ✗ {$label}\033[0m\n";
        $fail++;
    }
}

// ─── Shared SQLite DB ─────────────────────────────────────────────────────────
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$db->exec("
    CREATE TABLE users (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        username         TEXT    NOT NULL,
        email            TEXT    NOT NULL,
        last_name              TEXT    NOT NULL DEFAULT '',
        first_name           TEXT    NOT NULL DEFAULT '',
        password         TEXT    NOT NULL DEFAULT '',
        role             TEXT    NOT NULL DEFAULT 'user',
        permissions      TEXT,
        is_active        INTEGER NOT NULL DEFAULT 1,
        session_version  INTEGER NOT NULL DEFAULT 1,
        created_at    TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_login TEXT
    );

    CREATE TABLE students (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        last_name                  TEXT,
        first_name               TEXT,
        gender                 TEXT,
        birth_date       TEXT,
        residence       TEXT,
        institution        TEXT,
        status               TEXT,
        study_field       TEXT,
        study_level        TEXT,
        phone            TEXT,
        email                TEXT,
        arrival_year        INTEGER,
        housing_type        TEXT,
        housing_details   TEXT,
        post_training_project TEXT,
        identity_document             TEXT,
        nationalities         TEXT,
        cv_path              TEXT,
        registration_date  TEXT    DEFAULT CURRENT_TIMESTAMP,
        consent_privacy      INTEGER DEFAULT 0,
        is_locked            INTEGER DEFAULT 0,
        graduation_date     TEXT,
        kyc_status           TEXT    DEFAULT 'PENDING_CONFIRMATION',
        kyc_notes            TEXT,
        review_token         TEXT,
        kyc_updated_at       TEXT    DEFAULT CURRENT_TIMESTAMP
    );
");

// ─── 1. UserService + UserRepository ─────────────────────────────────────────
echo "\nUserService + UserRepository\n";

$userRepo    = new UserRepository($db);
$userService = new UserService($userRepo);

// Reserve id=1 for super admin
$db->exec("INSERT INTO users (id, username, email, last_name, first_name, password, role, permissions, is_active)
           VALUES (1, '_superadmin', 'sa@int.test', 'SA', 'Root', 'x', 'admin', NULL, 1)");

// createUser() — regular user
$regularId = $userService->createUser([
    'username'    => 'user_regular',
    'email'       => 'regular@test.sn',
    'last_name'         => 'Diallo',
    'first_name'      => 'Ibra',
    'password'    => 'secret123',
    'role'        => 'user',
    'permissions' => ['students'],   // should be ignored for non-admin
    'is_active'   => 1,
]);
$regular = $userRepo->findById($regularId);
expect('createUser() non-admin has no permissions',  $regular->getPermissions() === []);
expect('createUser() password is hashed',
    password_verify('secret123', $regular->getPassword() ?? ''));

// createUser() — admin with permissions
$adminId = $userService->createUser([
    'username'    => 'admin_awa',
    'email'       => 'awa@test.sn',
    'last_name'         => 'Ndiaye',
    'first_name'      => 'Awa',
    'password'    => 'pass456',
    'role'        => 'admin',
    'permissions' => ['students', 'export', 'INVALID_MODULE'],
    'is_active'   => 1,
]);
$admin = $userRepo->findById($adminId);
expect('createUser() admin has valid permissions',    $admin->hasPermission('students'));
expect('createUser() admin has export permission',    $admin->hasPermission('export'));
expect('createUser() invalid module filtered out',    !$admin->hasPermission('INVALID_MODULE'));

// updateUser() — self-escalation prevention
$updated = $userService->updateUser(
    $adminId,
    [
        'username'    => 'admin_awa',
        'email'       => 'awa@test.sn',
        'last_name'         => 'NdiayeUpdated',
        'first_name'      => 'AwaUpdated',
        'role'        => 'admin',
        'permissions' => ['students', 'export', 'users', 'settings'], // trying to add more
        'is_active'   => 1,
    ],
    isSelf:               true,
    isSuperAdminSession:  false
);
$afterSelfEdit = $userRepo->findById($adminId);
expect('updateUser() updates last_name during self-edit', $afterSelfEdit->getLastName() === 'NdiayeUpdated');
expect('updateUser() updates first_name during self-edit', $afterSelfEdit->getFirstName() === 'AwaUpdated');
expect('updateUser() self-edit cannot escalate',
    !$afterSelfEdit->hasPermission('settings')); // 'settings' was not in original

// updateUser() — super admin session CAN modify another admin's permissions
$userService->updateUser(
    $adminId,
    [
        'username'    => 'admin_awa',
        'email'       => 'awa@test.sn',
        'last_name'         => 'NdiayeSuper',
        'first_name'      => 'AwaSuper',
        'role'        => 'admin',
        'permissions' => ['students', 'settings'],
        'is_active'   => 1,
    ],
    isSelf:               false,
    isSuperAdminSession:  true
);
$afterSuperEdit = $userRepo->findById($adminId);
expect('updateUser() super-admin edit updates last_name',     $afterSuperEdit->getLastName() === 'NdiayeSuper');
expect('updateUser() super-admin edit updates first_name',  $afterSuperEdit->getFirstName() === 'AwaSuper');
expect('updateUser() super-admin session can add settings', $afterSuperEdit->hasPermission('settings'));
expect('updateUser() export removed by super-admin edit',  !$afterSuperEdit->hasPermission('export'));

// updateUser() — change role to user removes permissions
$userService->updateUser(
    $adminId,
    [
        'username'    => 'admin_awa',
        'email'       => 'awa@test.sn',
        'last_name'         => 'NdiayeDemoted',
        'first_name'      => 'AwaDemoted',
        'role'        => 'user',
        'permissions' => ['students'],
        'is_active'   => 1,
    ],
    isSelf:               false,
    isSuperAdminSession:  true
);
$demoted = $userRepo->findById($adminId);
expect('updateUser() demoted admin has no permissions', $demoted->getPermissions() === []);

// sanitizePermissions() whitelist
$clean = $userService->sanitizePermissions(['students', 'export', 'FAKE', 'SQL;DROP']);
expect('sanitizePermissions() keeps valid modules',   count($clean) === 2);
expect('sanitizePermissions() removes invalid ones',  !in_array('FAKE', $clean, true));

// ─── 2. AuthService + UserRepository + Session ────────────────────────────────
echo "\nAuthService + UserRepository + Session\n";

$session = new Session();
$flash   = new Flash($session);
$auth    = new AuthService($userRepo, $session, $flash);

// Create a login-capable user
$loginId = $userService->createUser([
    'username'    => 'logintest',
    'email'       => 'login@test.sn',
    'last_name'         => 'Fall',
    'first_name'      => 'Cheikh',
    'password'    => 'mypassword',
    'role'        => 'admin',
    'permissions' => ['students', 'export'],
    'is_active'   => 1,
]);

// attempt() — wrong password
$badAttempt = $auth->attempt('logintest', 'wrong');
expect('attempt() returns false for wrong password', $badAttempt === false);
expect('session not set after failed login',         $session->userId() === 0 || !$session->isLoggedIn());

// attempt() — correct credentials
$ok = $auth->attempt('logintest', 'mypassword');
expect('attempt() returns true for correct password', $ok === true);
expect('session user_id set after login',             $session->userId() === $loginId);
expect('session role set after login',                $session->role() === 'admin');
expect('session version set after login',             $session->get('session_version') === 1);

// updateLastLogin was called
$afterLogin = $userRepo->findById($loginId);
expect('attempt() updates last_login',        $afterLogin->getLastLogin() !== null);

// hasPermission() — logged-in admin with 'students'
expect('hasPermission() true for students',           $auth->hasPermission('students'));
expect('hasPermission() false for users module',      !$auth->hasPermission('users'));

// Permission cache: second call for same user uses cache
expect('hasPermission() cached — still true for export', $auth->hasPermission('export'));

// attempt() — inactive user rejected
$inactiveId = $userService->createUser([
    'username'    => 'inactive_user',
    'email'       => 'inactive@test.sn',
    'last_name'         => 'Sow',
    'first_name'      => 'Mariama',
    'password'    => 'pass789',
    'role'        => 'user',
    'permissions' => [],
    'is_active'   => 0,
]);
$inactiveAttempt = $auth->attempt('inactive_user', 'pass789');
expect('attempt() false for inactive user', $inactiveAttempt === false);

// logout() clears session
$auth->logout();
expect('logout() clears isLoggedIn',     !$session->isLoggedIn());
expect('logout() clears userId (=0)',    $session->userId() === 0);

// ─── 3. AuthService.hasPermission() — super admin bypass ──────────────────────
echo "\nAuthService super-admin bypass\n";

// Simulate super admin session (id=1)
$_SESSION['user_id'] = 1;
$_SESSION['role']    = 'admin';
expect('super-admin hasPermission() all modules true',
    $auth->hasPermission('students') &&
    $auth->hasPermission('users')    &&
    $auth->hasPermission('settings')
);

// Clean up session for subsequent tests
unset($_SESSION['user_id'], $_SESSION['role']);

// ─── 4. StudentRepository full workflow ───────────────────────────────────────
echo "\nStudentRepository full workflow\n";

$stuRepo = new StudentRepository($db);

function makeStu(array $overrides = []): array
{
    return array_merge([
        'last_name'                   => 'Diop',
        'first_name'                => 'Fatou',
        'gender'                  => 'Féminin',
        'birth_date'        => '2000-03-15',
        'residence'        => 'Dakar',
        'institution'         => 'UCAD',
        'status'                => 'En cours',
        'study_field'        => 'Informatique',
        'study_level'         => 'Licence',
        'phone'             => '771234567',
        'email'                 => 'fatou@test.sn',
        'arrival_year'         => 2022,
        'housing_type'         => 'Famille',
        'housing_details'    => null,
        'post_training_project'=> 'Emploi',
        'identity_document'              => null,
        'nationalities'          => 'Sénégalaise',
        'cv_path'               => null,
        'consent_privacy'       => 1,
    ], $overrides);
}

$s1 = $stuRepo->save(makeStu(['last_name' => 'Diop', 'email' => 'fatou@test.sn', 'gender' => 'Féminin',
    'status' => 'En cours', 'institution' => 'UCAD', 'study_level' => 'Licence']));
$s2 = $stuRepo->save(makeStu(['last_name' => 'Ba', 'email' => 'amadou@test.sn', 'gender' => 'Masculin',
    'status' => 'Diplômé(e)', 'institution' => 'ESP', 'study_level' => 'Master',
    'phone' => '779876543']));
$s3 = $stuRepo->save(makeStu(['last_name' => 'Sarr', 'email' => 'moussa@test.sn', 'gender' => 'Masculin',
    'status' => 'En cours', 'institution' => 'UCAD', 'study_level' => 'Licence',
    'phone' => '770001122']));

// findById
$stu1 = $stuRepo->findById($s1);
expect('findById() returns correct last_name',     $stu1?->getLastName() === 'Diop');

// countAll with various filters
expect('countAll() total is 3',              $stuRepo->countAll() === 3);
expect('countAll() filter by gender Masculin', $stuRepo->countAll(['gender' => 'Masculin']) === 2);
expect('countAll() filter by status',        $stuRepo->countAll(['status' => 'Diplômé(e)']) === 1);
expect('countAll() filter by institution', $stuRepo->countAll(['institution' => 'UCAD']) === 2);
expect('countAll() filter by niveau',        $stuRepo->countAll(['study_level' => 'Master']) === 1);
expect('countAll() search by last_name',           $stuRepo->countAll(['search' => 'Sarr']) === 1);
expect('countAll() search by email',         $stuRepo->countAll(['search' => 'amadou']) === 1);

// findPaginated — page 1 of 2 per page
$page1 = $stuRepo->findPaginated(1, 2);
expect('findPaginated() total is 3',         $page1['total'] === 3);
expect('findPaginated() page 1 has 2 items', count($page1['items']) === 2);

$page2 = $stuRepo->findPaginated(2, 2);
expect('findPaginated() page 2 has 1 item',  count($page2['items']) === 1);

// findPaginated with filter
$filtered = $stuRepo->findPaginated(1, 10, ['status' => 'En cours']);
expect('findPaginated() filter total is 2',  $filtered['total'] === 2);

// update()
$stuRepo->update($s1, ['status' => 'Diplômé(e)', 'graduation_date' => '2024-06-01']);
$updated = $stuRepo->findById($s1);
expect('update() status changed',            $updated?->getStatus() === 'Diplômé(e)');

// update() rejects unknown column
$threw = false;
try {
    $stuRepo->update($s1, ['DROP_TABLE' => 'hack']);
} catch (\InvalidArgumentException $e) {
    $threw = true;
}
expect('update() throws on unknown column',  $threw);

// existsByEmail / existsByPhone
expect('existsByEmail() true',               $stuRepo->existsByEmail('fatou@test.sn'));
expect('existsByEmail() false when excluded',$stuRepo->existsByEmail('fatou@test.sn', $s1) === false);
expect('existsByPhone() true',               $stuRepo->existsByPhone('779876543'));
expect('existsByPhone() false when excluded',$stuRepo->existsByPhone('779876543', $s2) === false);

// getStats()
$stats = $stuRepo->getStats();
expect('getStats() total is 3',              (int)$stats['total'] === 3);
expect('getStats() femmes is 1',             (int)$stats['femmes'] === 1);
expect('getStats() hommes is 2',             (int)$stats['hommes'] === 2);
expect('getStats() diplomes is 2',           (int)$stats['diplomes'] === 2); // s1 updated + s2

// updateField() — locked status (Student model has no getIsLocked(); verify via raw query)
$stuRepo->updateField($s3, 'is_locked', 1);
$lockedRow = $db->query("SELECT is_locked FROM students WHERE id = {$s3}")->fetch();
expect('updateField() is_locked set to 1',   (int)$lockedRow['is_locked'] === 1);

// updateField() rejects unknown field
$threwField = false;
try {
    $stuRepo->updateField($s3, 'DROP_TABLE', 'hack');
} catch (\InvalidArgumentException $e) {
    $threwField = true;
}
expect('updateField() throws on unknown field', $threwField);

// delete()
$stuRepo->delete($s3);
expect('delete() removes student',           $stuRepo->findById($s3) === null);
expect('countAll() after delete is 2',       $stuRepo->countAll() === 2);

// ─── 5. UserService permission edge cases ─────────────────────────────────────
echo "\nUserService permission edge cases\n";

// buildPermissionsJson() — Super Admin target always gets all modules
$json = $userService->buildPermissionsJson('admin', ['students'], false, false, '[]', 1);
$decoded = json_decode($json, true);
expect('buildPermissionsJson() super admin gets all modules',
    count($decoded) === count(UserService::MODULES));

// buildPermissionsJson() — non-admin role → null
$result = $userService->buildPermissionsJson('user', ['students'], false, false, '[]', 99);
expect('buildPermissionsJson() user role returns null', $result === null);

// buildPermissionsJson() — self-edit without super-admin session preserves existing JSON
$existing = json_encode(['students']);
$preserved = $userService->buildPermissionsJson('admin', ['students', 'export'], true, false, $existing, 99);
expect('buildPermissionsJson() self-edit preserves existing', $preserved === $existing);

// buildPermissionsJson() — super-admin session can override
$override = $userService->buildPermissionsJson('admin', ['export', 'users'], true, true, $existing, 99);
$overrideDec = json_decode($override, true);
expect('buildPermissionsJson() super-admin overrides self-edit',
    in_array('export', $overrideDec, true) && !in_array('students', $overrideDec, true));

// ─── Summary ──────────────────────────────────────────────────────────────────
echo "\n";
$total = $pass + $fail;
if ($fail === 0) {
    echo "\033[32m  {$pass}/{$total} tests passed\033[0m\n\n";
} else {
    echo "\033[31m  {$pass}/{$total} tests passed\033[0m\n\n";
    exit(1);
}
