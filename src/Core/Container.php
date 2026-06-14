<?php

namespace Amea\Core;

use Amea\Config\Database;

class Container
{
    private static ?\PDO $pdo = null;

    public static function db(): \PDO
    {
        return self::$pdo ??= Database::fromEnv()->getConnection();
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }

    public static function setDb(\PDO $pdo): void
    {
        self::$pdo = $pdo;
    }
}
