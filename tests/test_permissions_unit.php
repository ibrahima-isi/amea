<?php
/**
 * Unit tests for the hasPermission() function.
 *
 * Uses a SQLite in-memory database to mock MySQL so no live DB is required.
 * Run from project root: php tests/test_permissions_unit.php
 */

require_once __DIR__ . '/../functions/utility-functions.php';

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
$conn = new PDO('sqlite::memory:');
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$conn->exec("
    CREATE TABLE users (
        id_user   INTEGER PRIMARY KEY,
        role      TEXT,
        permissions TEXT
    )
");

// Super Admin (ID 1) — permissions column is intentionally left null; hasPermission bypasses DB for ID 1
$conn->exec("INSERT INTO users (id_user, role, permissions) VALUES (1, 'admin', NULL)");
// Regular admin with some permissions
$conn->exec("INSERT INTO users (id_user, role, permissions) VALUES (2, 'admin', '[\"students\",\"export\"]')");
// Admin with no permissions
$conn->exec("INSERT INTO users (id_user, role, permissions) VALUES (3, 'admin', '[]')");
// Admin with null permissions stored as empty string
$conn->exec("INSERT INTO users (id_user, role, permissions) VALUES (4, 'admin', '')");
// Non-admin user
$conn->exec("INSERT INTO users (id_user, role, permissions) VALUES (5, 'user', NULL)");
// Admin with all permissions
$allPerms = json_encode(['students','export','users','slider','upgrade','documents','communications','settings']);
$stmt = $conn->prepare("INSERT INTO users (id_user, role, permissions) VALUES (6, 'admin', ?)");
$stmt->execute([$allPerms]);
// Admin with malformed JSON
$conn->exec("INSERT INTO users (id_user, role, permissions) VALUES (7, 'admin', 'not-valid-json')");

// Helper: set a fake session state
function setSession(int $uid, string $role = 'admin'): void {
    $_SESSION = [];
    if ($uid > 0) {
        $_SESSION['user_id'] = $uid;
        $_SESSION['role']    = $role;
    }
}

// Start a CLI-safe session
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// ─── 1. Unauthenticated (no session) ─────────────────────────────────────────
echo "\nUnauthenticated user\n";

setSession(0);
expect('returns false when no user_id in session',         !hasPermission('students'));
expect('returns false for any module when not logged in',  !hasPermission('settings'));

// ─── 2. Super Admin (ID 1) ────────────────────────────────────────────────────
echo "\nSuper Admin (ID 1)\n";

setSession(1);
expect('always returns true for "students"',       hasPermission('students'));
expect('always returns true for "settings"',       hasPermission('settings'));
expect('always returns true for "documents"',      hasPermission('documents'));
expect('always returns true for unknown module',   hasPermission('nonexistent_module'));

// ─── 3. Non-admin role ────────────────────────────────────────────────────────
echo "\nNon-admin role\n";

setSession(5, 'user');
expect('returns false for role=user (students)',   !hasPermission('students'));
expect('returns false for role=user (export)',     !hasPermission('export'));

// ─── 4. Admin with specific permissions ──────────────────────────────────────
echo "\nAdmin with [students, export] permissions (ID 2)\n";

setSession(2);
expect('returns true for "students" (granted)',    hasPermission('students'));
expect('returns true for "export" (granted)',      hasPermission('export'));
expect('returns false for "users" (not granted)',  !hasPermission('users'));
expect('returns false for "settings" (not granted)', !hasPermission('settings'));
expect('returns false for "documents" (not granted)', !hasPermission('documents'));

// ─── 5. Admin with empty permissions array ────────────────────────────────────
echo "\nAdmin with empty permissions [] (ID 3)\n";

setSession(3);
expect('returns false for "students"',  !hasPermission('students'));
expect('returns false for "export"',    !hasPermission('export'));
expect('returns false for "settings"',  !hasPermission('settings'));

// ─── 6. Admin with empty-string permissions (null stored as "") ───────────────
echo "\nAdmin with empty-string permissions (ID 4)\n";

setSession(4);
expect('returns false for "students"',  !hasPermission('students'));
expect('returns false for "users"',     !hasPermission('users'));

// ─── 7. Admin with all permissions ───────────────────────────────────────────
echo "\nAdmin with all permissions (ID 6)\n";

setSession(6);
expect('returns true for "students"',       hasPermission('students'));
expect('returns true for "export"',         hasPermission('export'));
expect('returns true for "users"',          hasPermission('users'));
expect('returns true for "slider"',         hasPermission('slider'));
expect('returns true for "upgrade"',        hasPermission('upgrade'));
expect('returns true for "documents"',      hasPermission('documents'));
expect('returns true for "communications"', hasPermission('communications'));
expect('returns true for "settings"',       hasPermission('settings'));

// ─── 8. Admin with malformed JSON ─────────────────────────────────────────────
echo "\nAdmin with malformed JSON permissions (ID 7)\n";

setSession(7);
expect('returns false for "students" (bad JSON)',  !hasPermission('students'));
expect('returns false for "settings" (bad JSON)',  !hasPermission('settings'));

// ─── 9. Module name injection attempts ───────────────────────────────────────
echo "\nModule name injection (ID 2 — has [students, export])\n";

setSession(2);
expect('returns false for empty module name',      !hasPermission(''));
expect('returns false for SQL-like module name',   !hasPermission("' OR '1'='1"));
expect('returns false for wildcard module name',   !hasPermission('*'));

// ─── 10. Per-request static cache ─────────────────────────────────────────────
// Call hasPermission() twice for the same user — second call must hit the cache
// (no way to inspect the cache directly, but we verify the result is consistent).
//
// IMPORTANT — UID isolation:
// hasPermission() uses a static $cache keyed by user ID that persists for the
// entire PHP process lifetime. Each test group MUST use a distinct user ID.
// Re-using a UID that was already exercised in a previous group will hit the
// cache and return stale results, causing misleading test failures or passes.
echo "\nPer-request static cache (ID 2)\n";

setSession(2);
$first  = hasPermission('students');
$second = hasPermission('students');
expect('cached result matches first call (true)',  $first  === true  && $second === true);

$firstNo  = hasPermission('settings');
$secondNo = hasPermission('settings');
expect('cached result matches first call (false)', $firstNo === false && $secondNo === false);

// ─── 11. PERMISSION_MODULES constant ─────────────────────────────────────────
echo "\nPERMISSION_MODULES constant\n";

expect('constant is defined',                   defined('PERMISSION_MODULES'));
expect('contains exactly 8 modules',            count(PERMISSION_MODULES) === 8);
expect('contains "students"',                   in_array('students',       PERMISSION_MODULES));
expect('contains "export"',                     in_array('export',         PERMISSION_MODULES));
expect('contains "users"',                      in_array('users',          PERMISSION_MODULES));
expect('contains "slider"',                     in_array('slider',         PERMISSION_MODULES));
expect('contains "upgrade"',                    in_array('upgrade',        PERMISSION_MODULES));
expect('contains "documents"',                  in_array('documents',      PERMISSION_MODULES));
expect('contains "communications"',             in_array('communications', PERMISSION_MODULES));
expect('contains "settings"',                   in_array('settings',       PERMISSION_MODULES));

// ─── 12. DB is authoritative — $_SESSION['permissions'] is NOT used ───────────
echo "\nDB is authoritative (session shortcut removed)\n";

// User 99 is NOT in the SQLite DB. Even with $_SESSION['permissions'] set,
// hasPermission() must query the DB and return false (user not found).
$_SESSION = [
    'user_id'     => 99,
    'role'        => 'admin',
    'permissions' => json_encode(['slider', 'upgrade']), // would grant access if session were used
];
expect('returns false for "slider" — user not in DB, session value ignored',  !hasPermission('slider'));
expect('returns false for "upgrade" — user not in DB, session value ignored', !hasPermission('upgrade'));

// ─── Summary ─────────────────────────────────────────────────────────────────
echo "\n";
$total = $passed + $failed;
echo "\033[" . ($failed > 0 ? '31' : '32') . "m  $passed/$total tests passed\033[0m\n\n";
exit($failed > 0 ? 1 : 0);
