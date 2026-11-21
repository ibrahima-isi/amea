<?php
namespace Amea\Core;

/** Staff destinations are fixed configuration plus exact local application routes. */
final class StaffNavigation
{
    private const AUTH_PATHS = ['/login.php', '/logout.php', '/forgot-password.php', '/reset-password.php'];
    private const QUERY_KEYS = [
        '/students.php' => ['page', 'perPage', 'search', 'gender', 'status', 'institution', 'study_level'],
        '/student-details.php' => ['id'], '/edit-student.php' => ['id'], '/kyc-detail.php' => ['id'],
        '/trash.php' => ['page'], '/download.php' => ['id', 'type'], '/edit-user.php' => ['id'],
        '/communications-count.php' => ['status', 'institution'],
        '/reset-password.php' => ['token'],
        '/manage-journal.php' => ['page', 'type', 'status'], '/edit-journal.php' => ['id'],
        '/journal-preview.php' => ['id'], '/journal-preview-cover.php' => ['id'],
    ];

    public static function publicOrigin(): ?string
    {
        return self::configuredOrigin('PUBLIC_APP_URL');
    }

    private static function configuredOrigin(string $key): ?string
    {
        $url = trim((string)($_ENV[$key] ?? getenv($key) ?: ''));
        $parts = parse_url($url);
        if (filter_var($url, FILTER_VALIDATE_URL) === false || !$parts || !in_array($parts['scheme'] ?? '', ['https'], true) || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment']) || !in_array($parts['path'] ?? '', ['', '/'], true)
            || preg_match('/[\x00-\x20\\\\]/', $url)) {
            return null;
        }
        return rtrim($url, '/');
    }

    public static function localTarget(string $target, bool $allowAuthentication = false): ?string
    {
        if (strlen($target) > 2048 || !str_starts_with($target, '/') || str_starts_with($target, '//')
            || preg_match('/[\x00-\x20\\\\]/', $target)) {
            return null;
        }
        $parts = parse_url($target);
        if (!$parts || isset($parts['host']) || isset($parts['scheme']) || isset($parts['fragment'])) {
            return null;
        }
        $path = $parts['path'] ?? '';
        if (!defined('AMEA_APP') || AMEA_APP !== 'staff' || $path === '/' || !Router::supports('staff', $path, 'GET')
            || (!$allowAuthentication && in_array($path, self::AUTH_PATHS, true))) {
            return null;
        }
        parse_str($parts['query'] ?? '', $query);
        foreach ($query as $key => $value) {
            if (!in_array($key, self::QUERY_KEYS[$path] ?? [], true) || !is_string($value)
                || strlen($value) > 512 || preg_match('/[\x00-\x1f\x7f]/', $value)) {
                return null;
            }
        }
        return $path . ($query ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
    }

    public static function requestedTarget(): ?string
    {
        if (!defined('AMEA_APP') || AMEA_APP !== 'staff' || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
            return null;
        }
        return self::localTarget((string)($_SERVER['REQUEST_URI'] ?? ''));
    }

    public static function loginDestination(Session $session): string
    {
        $target = self::localTarget((string)$session->get('staff_return_to', ''));
        $session->remove('staff_return_to');
        return $target ?? 'dashboard.php';
    }
}
