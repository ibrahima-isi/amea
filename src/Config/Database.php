<?php
namespace Amea\Config;

class Database
{
    private static ?Database $instance = null;
    private \PDO $pdo;

    private function __construct(string $host, string $name, string $user, string $pass, ?int $port = null)
    {
        $dsn = self::buildDsn($host, $name, $port);
        $this->pdo = new \PDO($dsn, $user, $pass, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $this->pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    public static function buildDsn(string $host, string $name, ?int $port = null): string
    {
        $dsn = "mysql:host={$host}";
        if ($port !== null) {
            $dsn .= ";port={$port}";
        }
        return $dsn . ";dbname={$name};charset=utf8mb4";
    }

    public static function parsePort(string $rawPort): ?int
    {
        $trimmed = trim($rawPort);
        if ($trimmed === '') {
            return null;
        }
        $port = filter_var($trimmed, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        if ($port === false) {
            throw new \RuntimeException('DB_PORT must be a TCP port between 1 and 65535.');
        }
        return (int) $port;
    }

    public static function fromEnv(): self
    {
        if (self::$instance === null) {
            self::$instance = new self(
                $_ENV['DB_HOST'] ?? '',
                $_ENV['DB_NAME'] ?? '',
                $_ENV['DB_USER'] ?? '',
                $_ENV['DB_PASS'] ?? '',
                self::parsePort($_ENV['DB_PORT'] ?? '')
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
