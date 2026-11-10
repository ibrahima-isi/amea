<?php
namespace Amea\Core;

use Amea\Repository\UserRepository;

class Session
{
    private const LIFETIME = 1800; // 30 minutes

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            $this->configure();
        }
    }

    private function configure(): void
    {
        if (defined('AMEA_APP')) {
            // Fixed image identity, never Host or forwarded headers. No Domain cookie.
            session_name('AMEA_' . strtoupper(AMEA_APP));
            ini_set('session.cookie_domain', '');
            ini_set('session.cookie_path', '/');
        }
        ini_set('session.cookie_lifetime', '0');
        ini_set('session.gc_maxlifetime',  (string)self::LIFETIME);
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure',   '1');
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', '1');
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->checkExpiry();
    }

    public function startRequest(bool $renderLoginAfterRevocation = false): void
    {
        $this->start();
        if (!$this->isLoggedIn()) {
            return;
        }

        try {
            setlocale(LC_ALL, 'fr_FR.UTF-8');
            $users = new UserRepository(Container::db());
            if ($users->isSessionVersionCurrent($this->userId(), (int)$this->get('session_version', 0))) {
                return;
            }

            $returnTarget = StaffNavigation::requestedTarget();
            $_SESSION = [];
            $this->regenerate();
            $this->set('last_activity', time());
            if ($returnTarget !== null) {
                $this->set('staff_return_to', $returnTarget);
            }
            (new Flash($this))->set('warning', 'Votre session a expiré. Veuillez vous reconnecter.');

            if (!$renderLoginAfterRevocation) {
                header('Location: login.php');
                exit();
            }
        } catch (\Throwable $e) {
            error_log('[session] Session version check skipped: ' . $e->getMessage());
        }
    }

    private function checkExpiry(): void
    {
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > self::LIFETIME) {
            $returnTarget = StaffNavigation::requestedTarget();
            $this->destroy();
            $login = defined('AMEA_APP') && AMEA_APP === 'public' ? 'register.php' : 'login.php';
            if ($returnTarget !== null) {
                $login .= '?return_to=' . rawurlencode($returnTarget);
            }
            header('Location: ' . $login);
            exit();
        }
        $_SESSION['last_activity'] = time();
    }

    public function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function destroy(): void
    {
        session_unset();
        session_destroy();
    }

    public function userId(): int
    {
        return (int)($this->get('user_id') ?? 0);
    }

    public function role(): string
    {
        return $this->get('role', '');
    }

    public function isLoggedIn(): bool
    {
        return $this->has('user_id');
    }
}
