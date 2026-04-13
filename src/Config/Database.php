<?php
namespace Amea\Config;

class Database
{
    private static ?Database $instance = null;
    private \PDO $pdo;

    private function __construct(string $host, string $name, string $user, string $pass)
    {
        $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
        $this->pdo = new \PDO($dsn, $user, $pass, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $this->pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    public static function fromEnv(): self
    {
        if (self::$instance === null) {
            self::$instance = new self(
                $_ENV['DB_HOST'] ?? '',
                $_ENV['DB_NAME'] ?? '',
                $_ENV['DB_USER'] ?? '',
                $_ENV['DB_PASS'] ?? ''
            );
        }
        return self::$instance;
    }

    public function getConnection(): \PDO
    {
        return $this->pdo;
    }

    /** Reset singleton (useful for testing). */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
