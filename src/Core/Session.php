<?php
namespace Amea\Core;

use Amea\Repository\UserRepository;

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
        if ((!is_string($id) || $id === '') && ($legacy = self::legacyPublicSessionId()) !== null) {
            $id = $legacy;
        }
        if (!is_string($id) || !self::hasRegularSessionFile($id)) {
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
            ini_set('session.cookie_domain', '');
            ini_set('session.cookie_path', '/');
            $privatePath = Runtime::stateDir() === null ? null : Runtime::sessionSavePath(AMEA_APP);
            if ($privatePath !== null) {
                session_save_path($privatePath);
            }
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
        $adoptedLegacy = false;
        if (session_status() === PHP_SESSION_NONE) {
            if (!self::$readOnly) {
                $legacyId = self::legacyPublicSessionId();
                if ($legacyId !== null) {
                    session_id($legacyId);
                    $adoptedLegacy = true;
                }
            }
            if (!session_start(self::$readOnly ? ['read_and_close' => true] : [])) {
                throw new \RuntimeException('Unable to read the selected session.');
            }
            if ($adoptedLegacy) {
                $this->completeLegacyPublicHandover();
            }
        }
        $this->checkExpiry();
    }

    private static function legacyPublicSessionId(): ?string
    {
        if (!defined('AMEA_APP') || AMEA_APP !== 'public') {
            return null;
        }
        $current = $_COOKIE['AMEA_PUBLIC'] ?? '';
        if (is_string($current) && $current !== '') {
            return null;
        }
        $legacyId = $_COOKIE['PHPSESSID'] ?? '';
        if (!is_string($legacyId) || !self::hasRegularSessionFile($legacyId)) {
            return null;
        }
        return $legacyId;
    }

    private static function hasRegularSessionFile(string $id): bool
    {
        $directory = session_save_path();
        return preg_match('/^[A-Za-z0-9,-]{1,256}$/D', $id) === 1
            && $directory !== '' && !str_contains($directory, ';')
            && !is_link($directory . '/sess_' . $id) && is_file($directory . '/sess_' . $id);
    }

    private function completeLegacyPublicHandover(): void
    {
        if (!isset($_SESSION['pending_registration']) || !is_array($_SESSION['pending_registration'])) {
            $_SESSION = [];
        } else {
            $_SESSION = array_intersect_key(
                $_SESSION,
                array_flip(['pending_registration', 'csrf_token', 'last_activity', 'flash'])
            );
        }
        session_regenerate_id(true);
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            setcookie('PHPSESSID', '', [
                'expires' => 1,
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
        }
    }

    public function startRequest(): void
    {
        $this->start();
        if (empty($this->get('user_id'))) {
            return;
        }

        try {
            setlocale(LC_ALL, 'fr_FR.UTF-8');
            $users = new UserRepository(Container::db());
            if ($users->isSessionVersionCurrent($this->userId(), (int)$this->get('session_version', 0))) {
                return;
            }

            if (self::$readOnly) {
                http_response_code(503);
                header('Retry-After: 60');
                exit('Maintenance en cours. Veuillez réessayer plus tard.');
            }

            $returnTarget = StaffNavigation::requestedTarget();
            $_SESSION = [];
            $this->regenerate();
            $this->set('last_activity', time());
            if ($returnTarget !== null) {
                $this->set('staff_return_to', $returnTarget);
            }
            (new Flash($this))->set('warning', 'Votre session a expiré. Veuillez vous reconnecter.');

            header('Location: login.php');
            exit();
        } catch (\Throwable $e) {
            error_log('[session] Session version check skipped: ' . $e->getMessage());
        }
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
