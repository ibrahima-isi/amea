<?php

declare(strict_types=1);

namespace Amea\Migration;

use Amea\Migration\Version\Version20261106000000;
use Amea\Migration\Version\Version20261106000001;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\Common\EventManager;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\EventDispatcher;
use Doctrine\Migrations\Events;
use Doctrine\Migrations\Metadata\ExecutedMigration;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

final class MigrationRuntime
{
    public const LEDGER_TABLE = 'doctrine_migration_versions';
    public const RECOVERY_TABLE = 'amea_migration_recovery';
    public const BASELINE_VERSIONS = [
        Version20261106000000::class,
        Version20261106000001::class,
    ];

    public const MEMBER_FILE_VERSIONS = [
        \Amea\Migration\Version\Version20261113000000::class,
        \Amea\Migration\Version\Version20261113000001::class,
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

        $doctrine = DependencyFactory::fromConnection($configuration, new ExistingConnection($connection), $logger);
        $events = new EventManager();
        $events->addEventListener(
            [Events::onMigrationsVersionExecuting, Events::onMigrationsMigrated],
            new SchemaMigrationGuard($connection)
        );
        $doctrine->setService(EventDispatcher::class, new EventDispatcher($connection, $events));

        return new self(
            $connection,
            $doctrine
        );
    }

    public function connection(): Connection
    {
        return $this->connection;
    }

    public function withLock(callable $operation): mixed
    {
        $database = $this->connection->fetchOne(
            'SELECT IF(@@lower_case_table_names = 0, DATABASE(), LOWER(DATABASE()))'
        );
        $lock = 'amea:migrations:' . substr(hash('sha256', $database), 0, 48);
        if ((int) $this->connection->fetchOne('SELECT GET_LOCK(?, 30)', [$lock]) !== 1) {
            throw new RuntimeException("Could not acquire the migration lock for database {$database}. Wait for the other operation and retry.");
        }

        try {
            $result = $operation();
            if ((int) $this->connection->fetchOne('SELECT RELEASE_LOCK(?)', [$lock]) !== 1) {
                throw new RuntimeException("The migration lock for database {$database} was lost.");
            }
            return $result;
        } catch (Throwable $error) {
            // Closing the nonpersistent session releases its lock, even after a failed operation.
            $this->connection->close();
            throw $error;
        }
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
        $this->assertNoInterruptedSchemaMigration();
        $recorded = $this->recordedVersions();
        if (count($this->doctrine->getMigrationStatusCalculator()->getExecutedUnavailableMigrations()) > 0) {
            throw new RuntimeException('The database records unknown migration versions. Review the ledger before migrating.');
        }
        if (array_diff(self::BASELINE_VERSIONS, $recorded) === []) {
            return;
        }

        $objects = $this->connection->fetchFirstColumn('SHOW FULL TABLES');
        foreach (['ROUTINES' => 'ROUTINE_SCHEMA', 'EVENTS' => 'EVENT_SCHEMA'] as $catalog => $schemaColumn) {
            if ((int) $this->connection->fetchOne(
                "SELECT COUNT(*) FROM information_schema.{$catalog} WHERE {$schemaColumn} = BINARY DATABASE()"
            ) > 0) {
                $objects[] = $catalog;
            }
        }
        if ($recorded === [] && array_diff($objects, [self::LEDGER_TABLE, self::RECOVERY_TABLE]) === []) {
            return;
        }

        $database = $this->connection->getDatabase();
        if ($recorded === []) {
            throw new RuntimeException("Database {$database} has existing objects without a recorded baseline. Verify it before enrollment or interrupted migration recovery.");
        }

        throw new RuntimeException("Database {$database} has a partial migration baseline. Reconcile the schema and ledger before retrying.");
    }

    public function assertNoInterruptedSchemaMigration(): void
    {
        if (!in_array(self::RECOVERY_TABLE, $this->connection->fetchFirstColumn('SHOW FULL TABLES'), true)) {
            return;
        }
        $version = $this->connection->fetchOne('SELECT version FROM ' . self::RECOVERY_TABLE . ' LIMIT 1');
        if ($version !== false) {
            throw new RuntimeException("Interrupted schema migration {$version}. Reconcile the schema and ledger using docs/operations/migration-recovery.md before retrying.");
        }
    }
}
