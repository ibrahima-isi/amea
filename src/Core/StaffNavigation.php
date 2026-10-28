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
    ];

    public static function methods(string $path): array
    {
        if ($path === '/confirm-upgrade.php') {
            return ['POST'];
        }
        $postRoutes = ['/register.php', '/registration-details.php', '/kyc-correction.php', '/accept-cgu.php', '/login.php', '/forgot-password.php', '/reset-password.php', '/students.php', '/edit-student.php', '/kyc-detail.php', '/trash.php', '/manage-slider.php', '/communications.php', '/export.php', '/settings.php', '/users.php', '/add-user.php', '/edit-user.php', '/profile.php'];
        return in_array($path, $postRoutes, true) ? ['GET', 'POST'] : ['GET'];
    }

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
        $allowed = defined('AMEA_STAFF_PATHS') ? AMEA_STAFF_PATHS : [];
        if ($path === '/' || !in_array($path, $allowed, true) || !in_array('GET', self::methods($path), true)
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
