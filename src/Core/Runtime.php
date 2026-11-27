<?php

declare(strict_types=1);

namespace Amea\Core;

use RuntimeException;

/**
 * Resolves private runtime configuration and persistent state paths.
 *
 * Native split deployments configure AMEA_CONFIG_FILE and AMEA_STATE_DIR outside
 * both document roots. Docker and local checkouts retain their existing defaults
 * whenever those native settings are absent.
 */
final class Runtime
{
    public const VALID_APP_IDENTITIES = ['public', 'staff', 'jobs', 'app'];

    public static function env(string $key, string $default = ''): string
    {
        if (array_key_exists($key, $_ENV) && $_ENV[$key] !== null) {
            return (string) $_ENV[$key];
        }
        if (array_key_exists($key, $_SERVER) && $_SERVER[$key] !== null) {
            return (string) $_SERVER[$key];
        }
        $process = getenv($key);
        if ($process !== false) {
            return (string) $process;
        }
        return $default;
    }

    public static function appIdentity(): ?string
    {
        if (defined('AMEA_APP')) {
            $identity = trim((string) AMEA_APP);
            return in_array($identity, self::VALID_APP_IDENTITIES, true) ? $identity : null;
        }
        $envIdentity = trim(self::env('AMEA_APP'));
        if ($envIdentity !== '' && in_array($envIdentity, self::VALID_APP_IDENTITIES, true)) {
            return $envIdentity;
        }
        if (is_file('/opt/amea-apps/identity')) {
            $dockerIdentity = trim((string) @file_get_contents('/opt/amea-apps/identity'));
            return in_array($dockerIdentity, self::VALID_APP_IDENTITIES, true) ? $dockerIdentity : null;
        }
        return null;
    }

    public static function configFilePath(): ?string
    {
        $configured = defined('AMEA_CONFIG_FILE')
            ? trim((string) AMEA_CONFIG_FILE)
            : trim(self::env('AMEA_CONFIG_FILE'));
        return $configured !== '' ? $configured : null;
    }

    /**
     * Loads runtime environment variables from an explicit private config file
     * or falls back to $projectRoot/.env when no explicit config file is set.
     *
     * Existing process environment / $_ENV entries take precedence unless
     * $overrideExisting is true, so per-invocation overrides (such as backup
     * confirmations or test harnesses) remain effective.
     */
    public static function loadEnv(
        string $projectRoot,
        ?string $explicitFile = null,
        bool $overrideExisting = false
    ): ?string {
        $selected = ($explicitFile !== null && trim($explicitFile) !== '')
            ? trim($explicitFile)
            : self::configFilePath();

        if ($selected !== null) {
            $canonical = self::assertPrivateConfigFile($selected);
            self::parseAndApplyEnvFile($canonical, $overrideExisting, true);
            self::deriveAppOrigin();
            return $canonical;
        }

        $fallback = rtrim($projectRoot, '/') . '/.env';
        if (is_file($fallback)) {
            self::parseAndApplyEnvFile($fallback, $overrideExisting, false);
            self::deriveAppOrigin();
            return realpath($fallback) ?: $fallback;
        }

        self::deriveAppOrigin();
        return null;
    }

    public static function stateDir(): ?string
    {
        $raw = defined('AMEA_STATE_DIR')
            ? trim((string) AMEA_STATE_DIR)
            : trim(self::env('AMEA_STATE_DIR'));
        if ($raw === '') {
            return null;
        }
        return self::ensurePrivateDirectory($raw, false);
    }

