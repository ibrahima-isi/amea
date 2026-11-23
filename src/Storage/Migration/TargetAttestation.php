<?php

declare(strict_types=1);

namespace Amea\Storage\Migration;

use Amea\Config\Database;
use Amea\Core\Container;
use Amea\Core\FileWriteGate;
use Amea\Core\Runtime;
use Amea\Storage\StorageFactory;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Generates target attestation reports for paused Docker or native Stellar runtimes.
 */
final class TargetAttestation
{
    /**
     * @param list<string> $argv
     * @return array<string, string>
     */
    public static function parseCliOptions(array $argv): array
    {
        $options = [];
        foreach (array_slice($argv, 1) as $argument) {
            if (!preg_match('/^--(role|config|state-dir|docroot|job-wrapper)=(.+)$/s', $argument, $match)) {
                throw new InvalidArgumentException('Unsupported option.');
            }
            $key = str_replace('-', '_', $match[1]);
            if (isset($options[$key])) {
                throw new InvalidArgumentException('Duplicate option.');
            }
            $options[$key] = $match[2];
        }
        return $options;
    }

    /**
     * @param array<string, string> $options
     * @return array<string, mixed>
     */
    public static function report(string $projectRoot, array $options = [], ?PDO $db = null): array
    {
        $canonicalRoot = realpath($projectRoot);
        if ($canonicalRoot === false || !is_dir($canonicalRoot)) {
            throw new RuntimeException('Application root is missing.');
        }

        if (self::isNativeMode($options)) {
            return self::reportNative($canonicalRoot, $options, $db);
        }

        return self::reportDocker($canonicalRoot, $db);
    }

