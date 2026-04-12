<?php
/**
 * Integration tests for the permissions system and the privilege-escalation fix.
 *
 * Requires a running local server: php -S localhost:8000
 * Run from project root: php tests/test_permissions_integration.php
 *
 * Strategy:
 *   1. Connect to the real DB and create an isolated test user.
 *   2. Log in via HTTP to obtain a real session cookie.
 *   3. Exercise the protected endpoints with that cookie.
 *   4. Tear down the test user unconditionally.
 */

require_once __DIR__ . '/../functions/utility-functions.php';
require_once __DIR__ . '/../config/database.php'; // sets $conn

define('BASE_URL', 'http://localhost:8000');

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

// ─── HTTP helper ─────────────────────────────────────────────────────────────
/**
 * Make an HTTP request and return [http_code, location_header, body].
 */
function http(string $method, string $path, array $postFields = [], string $cookie = ''): array {
    $ch = curl_init(BASE_URL . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // capture redirects, don't follow
    curl_setopt($ch, CURLOPT_HEADER, true);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
    }
    if ($cookie !== '') {
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    }

    $raw      = curl_exec($ch);
    $code     = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hdrSize  = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $headers  = substr($raw, 0, $hdrSize);
    $body     = substr($raw, $hdrSize);

    $location = '';
    if (preg_match('/^Location:\s*(.+)$/im', $headers, $m)) {
        $location = trim($m[1]);
    }

    $setCookie = '';
    if (preg_match('/^Set-Cookie:\s*([^;]+)/im', $headers, $m)) {
        $setCookie = trim($m[1]);
    }

    return [$code, $location, $body, $setCookie];
}

/**
 * Log in as $username/$password and return the session cookie string, or '' on failure.
 */
function login(string $username, string $password): string {
    // First GET to obtain a CSRF token cookie/form value
    [$code, , $body, $cookie] = http('GET', '/login.php');

    // Extract CSRF token from the login form
    if (!preg_match('/name="csrf_token"\s+value="([^"]+)"/', $body, $m)) {
        echo "\033[33m  [setup] Could not extract CSRF token from login page\033[0m\n";
        return '';
    }
    $csrf = $m[1];

    [$code, $location, , $sessionCookie] = http('POST', '/login.php', [
        'username'   => $username,
        'password'   => $password,
        'csrf_token' => $csrf,
    ], $cookie);

    if ($code === 302 && $sessionCookie !== '') {
        return $sessionCookie;
    }

    echo "\033[33m  [setup] Login failed for '$username' (HTTP $code)\033[0m\n";
    return '';
}

// ─── Test DB fixtures ─────────────────────────────────────────────────────────
// Create two temporary test users.
//   - test_superadmin: role=admin, id forced to be != 1 but granted all perms (just uses a known ID)
//   - test_restricted: role=admin, only 'documents' permission (NOT 'users')
// Both have a known password so we can log in via HTTP.
//
// We use a high ID range (900xx) unlikely to clash with real data.

$testPassword = 'TestPass#2026!';
$testHash     = password_hash($testPassword, PASSWORD_DEFAULT);

$allPermsJson  = json_encode(['students','export','users','slider','upgrade','documents','communications','settings']);
$limitedPermsJson = json_encode(['documents']); // only documents, NOT users

// Clean up any leftover fixtures from a previous failed run
$conn->exec("DELETE FROM users WHERE username IN ('_test_fulladmin','_test_restricted')");