    /**
     * Returns the session save directory for the given or current app identity.
     *
     * Native split deployments isolate public and staff sessions into separate
     * private subdirectories under AMEA_STATE_DIR (or explicit per-app overrides).
     * Docker retains /var/lib/amea/sessions inside each isolated container.
     */
    public static function sessionSavePath(?string $appIdentity = null): ?string
    {
        $role = $appIdentity ?? self::appIdentity();
        if ($role === 'jobs') {
            return null;
        }

        $explicit = '';
        if ($role === 'public') {
            $explicit = trim(self::constantOrEnv('AMEA_PUBLIC_SESSION_PATH'));
        } elseif ($role === 'staff') {
            $explicit = trim(self::constantOrEnv('AMEA_STAFF_SESSION_PATH'));
        }
        if ($explicit === '') {
            $explicit = trim(self::constantOrEnv('AMEA_SESSION_SAVE_PATH'));
        }
        if ($explicit !== '') {
            return self::ensurePrivateSessionDirectory($explicit);
        }

        $stateDir = self::stateDir();
        if ($stateDir !== null && in_array($role, ['public', 'staff'], true)) {
            return self::ensurePrivateSessionDirectory($stateDir . '/sessions/' . $role);
        }

        if (in_array($role, ['public', 'staff'], true)) {
            return '/var/lib/amea/sessions';
        }

        return null;
    }

    /**
     * Returns the Twig cache directory. Native deployments isolate caches per
     * entry point so public and staff never share compiled template files.
     */
    public static function twigCacheDir(string $projectRoot, ?string $appIdentity = null): string
    {
        $role = $appIdentity ?? (defined('AMEA_APP') ? (string) AMEA_APP : null);
        $explicit = trim(self::constantOrEnv('AMEA_TWIG_CACHE_DIR'));
        if ($explicit !== '') {
            $base = rtrim($explicit, '/');
            return in_array($role, ['public', 'staff'], true) ? $base . '/' . $role : $base;
        }

        $stateDir = self::stateDir();
        if ($stateDir !== null) {
            $bucket = in_array($role, ['public', 'staff', 'jobs'], true) ? $role : 'app';
            return $stateDir . '/cache/twig/' . $bucket;
        }

        return rtrim($projectRoot, '/') . '/storage/cache/twig';
    }

    /**
     * Returns the shared application cache directory across public, staff, and jobs
     * so staff mutations immediately invalidate public origin caches.
     */
    public static function appCacheDir(string $projectRoot): string
    {
        $explicit = trim(self::constantOrEnv('AMEA_APP_CACHE_DIR'));
        if ($explicit !== '') {
            return rtrim($explicit, '/');
        }

        $stateDir = self::stateDir();
        if ($stateDir !== null) {
            return $stateDir . '/cache/app';
        }

        return rtrim($projectRoot, '/') . '/storage/app-cache';
    }

    public static function writeGateDir(string $projectRoot): string
    {
        $explicit = trim(self::env('FILE_WRITE_GATE_PATH'));
        if ($explicit !== '') {
            return $explicit;
        }
        $stateDir = self::stateDir();
        if ($stateDir !== null) {
            return $stateDir . '/write-gate';
        }
        return rtrim($projectRoot, '/') . '/storage/write-gate';
    }

    public static function mailQueueDir(string $projectRoot): string
    {
        $explicit = trim(self::constantOrEnv('AMEA_MAIL_QUEUE_DIR'));
        if ($explicit !== '') {
            return $explicit;
        }
        $stateDir = self::stateDir();
        if ($stateDir !== null) {
            return $stateDir . '/mail-queue';
        }
        return rtrim($projectRoot, '/') . '/storage/mail-queue';
    }

    public static function jobsLockPath(string $projectRoot): string
    {
        $explicit = trim(self::constantOrEnv('AMEA_JOBS_LOCK_PATH'));
        if ($explicit !== '') {
            return $explicit;
        }
        $stateDir = self::stateDir();
        if ($stateDir !== null) {
            return $stateDir . '/.shared-jobs.lock';
        }
        return rtrim($projectRoot, '/') . '/storage/.shared-jobs.lock';
    }

