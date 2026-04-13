<?php
namespace Amea\Core;

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

    private function checkExpiry(): void
    {
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > self::LIFETIME) {
            $this->destroy();
            header('Location: login.php');
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