$conn->prepare("INSERT INTO users (username, email, nom, prenom, password, role, permissions, est_actif, date_creation)
                VALUES ('_test_fulladmin', '_test_fulladmin@test.local', 'Test', 'FullAdmin', ?, 'admin', ?, 1, NOW())")
     ->execute([$testHash, $allPermsJson]);
$fullAdminId = (int)$conn->lastInsertId();

$conn->prepare("INSERT INTO users (username, email, nom, prenom, password, role, permissions, est_actif, date_creation)
                VALUES ('_test_restricted', '_test_restricted@test.local', 'Test', 'Restricted', ?, 'admin', ?, 1, NOW())")
     ->execute([$testHash, $limitedPermsJson]);
$restrictedId = (int)$conn->lastInsertId();

// ─── Login sessions ───────────────────────────────────────────────────────────
$fullAdminCookie  = login('_test_fulladmin',  $testPassword);
$restrictedCookie = login('_test_restricted', $testPassword);

$sessionsOk = $fullAdminCookie !== '' && $restrictedCookie !== '';

if (!$sessionsOk) {
    echo "\033[31m  [fatal] Could not create test sessions — aborting authenticated tests\033[0m\n";
}

// ─── Tests ────────────────────────────────────────────────────────────────────

// 1. Unauthenticated access → always redirect
echo "\nUnauthenticated access — all protected pages must redirect\n";

foreach ([
    '/edit-user.php?id=1',
    '/users.php',
    '/settings.php',
    '/students.php',
    '/export.php',
    '/manage-slider.php',
    '/upgrade-levels.php',
    '/reconcile-documents.php',
    '/communications.php',
] as $path) {
    [$code] = http('GET', $path);
    expect("GET $path → 302 redirect", $code === 302);
}

// 2. edit-user.php: restricted admin (no 'users' perm) must be blocked
echo "\nRestricted admin (documents-only) access to edit-user.php\n";

if ($sessionsOk) {
    [$code, $location] = http('GET', "/edit-user.php?id=$restrictedId", [], $restrictedCookie);
    expect('GET edit-user.php → 302 (no users permission)',   $code === 302);
    expect('Redirected to dashboard (not to users.php)',      str_contains($location, 'dashboard.php'));
} else {
    expect('SKIP: session unavailable — restricted GET edit-user.php', false);
}

// 3. edit-user.php: full-admin CAN access edit-user.php
echo "\nFull-admin access to edit-user.php\n";

if ($sessionsOk) {
    [$code] = http('GET', "/edit-user.php?id=$restrictedId", [], $fullAdminCookie);
    expect('GET edit-user.php → 200 (has users permission)',  $code === 200);
}

// 4. Privilege escalation prevention: restricted admin cannot POST to edit-user.php
//    to grant themselves extra permissions
echo "\nPrivilege escalation prevention via POST to edit-user.php\n";

if ($sessionsOk) {
    // Fetch the real CSRF token from the page (full admin renders it for us, we only need
    // to know it's there; restricted admin can't even GET the page)
    [$getCode, , $body] = http('GET', "/edit-user.php?id=$restrictedId", [], $fullAdminCookie);
    $csrf = '';
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/', $body, $m)) {
        $csrf = $m[1];
    }

    // Attempt: restricted admin POSTs all permissions to their own account
    // (they cannot even reach the page, so they won't have a valid CSRF token)
    [$postCode, $postLocation] = http('POST', "/edit-user.php?id=$restrictedId", [
        'csrf_token'    => 'invalid_csrf',
        'username'      => '_test_restricted',
        'email'         => '_test_restricted@test.local',
        'role'          => 'admin',
        'est_actif'     => '1',
        'permissions[]' => ['students','export','users','slider','upgrade','documents','communications','settings'],
    ], $restrictedCookie);

    expect('POST with invalid CSRF → 302 redirect',         $postCode === 302);

    // Verify DB: restricted admin's permissions must still be documents-only
    $check = $conn->prepare("SELECT permissions FROM users WHERE id_user = ?");
    $check->execute([$restrictedId]);
    $permsInDb = json_decode($check->fetchColumn(), true);
    expect('DB permissions unchanged after blocked POST',
        $permsInDb === ['documents']
    );
}

// 5. Self-permission escalation via valid CSRF (the core fix)
//    Full-admin edits their OWN record trying to add more permissions — must be blocked
echo "\nSelf-permission escalation must be blocked even with valid CSRF\n";

if ($sessionsOk) {
    // Full admin tries to edit themselves
    [, , $body] = http('GET', "/edit-user.php?id=$fullAdminId", [], $fullAdminCookie);
    $csrf = '';
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/', $body, $m)) {
        $csrf = $m[1];
    }

    if ($csrf !== '') {
        // POST: self-edit, try to change their own permissions
        $originalPerms = json_decode($allPermsJson, true);
        $manipulated   = array_merge($originalPerms, ['injected_module']);

        http('POST', "/edit-user.php?id=$fullAdminId", [
            'csrf_token'  => $csrf,
            'username'    => '_test_fulladmin',
            'email'       => '_test_fulladmin@test.local',
            'role'        => 'admin',
            'est_actif'   => '1',
            'permissions' => $manipulated,
        ], $fullAdminCookie);

        $check = $conn->prepare("SELECT permissions FROM users WHERE id_user = ?");
        $check->execute([$fullAdminId]);
        $permsInDb = json_decode($check->fetchColumn(), true);
        expect('injected_module NOT present after self-edit',
            !in_array('injected_module', $permsInDb ?? [])
        );
        expect('legitimate permissions preserved after self-edit',
            in_array('students', $permsInDb ?? []) && in_array('settings', $permsInDb ?? [])
        );
    } else {
        expect('SKIP: could not extract CSRF token for self-edit test', false);
    }
}