    public static function logDir(string $projectRoot): string
    {
        $explicit = trim(self::constantOrEnv('AMEA_LOG_DIR'));
        if ($explicit !== '') {
            return $explicit;
        }
        $stateDir = self::stateDir();
        if ($stateDir !== null) {
            return $stateDir . '/logs';
        }
        return rtrim($projectRoot, '/') . '/logs';
    }

    public static function storageProjectRoot(string $projectRoot): string
    {
        $explicit = trim(self::constantOrEnv('AMEA_STORAGE_ROOT'));
        if ($explicit !== '') {
            return $explicit;
        }
        $stateDir = self::stateDir();
        if ($stateDir !== null) {
            return $stateDir;
        }
        return $projectRoot;
    }

    private static function constantOrEnv(string $key): string
    {
        if (defined($key)) {
            return (string) constant($key);
        }
        return self::env($key);
    }

    private static function assertPrivateConfigFile(string $path): string
    {
        if (!str_starts_with($path, '/') || is_link($path)) {
            throw new RuntimeException('Private runtime configuration requires an absolute non-symlink file path.');
        }
        clearstatcache(true, $path);
        $canonical = realpath($path);
        if ($canonical === false || !is_file($canonical) || !is_readable($canonical)) {
            throw new RuntimeException('Private runtime configuration file is missing or unreadable.');
        }
        if ((fileperms($canonical) & 0077) !== 0) {
            throw new RuntimeException('Private runtime configuration file must restrict group and world permissions (0600).');
        }
        return $canonical;
    }

    private static function ensurePrivateSessionDirectory(string $path): string
    {
        $canonical = self::ensurePrivateDirectory($path, true);
        if ($canonical === '/tmp' || $canonical === '/private/tmp') {
            throw new RuntimeException('Session storage requires a dedicated private directory outside /tmp.');
        }
        return $canonical;
    }

    private static function ensurePrivateDirectory(string $directory, bool $strictMode): string
    {
        if (!str_starts_with($directory, '/') || is_link($directory)) {
            throw new RuntimeException('Runtime state directories require an absolute non-symlink path.');
        }
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create private runtime directory.');
        }
        clearstatcache(true, $directory);
        $canonical = realpath($directory);
        if ($canonical === false || !is_dir($canonical) || is_link($directory)) {
            throw new RuntimeException('Runtime state directory is inaccessible.');
        }
        if ($strictMode && (fileperms($canonical) & 0077) !== 0) {
            throw new RuntimeException('Private runtime directory must restrict group and world permissions (0700).');
        }
        return $canonical;
    }

    private static function parseAndApplyEnvFile(string $path, bool $overrideExisting, bool $exportProcessEnv): void
    {
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            throw new RuntimeException('Unable to read runtime configuration file.');
        }

        foreach ($lines as $line) {
            $trimmed = ltrim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$rawKey, $rawValue] = explode('=', $line, 2);
            $key = trim($rawKey);
            if ($key === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
                continue;
            }
            $value = trim($rawValue);

            $alreadySet = array_key_exists($key, $_ENV) || getenv($key) !== false;
            if (!$overrideExisting && $alreadySet) {
                if (!array_key_exists($key, $_ENV) && getenv($key) !== false) {
                    $_ENV[$key] = (string) getenv($key);
                }
                continue;
            }

            $_ENV[$key] = $value;
            if ($exportProcessEnv) {
                putenv($key . '=' . $value);
            }
        }
    }

    private static function deriveAppOrigin(): void
    {
        if (!defined('AMEA_APP')) {
            return;
        }
        $currentAppUrl = trim(self::env('APP_URL'));
        if ($currentAppUrl !== '') {
            return;
        }
        $originKey = match (AMEA_APP) {
            'public' => 'PUBLIC_APP_URL',
            'staff' => 'STAFF_APP_URL',
            default => null,
        };
        if ($originKey === null) {
            return;
        }
        $origin = trim(self::env($originKey));
        if ($origin !== '') {
            $_ENV['APP_URL'] = $origin;
            putenv('APP_URL=' . $origin);
        }
    }
}
