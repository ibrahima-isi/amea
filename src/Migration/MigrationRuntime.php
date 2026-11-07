<?php

declare(strict_types=1);

namespace Amea\Migration;

use Amea\Migration\Version\Version20261106000000;
use Amea\Migration\Version\Version20261106000001;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Metadata\ExecutedMigration;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class MigrationRuntime
{
    public const LEDGER_TABLE = 'doctrine_migration_versions';
    public const BASELINE_VERSIONS = [
        Version20261106000000::class,
        Version20261106000001::class,
    ];

    private function __construct(
        private readonly Connection $connection,
        public readonly DependencyFactory $doctrine
    ) {
    }

    public static function fromEnvironment(?LoggerInterface $logger = null): self
    {
        $host = getenv('DB_HOST');
        $database = getenv('DB_NAME');
        $user = getenv('DB_USER');
        $password = getenv('DB_PASS');
        $port = getenv('DB_PORT');

        if (!$host || !$database || !$user || $password === false) {
            throw new RuntimeException('Set DB_HOST, DB_NAME, DB_USER and DB_PASS in the migration command environment.');
        }
        if ($port !== false && filter_var($port, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]) === false) {
            throw new RuntimeException('DB_PORT must be a TCP port between 1 and 65535.');
        }

        $connection = DriverManager::getConnection([
            'driver' => 'pdo_mysql',
            'host' => $host,
            'port' => $port === false ? 3306 : (int) $port,
            'dbname' => $database,
            'user' => $user,
            'password' => $password,
            'charset' => 'utf8mb4',
        ]);
        $configuration = new ConfigurationArray([
            'migrations_paths' => [__NAMESPACE__ . '\\Version' => __DIR__ . '/Version'],
            'table_storage' => ['table_name' => self::LEDGER_TABLE],
            'all_or_nothing' => false,
        ]);

        return new self(
            $connection,
            DependencyFactory::fromConnection($configuration, new ExistingConnection($connection), $logger)
        );
    }

    public function connection(): Connection
    {
        return $this->connection;
    }

    /** @return list<string> */
    public function recordedVersions(): array
    {
        return array_map(
            static fn (ExecutedMigration $migration): string => (string) $migration->getVersion(),
            $this->doctrine->getMetadataStorage()->getExecutedMigrations()->getItems()
        );
    }

    public function assertCanMigrate(): void
    {
        $recorded = $this->recordedVersions();
        if (count($this->doctrine->getMigrationStatusCalculator()->getExecutedUnavailableMigrations()) > 0) {
            throw new RuntimeException('The database records unknown migration versions. Review the ledger before migrating.');
        }
        if (array_diff(self::BASELINE_VERSIONS, $recorded) === []) {
            return;
        }

        $objects = $this->connection->fetchFirstColumn('SHOW FULL TABLES');
        if ($recorded === [] && array_diff($objects, [self::LEDGER_TABLE]) === []) {
            return;
        }

        $database = $this->connection->getDatabase();
        if ($recorded === []) {
            throw new RuntimeException("Database {$database} has existing objects without a recorded baseline. Verify it before enrollment or interrupted migration recovery.");
        }

        throw new RuntimeException("Database {$database} has a partial migration baseline. Reconcile the schema and ledger before retrying.");
    }
}
