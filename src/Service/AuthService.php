<?php
namespace Amea\Service;

use Amea\Core\{Flash, Session};
use Amea\Repository\UserRepository;

class AuthService
{
    /** Per-request permissions cache: [uid => string[]] */
    private array $permCache = [];

    public function __construct(
        private UserRepository $users,
        private Session        $session,
        private Flash          $flash
    ) {}

    public function attempt(string $username, string $password): bool
    {
        $user = $this->users->findActiveByUsername($username);
        if ($user === null) return false;
        if (!password_verify($password, $user->getPassword() ?? '')) return false;

        $this->session->regenerate();
        $this->session->set('user_id', $user->getId());
        $this->session->set('username', $user->getUsername());
        $this->session->set('role',    $user->getRole());
        $this->session->set('last_name',  $user->getLastName());
        $this->session->set('first_name', $user->getFirstName());
        $this->session->set('session_version', $user->getSessionVersion());

        $this->users->updateLastLogin($user->getId());
        return true;
    }

    public function logout(): void
    {
        $this->session->destroy();
    }

    public function requireLogin(string $redirectTo = 'login.php'): void
    {
        if (!$this->session->isLoggedIn()) {
            header("Location: {$redirectTo}");
            exit();
        }
    }

    public function requireRole(string $role, string $redirectTo = 'dashboard.php'): void
    {
        if ($this->session->role() !== $role) {
            $this->flash->set('error', 'Accès non autorisé.');
            header("Location: {$redirectTo}");
            exit();
        }
    }

    public function requirePermission(string $module, string $redirectTo = 'dashboard.php'): void
    {
        $uid = $this->session->userId();
        if ($uid === 1) return; // Super Admin bypass

        if (!isset($this->permCache[$uid])) {
            $user = $this->users->findById($uid);
            $this->permCache[$uid] = $user ? $user->getPermissions() : [];
        }

        if (!in_array($module, $this->permCache[$uid], true)) {
            $this->flash->set('error', "Accès refusé : vous n'avez pas la permission d'accéder à ce module.");
            header("Location: {$redirectTo}");
            exit();
        }
    }

    public function hasPermission(string $module): bool
    {
        $uid = $this->session->userId();
        if ($uid === 0)  return false;
        if ($uid === 1)  return true;
        if ($this->session->role() !== 'admin') return false;

        if (!isset($this->permCache[$uid])) {
            $user = $this->users->findById($uid);
            $this->permCache[$uid] = $user ? $user->getPermissions() : [];
        }
        return in_array($module, $this->permCache[$uid], true);
    }
}