// 6. users.php: restricted admin must be blocked
echo "\nRestricted admin access to users.php\n";

if ($sessionsOk) {
    [$code, $location] = http('GET', '/users.php', [], $restrictedCookie);
    expect('GET users.php → 302 (no users permission)',   $code === 302);
    expect('Redirected to dashboard.php',                 str_contains($location, 'dashboard.php'));
}

// 7. Full-admin can access users.php
if ($sessionsOk) {
    [$code] = http('GET', '/users.php', [], $fullAdminCookie);
    expect('Full-admin GET users.php → 200',  $code === 200);
}

// 8. settings.php: restricted admin (no 'settings') must be blocked
echo "\nRestricted admin access to settings.php\n";

if ($sessionsOk) {
    [$code, $location] = http('GET', '/settings.php', [], $restrictedCookie);
    expect('GET settings.php → 302 (no settings permission)',  $code === 302);
    expect('Redirected to dashboard.php',                      str_contains($location, 'dashboard.php'));
}

// 9. CSRF enforcement on POST to edit-user.php (full admin, missing token)
echo "\nCSRF enforcement on edit-user.php\n";

if ($sessionsOk) {
    [$code, $location] = http('POST', "/edit-user.php?id=$restrictedId", [
        // no csrf_token
        'username'  => '_test_restricted',
        'email'     => '_test_restricted@test.local',
        'role'      => 'admin',
        'est_actif' => '1',
    ], $fullAdminCookie);
    expect('POST without CSRF token → 302 redirect', $code === 302);
}

// 10. settings.php: unauthenticated request redirects to login.php (not dashboard)
echo "\nsettings.php auth check order\n";

[$code, $location] = http('GET', '/settings.php');
expect('Unauthenticated GET settings.php → 302',         $code === 302);
expect('Redirects to login.php (auth check runs first)', str_contains($location, 'login.php'));

// 11. Permission whitelist: injected module name must not appear in DB
echo "\nPermission whitelist on edit-user.php\n";

if ($sessionsOk) {
    [, , $body] = http('GET', "/edit-user.php?id=$restrictedId", [], $fullAdminCookie);
    $csrf = '';
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/', $body, $m)) {
        $csrf = $m[1];
    }

    if ($csrf !== '') {
        http('POST', "/edit-user.php?id=$restrictedId", [
            'csrf_token'  => $csrf,
            'username'    => '_test_restricted',
            'email'       => '_test_restricted@test.local',
            'role'        => 'admin',
            'est_actif'   => '1',
            'permissions' => ['documents', '../../etc/passwd', 'injected_module'],
        ], $fullAdminCookie);

        $check = $conn->prepare("SELECT permissions FROM users WHERE id_user = ?");
        $check->execute([$restrictedId]);
        $stored = json_decode($check->fetchColumn(), true) ?? [];
        expect('injected_module not stored in DB',      !in_array('injected_module', $stored));
        expect('../../etc/passwd not stored in DB',     !in_array('../../etc/passwd', $stored));
        expect('legitimate "documents" is preserved',   in_array('documents', $stored));
    } else {
        expect('SKIP: could not extract CSRF for whitelist test', false);
    }
}

// ─── Teardown ─────────────────────────────────────────────────────────────────
$conn->exec("DELETE FROM users WHERE username IN ('_test_fulladmin','_test_restricted')");

// ─── Summary ─────────────────────────────────────────────────────────────────
echo "\n";
$total = $passed + $failed;
echo "\033[" . ($failed > 0 ? '31' : '32') . "m  $passed/$total tests passed\033[0m\n\n";
exit($failed > 0 ? 1 : 0);
