<?php

declare(strict_types=1);

namespace Amea\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\Migrations\Event\MigrationsVersionEventArgs;
use Doctrine\Migrations\Event\MigrationsEventArgs;
use RuntimeException;

/** Keep an autocommitted marker across the nontransactional schema/ledger boundary. */
final class SchemaMigrationGuard
{
    private ?bool $existingDatabase = null;

    public function __construct(private readonly Connection $connection)
    {
    }

    public function onMigrationsVersionExecuting(MigrationsVersionEventArgs $event): void
    {
        if ($event->getPlan()->getMigration()->isTransactional() || $event->getMigratorConfiguration()->isDryRun()) {
            return;
        }

        $this->existingDatabase ??= array_diff(
            $this->connection->fetchFirstColumn('SHOW FULL TABLES'),
            [MigrationRuntime::LEDGER_TABLE, MigrationRuntime::RECOVERY_TABLE]
        ) !== [];
        if ($this->existingDatabase
            && getenv('AMEA_SCHEMA_BACKUP_CONFIRMED') !== '1') {
            throw new RuntimeException('Schema changes to an existing database require a verified restorable backup. Set AMEA_SCHEMA_BACKUP_CONFIRMED=1 for this single invocation after verification.');
        }
        if ($this->connection->isTransactionActive()) {
            throw new RuntimeException('Schema migration recovery markers require an autocommit connection.');
        }
        $this->connection->executeStatement('CREATE TABLE IF NOT EXISTS ' . MigrationRuntime::RECOVERY_TABLE . ' (
            version VARCHAR(191) NOT NULL PRIMARY KEY,
            started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB');
        $this->connection->insert(MigrationRuntime::RECOVERY_TABLE, ['version' => (string) $event->getPlan()->getVersion()]);
    }

    public function onMigrationsMigrated(MigrationsEventArgs $event): void
    {
        if ($event->getMigratorConfiguration()->isDryRun()) {
            return;
        }

        // The whole plan completed. Failures (including later data migrations) retain markers.
        foreach ($event->getPlan()->getItems() as $plan) {
            if ($plan->getMigration()->isTransactional()) {
                continue;
            }
            $version = (string) $plan->getVersion();
            if (!$this->connection->fetchOne('SELECT version FROM ' . MigrationRuntime::LEDGER_TABLE . ' WHERE version = ?', [$version])) {
                throw new RuntimeException("Schema migration {$version} has no recorded version. Reconcile the schema and ledger before retrying.");
            }
            $this->connection->delete(MigrationRuntime::RECOVERY_TABLE, ['version' => $version]);
        }
    }
}