    /**
     * @param array<string, string> $options
     */
    private static function isNativeMode(array $options): bool
    {
        if ($options !== []) {
            return true;
        }
        if (Runtime::configFilePath() !== null || Runtime::stateDir() !== null) {
            return true;
        }
        return !is_file('/opt/amea-apps/identity') && Runtime::appIdentity() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function reportDocker(string $canonicalRoot, ?PDO $db): array
    {
        $role = trim((string) @file_get_contents('/opt/amea-apps/identity'));
        if (!in_array($role, ['app', 'public', 'staff', 'jobs'], true)) {
            throw new RuntimeException('Run this report inside the selected application image.');
        }
        $sessionPath = $role === 'app' ? session_save_path() : '/var/lib/amea/sessions';
        if ($role === 'app' && (ini_get('session.save_handler') !== 'files' || $sessionPath === '/tmp')) {
            throw new RuntimeException('Single-app attestation requires a dedicated PHP file-session directory.');
        }
        $gate = FileWriteGate::application();
        $state = $gate->status();
        if (empty($state['paused']) || empty($state['drained'])) {
            throw new RuntimeException('Target reports require the persistent completed pause.');
        }
        $pdo = $db ?? Container::db();
        $backend = StorageFactory::fromEnvironment($canonicalRoot);

        return [
            'role' => $role,
            'backend' => Manifest::backendIdentity($backend),
            'application_backend' => StorageFactory::identity($canonicalRoot),
            'database' => ReferenceAudit::databaseIdentity($pdo),
            'gate' => $gate->identity(),
            'code' => Cutover::codeIdentity($canonicalRoot),
            'serializer' => (string) ini_get('session.serialize_handler'),
            'session_path' => $sessionPath,
            'session_store' => $role === 'jobs' ? null : Cutover::sessionStoreIdentity($sessionPath),
            'challenge' => $state['challenge'],
            'observed_at' => microtime(true),
        ];
    }

    /**
     * @param array<string, string> $options
     * @return array<string, mixed>
     */
    private static function reportNative(string $canonicalRoot, array $options, ?PDO $db): array
    {
        $role = trim($options['role'] ?? (Runtime::appIdentity() ?? ''));
        if (!in_array($role, ['public', 'staff', 'jobs'], true)) {
            throw new RuntimeException('Native target attestation requires role public, staff, or jobs.');
        }
        if ($role === 'jobs' && isset($options['docroot'])) {
            throw new RuntimeException('Jobs target attestation does not accept --docroot.');
        }
        if ($role !== 'jobs' && isset($options['job_wrapper'])) {
            throw new RuntimeException('Web target attestation does not accept --job-wrapper.');
        }

        $configInput = trim($options['config'] ?? (Runtime::configFilePath() ?? ''));
        $stateInput = trim($options['state_dir'] ?? (Runtime::stateDir() ?? ''));
        if ($configInput === '' || $stateInput === '') {
            throw new RuntimeException('Native target attestation requires --config and --state-dir.');
        }

        $canonicalConfig = Runtime::loadEnv($canonicalRoot, $configInput, true);
        if ($canonicalConfig === null) {
            throw new RuntimeException('Unable to load private runtime configuration.');
        }

        $_ENV['AMEA_APP'] = $role;
        $_ENV['AMEA_CONFIG_FILE'] = $canonicalConfig;
        $_ENV['AMEA_STATE_DIR'] = $stateInput;
        putenv('AMEA_APP=' . $role);
        putenv('AMEA_CONFIG_FILE=' . $canonicalConfig);
        putenv('AMEA_STATE_DIR=' . $stateInput);

        $canonicalState = Runtime::stateDir();
        if ($canonicalState === null) {
            throw new RuntimeException('Private runtime state directory is required.');
        }

        if (isset($options['docroot'])) {
            self::assertDocrootWiring($options['docroot'], $role, $canonicalRoot, $canonicalConfig, $canonicalState);
        }
        if (isset($options['job_wrapper'])) {
            self::assertJobWrapperWiring($options['job_wrapper'], $canonicalRoot, $canonicalConfig, $canonicalState);
        }

        $gate = FileWriteGate::application();
        $state = $gate->status();
        if (empty($state['paused']) || empty($state['drained'])) {
            throw new RuntimeException('Target reports require the persistent completed pause.');
        }

        $storageRoot = Runtime::storageProjectRoot($canonicalRoot);
        if ($db === null) {
            Database::reset();
            Container::reset();
            $pdo = Container::db();
        } else {
            $pdo = $db;
        }
        $backend = StorageFactory::fromEnvironment($storageRoot);
        $sessionPath = $role === 'jobs' ? null : Runtime::sessionSavePath($role);
        if ($role !== 'jobs' && ($sessionPath === null || $sessionPath === '')) {
            throw new RuntimeException('Dedicated private session directory is required.');
        }

        return [
            'role' => $role,
            'backend' => Manifest::backendIdentity($backend),
            'application_backend' => StorageFactory::identity($storageRoot),
            'database' => ReferenceAudit::databaseIdentity($pdo),
            'gate' => $gate->identity(),
            'code' => Cutover::codeIdentity($canonicalRoot),
            'serializer' => (string) ini_get('session.serialize_handler'),
            'session_path' => $sessionPath,
            'session_store' => $role === 'jobs' ? null : Cutover::sessionStoreIdentity((string) $sessionPath),
            'challenge' => $state['challenge'],
            'observed_at' => microtime(true),
        ];
    }

    private static function assertDocrootWiring(
        string $docroot,
        string $role,
        string $canonicalRoot,
        string $canonicalConfig,
        string $canonicalState
    ): void {
        if (!str_starts_with($docroot, '/') || is_link($docroot)) {
            throw new RuntimeException('Document root must be an absolute non-symlink directory.');
        }
        $canonicalDocroot = realpath($docroot);
        if ($canonicalDocroot === false || !is_dir($canonicalDocroot)) {
            throw new RuntimeException('Document root does not exist.');
        }
        foreach ([$canonicalRoot, $canonicalConfig, $canonicalState] as $privatePath) {
            if (str_starts_with($privatePath, $canonicalDocroot . '/')) {
                throw new RuntimeException('Private runtime paths must stay outside the document root.');
            }
        }

        $entryPath = $canonicalDocroot . '/index.php';
        if (is_link($entryPath) || !is_file($entryPath)) {
            throw new RuntimeException('Document root index.php is missing or invalid.');
        }
        $contents = (string) file_get_contents($entryPath);
        $expectedLines = [
            'require_once ' . self::phpSingleQuoted($canonicalRoot . '/vendor/autoload.php') . ';',
            "define('AMEA_CONFIG_FILE', " . self::phpSingleQuoted($canonicalConfig) . ');',
            "define('AMEA_STATE_DIR', " . self::phpSingleQuoted($canonicalState) . ');',
            '(new \Amea\Core\Router())->dispatch(' . self::phpSingleQuoted($role) . ');',
        ];
        foreach ($expectedLines as $line) {
            if (!str_contains($contents, $line)) {
                throw new RuntimeException('Document root index.php does not match the expected release wiring.');
            }
        }
    }

    private static function assertJobWrapperWiring(
        string $wrapperPath,
        string $canonicalRoot,
        string $canonicalConfig,
        string $canonicalState
    ): void {
        if (!str_starts_with($wrapperPath, '/') || is_link($wrapperPath) || !is_file($wrapperPath)) {
            throw new RuntimeException('Job wrapper must be an absolute non-symlink file.');
        }
        $canonicalWrapper = realpath($wrapperPath);
        if ($canonicalWrapper === false || (fileperms($canonicalWrapper) & 0077) !== 0) {
            throw new RuntimeException('Job wrapper must restrict group and world permissions.');
        }
        $contents = (string) file_get_contents($canonicalWrapper);
        $expectedLines = [
            "define('AMEA_APP', 'jobs');",
            "define('AMEA_CONFIG_FILE', " . self::phpSingleQuoted($canonicalConfig) . ');',
            "define('AMEA_STATE_DIR', " . self::phpSingleQuoted($canonicalState) . ');',
        ];
        foreach ($expectedLines as $line) {
            if (!str_contains($contents, $line)) {
                throw new RuntimeException('Job wrapper does not match the expected private configuration.');
            }
        }
        if (!preg_match("/require '((?:[^'\\\\]|\\\\.)+)' \\. '\\/scripts\\/run-shared-jobs\\.php';/", $contents, $match)) {
            throw new RuntimeException('Job wrapper does not invoke run-shared-jobs.php.');
        }
        $targetBase = stripslashes($match[1]);
        $resolvedTarget = realpath($targetBase);
        if ($resolvedTarget === false || $resolvedTarget !== $canonicalRoot) {
            throw new RuntimeException('Job wrapper does not resolve to the selected release root.');
        }
    }

    private static function phpSingleQuoted(string $value): string
    {
        return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $value) . "'";
    }
}
