<?php
/**
 * Unit tests for AuthService::hasPermission().
 *
 * Uses a SQLite in-memory database to mock MySQL so no live DB is required.
 * Run from project root: php tests/test_permissions_unit.php
 */

use Amea\Core\{Flash, Session};
use Amea\Repository\UserRepository;
use Amea\Service\{AuthService, UserService};

require_once __DIR__ . '/../vendor/autoload.php';

// ─── Minimal test runner ──────────────────────────────────────────────────────
$passed = 0;
$failed = 0;

function expect(string $name, bool $result): void {
    global $passed, $failed;
    if ($result) {
        echo "\033[32m  ✓ $name\033[0m\n";
        $passed++;
    } else {
        echo "\033[31m  ✗ $name\033[0m\n";
        $failed++;
    }
}

// ─── SQLite in-memory DB mimicking the users table ────────────────────────────
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("
    CREATE TABLE users (
        id   INTEGER PRIMARY KEY,
        role      TEXT,
        permissions TEXT
    )
");

// Super Admin (ID 1) — permissions column is intentionally left null; hasPermission bypasses DB for ID 1
$db->exec("INSERT INTO users (id, role, permissions) VALUES (1, 'admin', NULL)");
// Regular admin with some permissions
$db->exec("INSERT INTO users (id, role, permissions) VALUES (2, 'admin', '[\"students\",\"export\"]')");
// Admin with no permissions
$db->exec("INSERT INTO users (id, role, permissions) VALUES (3, 'admin', '[]')");
// Admin with null permissions stored as empty string
$db->exec("INSERT INTO users (id, role, permissions) VALUES (4, 'admin', '')");
// Non-admin user
$db->exec("INSERT INTO users (id, role, permissions) VALUES (5, 'user', '[\"students\",\"export\"]')");
// Admin with all permissions
$allPerms = json_encode(['students','export','users','journal','slider','upgrade','documents','communications','settings']);
$stmt = $db->prepare("INSERT INTO users (id, role, permissions) VALUES (6, 'admin', ?)");
$stmt->execute([$allPerms]);
// Admin with malformed JSON
$db->exec("INSERT INTO users (id, role, permissions) VALUES (7, 'admin', 'not-valid-json')");

// Helper: set a fake session state
function setSession(int $uid, string $role = 'admin'): void {
    $_SESSION = [];
    if ($uid > 0) {
        $_SESSION['user_id'] = $uid;
        $_SESSION['role']    = $role;
    }
}

// Start a CLI-safe session
$session = new Session();
$session->start();
$auth = new AuthService(new UserRepository($db), $session, new Flash($session));

// ─── 1. Unauthenticated (no session) ─────────────────────────────────────────
echo "\nUnauthenticated user\n";

setSession(0);
expect('returns false when no user_id in session',         !$auth->hasPermission('students'));
expect('returns false for any module when not logged in',  !$auth->hasPermission('settings'));

// ─── 2. Super Admin (ID 1) ────────────────────────────────────────────────────
echo "\nSuper Admin (ID 1)\n";

setSession(1);
expect('always returns true for "students"',       $auth->hasPermission('students'));
expect('always returns true for "settings"',       $auth->hasPermission('settings'));
expect('always returns true for "documents"',      $auth->hasPermission('documents'));
expect('always returns true for unknown module',   $auth->hasPermission('nonexistent_module'));

// ─── 3. Non-admin role ────────────────────────────────────────────────────────
echo "\nNon-admin role\n";

setSession(5, 'user');
expect('returns false for role=user (students)',   !$auth->hasPermission('students'));
expect('returns false for role=user (export)',     !$auth->hasPermission('export'));

// ─── 4. Admin with specific permissions ──────────────────────────────────────
echo "\nAdmin with [students, export] permissions (ID 2)\n";

setSession(2);
expect('returns true for "students" (granted)',    $auth->hasPermission('students'));
expect('returns true for "export" (granted)',      $auth->hasPermission('export'));
expect('returns false for "users" (not granted)',  !$auth->hasPermission('users'));
expect('returns false for "settings" (not granted)', !$auth->hasPermission('settings'));
expect('returns false for "documents" (not granted)', !$auth->hasPermission('documents'));

// ─── 5. Admin with empty permissions array ────────────────────────────────────
echo "\nAdmin with empty permissions [] (ID 3)\n";

setSession(3);
expect('returns false for "students"',  !$auth->hasPermission('students'));
expect('returns false for "export"',    !$auth->hasPermission('export'));
expect('returns false for "settings"',  !$auth->hasPermission('settings'));

// ─── 6. Admin with empty-string permissions (null stored as "") ───────────────
echo "\nAdmin with empty-string permissions (ID 4)\n";

setSession(4);
expect('returns false for "students"',  !$auth->hasPermission('students'));
expect('returns false for "users"',     !$auth->hasPermission('users'));

// ─── 7. Admin with all permissions ───────────────────────────────────────────
echo "\nAdmin with all permissions (ID 6)\n";

setSession(6);
expect('returns true for "students"',       $auth->hasPermission('students'));
expect('returns true for "export"',         $auth->hasPermission('export'));
expect('returns true for "users"',          $auth->hasPermission('users'));
expect('returns true for "journal"',        $auth->hasPermission('journal'));
expect('returns true for "slider"',         $auth->hasPermission('slider'));
expect('returns true for "upgrade"',        $auth->hasPermission('upgrade'));
expect('returns true for "documents"',      $auth->hasPermission('documents'));
expect('returns true for "communications"', $auth->hasPermission('communications'));
expect('returns true for "settings"',       $auth->hasPermission('settings'));

// ─── 8. Admin with malformed JSON ─────────────────────────────────────────────
echo "\nAdmin with malformed JSON permissions (ID 7)\n";

setSession(7);
expect('returns false for "students" (bad JSON)',  !$auth->hasPermission('students'));
expect('returns false for "settings" (bad JSON)',  !$auth->hasPermission('settings'));

// ─── 9. Module name injection attempts ───────────────────────────────────────
echo "\nModule name injection (ID 2 — has [students, export])\n";

setSession(2);
expect('returns false for empty module name',      !$auth->hasPermission(''));
expect('returns false for SQL-like module name',   !$auth->hasPermission("' OR '1'='1"));
expect('returns false for wildcard module name',   !$auth->hasPermission('*'));

// ─── 10. Per-request permissions cache ───────────────────────────────────────
echo "\nPer-request permissions cache (ID 2)\n";

setSession(2);
$first  = $auth->hasPermission('students');
$second = $auth->hasPermission('students');
expect('cached result matches first call (true)',  $first  === true  && $second === true);

$firstNo  = $auth->hasPermission('settings');
$secondNo = $auth->hasPermission('settings');
expect('cached result matches first call (false)', $firstNo === false && $secondNo === false);

// ─── 11. UserService::MODULES constant ─────────────────────────────────────────
echo "\nUserService::MODULES constant\n";

expect('contains exactly 9 modules',            count(UserService::MODULES) === 9);
expect('contains "students"',                   in_array('students',       UserService::MODULES));
expect('contains "export"',                     in_array('export',         UserService::MODULES));
expect('contains "users"',                      in_array('users',          UserService::MODULES));
expect('contains "journal"',                    in_array('journal',        UserService::MODULES));
expect('contains "slider"',                     in_array('slider',         UserService::MODULES));
expect('contains "upgrade"',                    in_array('upgrade',        UserService::MODULES));
expect('contains "documents"',                  in_array('documents',      UserService::MODULES));
expect('contains "communications"',             in_array('communications', UserService::MODULES));
expect('contains "settings"',                   in_array('settings',       UserService::MODULES));

// ─── 12. DB is authoritative — $_SESSION['permissions'] is NOT used ───────────
echo "\nDB is authoritative (session shortcut removed)\n";

// User 99 is NOT in the SQLite DB. Even with $_SESSION['permissions'] set,
// hasPermission() must query the DB and return false (user not found).
$_SESSION = [
    'user_id'     => 99,
    'role'        => 'admin',
    'permissions' => json_encode(['slider', 'upgrade']), // would grant access if session were used
];
expect('returns false for "slider" — user not in DB, session value ignored',  !$auth->hasPermission('slider'));
expect('returns false for "upgrade" — user not in DB, session value ignored', !$auth->hasPermission('upgrade'));

// ─── Summary ─────────────────────────────────────────────────────────────────
echo "\n";
$total = $passed + $failed;
echo "\033[" . ($failed > 0 ? '31' : '32') . "m  $passed/$total tests passed\033[0m\n\n";
exit($failed > 0 ? 1 : 0);
