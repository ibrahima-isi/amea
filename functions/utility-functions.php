<?php
/**
 * functions/utility-functions.php — backward-compat wrappers
 *
 * All implementations live in src/. These wrappers preserve the old function
 * signatures so that controllers not yet migrated to OOP continue to work.
 * Remove a wrapper only after every caller has been migrated.
 */

require_once __DIR__ . '/../vendor/autoload.php';

// ─── Constants ────────────────────────────────────────────────────────────────
if (!defined('PERMISSION_MODULES')) {
    define('PERMISSION_MODULES', \Amea\Service\UserService::MODULES);
}

// ─── Env ──────────────────────────────────────────────────────────────────────
function env(string $key, mixed $default = null): mixed
{
    return $_ENV[$key] ?? $default;
}

// ─── Strings / validation ─────────────────────────────────────────────────────
function generateRandomString(int $length = 10): string
{
    $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $s = '';
    for ($i = 0; $i < $length; $i++) {
        $s .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $s;
}

function calculateAge(string $dateNaissance): int
{
    if (empty($dateNaissance)) return 0;
    try {
        return (int)(new DateTime($dateNaissance))->diff(new DateTime())->y;
    } catch (Exception) {
        return 0;
    }
}

function formatDateFr(string $date, bool $includeTime = false): string
{
    if (empty($date) || $date === '0000-00-00') return '';
    try {
        return (new DateTime($date))->format($includeTime ? 'd/m/Y à H:i' : 'd/m/Y');
    } catch (Exception) {
        return '';
    }
}

function isValidEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function isValidPhone(string $phone): bool
{
    return strlen(preg_replace('/[^0-9]/', '', $phone)) === 9;
}

function truncateString(string $string, int $length = 100, string $append = '...'): string
{
    return strlen($string) > $length ? substr($string, 0, $length) . $append : $string;
}

// ─── CSRF ─────────────────────────────────────────────────────────────────────
function generateCsrfToken(): string
{
    static $guard = null;
    if ($guard === null) {
        $guard = new \Amea\Core\CsrfGuard(new \Amea\Core\Session());
    }
    return $guard->getToken();
}

function verifyCsrfToken(string $token): bool
{
    static $guard = null;
    if ($guard === null) {
        $guard = new \Amea\Core\CsrfGuard(new \Amea\Core\Session());
    }
    return $guard->verify($token);
}

// ─── Logging ──────────────────────────────────────────────────────────────────
function logError(string $message, ?\Throwable $e = null): void
{
    $logFile = __DIR__ . '/../logs/error.log';
    $entry   = '[' . date('c') . '] ' . $message;
    if ($e) {
        $entry .= ' | ' . get_class($e) . ': ' . $e->getMessage()
               . ' in ' . $e->getFile() . ':' . $e->getLine();
    }
    error_log($entry . PHP_EOL, 3, $logFile);
}

// ─── Flash messages ───────────────────────────────────────────────────────────
function setFlashMessage(string $type, string $message): void
{
    static $flash = null;
    if ($flash === null) {
        $flash = new \Amea\Core\Flash(new \Amea\Core\Session());
    }
    $flash->set($type, $message);
}

function getFlashMessage(): ?array
{
    static $flash = null;
    if ($flash === null) {
        $flash = new \Amea\Core\Flash(new \Amea\Core\Session());
    }
    return $flash->get();
}

function getFlashMessageClass(string $type): string
{
    static $flash = null;
    if ($flash === null) {
        $flash = new \Amea\Core\Flash(new \Amea\Core\Session());
    }
    return $flash->cssClass($type);
}

// ─── Settings ─────────────────────────────────────────────────────────────────
function getSetting(string $key, string $default = ''): string
{
    global $conn;
    static $repo = null;
    if ($repo === null) {
        $repo = new \Amea\Repository\SettingRepository($conn);
    }
    return $repo->getValue($key, $default);
}

function getFooterReplacements(): array
{
    global $conn;
    static $repo = null;
    if ($repo === null) {
        $repo = new \Amea\Repository\SettingRepository($conn);
    }
    return $repo->getFooterReplacements();
}

// ─── Permissions ──────────────────────────────────────────────────────────────
function hasPermission(string $module): bool
{
    global $conn;
    static $cache = [];
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if ($uid === 0) return false;
    if ($uid === 1) return true;
    if (($_SESSION['role'] ?? '') !== 'admin') return false;
    if (isset($cache[$uid])) {
        return in_array($module, $cache[$uid], true);
    }
    $repo        = new \Amea\Repository\UserRepository($conn);
    $user        = $repo->findById($uid);
    $cache[$uid] = $user ? $user->getPermissions() : [];
    return in_array($module, $cache[$uid], true);
}

// ─── File handling ────────────────────────────────────────────────────────────
function handleFileUpload(array $file, array $allowedExtensions, int $maxSize, string $uploadDir): array
{
    return (new \Amea\Core\FileUploader(__DIR__ . '/..'))->handle($file, $allowedExtensions, $maxSize, $uploadDir);
}

function safeUnlink(?string $filePath): bool
{
    return (new \Amea\Core\FileUploader(__DIR__ . '/..'))->safeDelete($filePath);
}

// ─── CSV export ───────────────────────────────────────────────────────────────
function cleanData(?string $data): string
{
    if ($data === null) return '';
    return str_replace('"', '""', str_replace(["\r", "\n", "\t"], ' ', $data));
}

// ─── Asset versioning ─────────────────────────────────────────────────────────
function addVersionToAssets(string $html): string
{
    return \Amea\Core\TemplateEngine::versionAssets($html, __DIR__ . '/..');
}
