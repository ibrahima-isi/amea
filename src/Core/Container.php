<?php

namespace Amea\Core;

use Amea\Cache\AppCache;
use Amea\Cache\CacheInvalidator;
use Amea\Cache\CloudflareCachePurger;
use Amea\Config\Database;
use Amea\Storage\Storage;
use Amea\Storage\StorageFactory;

class Container
{
    private static ?\PDO $pdo = null;
    private static ?Storage $storage = null;
    private static ?AppCache $cache = null;
    private static ?CloudflareCachePurger $cloudflare = null;

    public static function db(): \PDO
    {
        return self::$pdo ??= Database::fromEnv()->getConnection();
    }

    public static function reset(): void
    {
        self::$pdo = null;
        self::$storage = null;
        self::$cache = null;
        self::$cloudflare = null;
    }

    public static function storage(): Storage
    {
        return self::$storage ??= StorageFactory::fromEnvironment(Runtime::storageProjectRoot(dirname(__DIR__, 2)));
    }

    public static function cacheForConnection(\PDO $pdo): AppCache
    {
        if ($pdo === self::$pdo) {
            return self::cache();
        }
        static $connections;
        $connections ??= new \WeakMap();
        if (!isset($connections[$pdo])) {
            $file = self::sqliteMainFile($pdo);
            $root = dirname(__DIR__, 2);
            $connections[$pdo] = $file
                ? new AppCache(Runtime::appCacheDir($root), 'sqlite:' . (realpath($file) ?: $file), Runtime::writeGateDir($root))
                : AppCache::memoryOnly();
        }
        return $connections[$pdo];
    }

    public static function cache(?string $projectRoot = null): AppCache
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $root = $projectRoot ?? dirname(__DIR__, 2);
        if (self::$pdo !== null && self::isInMemorySqlite(self::$pdo)) {
            return self::$cache = AppCache::memoryOnly('sqlite-memory-' . spl_object_id(self::$pdo));
        }

        $scope = self::cacheScope($root);
        return self::$cache = new AppCache(
            Runtime::appCacheDir($root),
            $scope,
            Runtime::writeGateDir($root)
        );
    }

    public static function setCache(?AppCache $cache): void
    {
        self::$cache = $cache;
    }

    public static function cloudflare(): CloudflareCachePurger
    {
        return self::$cloudflare ??= CloudflareCachePurger::fromEnvironment();
    }

    public static function setCloudflare(?CloudflareCachePurger $cloudflare): void
    {
        self::$cloudflare = $cloudflare;
    }

    public static function cacheInvalidator(?string $projectRoot = null, ?AppCache $cache = null): CacheInvalidator
    {
        return CacheInvalidator::fromEnvironment($cache ?? self::cache($projectRoot), self::cloudflare());
    }

    public static function fileLifecycle(): \Amea\Service\FileLifecycleService
    {
        return new \Amea\Service\FileLifecycleService(self::db(), self::storage(), StorageFactory::identity(dirname(__DIR__, 2)));
    }

    public static function setStorage(Storage $storage): void
    {
        self::$storage = $storage;
    }

    public static function setDb(\PDO $pdo): void
    {
        self::$pdo = $pdo;
        self::$cache = null;
    }

    private static function isInMemorySqlite(\PDO $pdo): bool
    {
        $file = self::sqliteMainFile($pdo);
        return $file === '';
    }

    private static function sqliteMainFile(\PDO $pdo): ?string
    {
        try {
            if ($pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
                return null;
            }
            foreach ($pdo->query('PRAGMA database_list')->fetchAll(\PDO::FETCH_ASSOC) as $entry) {
                if (($entry['name'] ?? '') === 'main') {
                    return trim((string) ($entry['file'] ?? ''));
                }
            }
        } catch (\Throwable) {
            return null;
        }
        return null;
    }

    private static function cacheScope(string $root): string
    {
        if (self::$pdo !== null) {
            $sqliteFile = self::sqliteMainFile(self::$pdo);
            if ($sqliteFile !== null && $sqliteFile !== '') {
                return 'sqlite:' . (realpath($sqliteFile) ?: $sqliteFile);
            }
        }
        $dbHost = Runtime::env('DB_HOST');
        $dbPort = Runtime::env('DB_PORT', '3306');
        $dbName = Runtime::env('DB_NAME');
        if ($dbName !== '') {
            return "mysql:{$dbHost}:{$dbPort}:{$dbName}";
        }
        return 'root:' . (realpath($root) ?: $root);
    }
}
