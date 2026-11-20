<?php
namespace Amea\Core;

class Session
{
    private const LIFETIME = 1800; // 30 minutes
    private static bool $readOnly = false;

    public static function isReadOnly(): bool
    {
        return self::$readOnly;
    }

    /** Existing files-only sessions can authorize private smoke reads during pause. */
    public static function allowPausedRead(): bool
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET'
            || !in_array($path, ['/download.php', '/registration-preview.php', '/registration-details.php',
                '/journal-preview.php', '/journal-preview-cover.php'], true)
            || ini_get('session.save_handler') !== 'files') {
            return false;
        }
        new self();
        $id = $_COOKIE[session_name()] ?? '';
        $directory = session_save_path();
        if (!is_string($id) || !preg_match('/^[A-Za-z0-9,-]{1,256}$/D', $id)
            || $directory === '' || str_contains($directory, ';')
            || is_link($directory . '/sess_' . $id) || !is_file($directory . '/sess_' . $id)) {
            return false;
        }
        // Files handler GC can delete other sessions even during read_and_close.
        ini_set('session.gc_probability', '0');
        ini_set('session.use_cookies', '0');
        session_id($id);
        self::$readOnly = true;
        return true;
    }

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
            session_save_path('/var/lib/amea/sessions');
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
        if (PHP_SAPI !== 'cli') {
            FileWriteGate::admitHttp();
        }
        if (session_status() === PHP_SESSION_NONE) {
            if (!session_start(self::$readOnly ? ['read_and_close' => true] : [])) {
                throw new \RuntimeException('Unable to read the selected session.');
            }
        }
        $this->checkExpiry();
    }

    private function checkExpiry(): void
    {
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > self::LIFETIME) {
            if (self::$readOnly) {
                http_response_code(503);
                header('Retry-After: 60');
                exit('Maintenance en cours. Veuillez réessayer plus tard.');
            }
            $returnTarget = StaffNavigation::requestedTarget();
            $this->destroy();
            $login = defined('AMEA_APP') && AMEA_APP === 'public' ? 'register.php' : 'login.php';
            if ($returnTarget !== null) {
                $login .= '?return_to=' . rawurlencode($returnTarget);
            }
            header('Location: ' . $login);
            exit();
        }
        if (!self::$readOnly) {
            $_SESSION['last_activity'] = time();
        }
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
