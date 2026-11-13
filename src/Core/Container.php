<?php

namespace Amea\Core;

use Amea\Config\Database;
use Amea\Storage\Storage;
use Amea\Storage\StorageFactory;

class Container
{
    private static ?\PDO $pdo = null;
    private static ?Storage $storage = null;

    public static function db(): \PDO
    {
        return self::$pdo ??= Database::fromEnv()->getConnection();
    }

    public static function reset(): void
    {
        self::$pdo = null;
        self::$storage = null;
    }

    public static function storage(): Storage
    {
        return self::$storage ??= StorageFactory::fromEnvironment(dirname(__DIR__, 2));
    }

    public static function setStorage(Storage $storage): void
    {
        self::$storage = $storage;
    }

    public static function setDb(\PDO $pdo): void
    {
        self::$pdo = $pdo;
    }
}
