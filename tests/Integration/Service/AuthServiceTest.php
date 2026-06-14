<?php

namespace Amea\Tests\Integration\Service;

use Amea\Core\Container;
use Amea\Core\Flash;
use Amea\Core\Session;
use Amea\Repository\UserRepository;
use Amea\Service\AuthService;
use PHPUnit\Framework\TestCase;

class AuthServiceTest extends TestCase
{
    private \PDO $db;
    private UserRepository $userRepo;
    private Session $session;
    private AuthService $auth;

    protected function setUp(): void
    {
        $_SESSION = [];
        // Ensure a clean session state for each test
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->db = makeTestDb();
        Container::setDb($this->db);
        $this->userRepo = new UserRepository($this->db);
        $this->session  = new Session();
        $this->auth     = new AuthService($this->userRepo, $this->session, new Flash($this->session));

        // Seed a test user: diana / correct
        $this->userRepo->save([
            'username'    => 'diana',
            'email'       => 'diana@test.sn',
            'last_name'   => 'Diallo',
            'first_name'  => 'Diana',
            'password'    => password_hash('correct', PASSWORD_DEFAULT),
            'role'        => 'admin',
            'permissions' => json_encode(['students']),
            'is_active'   => 1,
        ]);
    }

    protected function tearDown(): void
    {
        Container::reset();
        // Restart a fresh session for the next test
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
    }

    public function testAttemptSucceedsWithValidCredentials(): void
    {
        $result = $this->auth->attempt('diana', 'correct');

        $this->assertTrue($result);
        $this->assertArrayHasKey('user_id', $_SESSION);
        $this->assertSame('admin', $_SESSION['role'] ?? null);
        $this->assertSame('diana', $_SESSION['username'] ?? null);
    }

    public function testAttemptFailsWithWrongPassword(): void
    {
        $result = $this->auth->attempt('diana', 'wrong');

        $this->assertFalse($result);
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function testAttemptFailsForNonExistentUser(): void
    {
        $this->assertFalse($this->auth->attempt('nobody', 'pass'));
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function testAttemptFailsForInactiveUser(): void
    {
        $this->userRepo->save([
            'username'    => 'inactive',
            'email'       => 'inactive@test.sn',
            'last_name'   => 'Off',
            'first_name'  => 'User',
            'password'    => password_hash('pass', PASSWORD_DEFAULT),
            'role'        => 'user',
            'permissions' => json_encode([]),
            'is_active'   => 0,
        ]);

        $this->assertFalse($this->auth->attempt('inactive', 'pass'));
    }

    public function testAttemptSetsSessionVersion(): void
    {
        $this->auth->attempt('diana', 'correct');
        $this->assertArrayHasKey('session_version', $_SESSION);
    }

    public function testHasPermissionReturnsTrueForGrantedModule(): void
    {
        $this->auth->attempt('diana', 'correct');
        $this->assertTrue($this->auth->hasPermission('students'));
    }

    public function testHasPermissionReturnsFalseForDeniedModule(): void
    {
        $this->auth->attempt('diana', 'correct');
        $this->assertFalse($this->auth->hasPermission('export'));
    }

    public function testHasPermissionReturnsFalseWhenNotLoggedIn(): void
    {
        // No attempt() call — user_id = 0
        $this->assertFalse($this->auth->hasPermission('students'));
    }

    public function testSuperAdminHasAllPermissions(): void
    {
        // _superadmin is id=1 (seeded by makeTestDb()) — bypasses permission check
        $this->session->set('user_id', 1);
        $this->session->set('role', 'admin');
        $this->assertTrue($this->auth->hasPermission('any_module'));
        $this->assertTrue($this->auth->hasPermission('export'));
    }

    public function testLogoutClearsSession(): void
    {
        $this->auth->attempt('diana', 'correct');
        $this->assertArrayHasKey('user_id', $_SESSION);

        $this->auth->logout();

        // session_unset() clears $_SESSION; session_destroy() ends the session
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }
}
