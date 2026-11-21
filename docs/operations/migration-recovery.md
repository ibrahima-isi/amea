# Doctrine migration recovery

Use the explicitly selected `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` and `DB_PASS` command environment. `php scripts/migrate.php status` reports the Doctrine ledger. It does not establish that an interrupted schema migration completed.

## Before an existing database schema change

Stop application writers and jobs. Make a consistent database and private-file backup, restore it into an isolated database and upload location, and verify the restored schema, representative business rows and document references. Record the backup identity, selected database, restore result and migration versions in the execution evidence.

After verifying the restore, acknowledge that backup for one invocation:

```sh
AMEA_SCHEMA_BACKUP_CONFIRMED=1 php scripts/migrate.php migrate
```

For Compose, pass the acknowledgement through a single `run --rm -e AMEA_SCHEMA_BACKUP_CONFIRMED=1 migrate` invocation after stopping writers. Do not save it in `.env`, a Compose service environment or a deployment template. Fresh initialization does not need this acknowledgement; transactional data-only migrations do not need it.

## Transaction and interruption boundaries

Keep each schema change in a migration whose `isTransactional()` returns `false`. Keep data-only changes in separate transactional migrations, using InnoDB tables. MariaDB implicitly commits schema statements; a transaction cannot undo that DDL. Transactional migrations must not contain schema statements or nontransactional table writes.

The runner holds a database-specific MariaDB advisory lock across validation, version execution and version recording. It commits a row in `amea_migration_recovery` before each nontransactional version starts, including future versions. Failure to persist that marker prevents execution. Markers are removed only after the complete migration plan succeeds and its schema versions are recorded. An error, process termination or failed marker cleanup leaves the marker in place. Migration and enrollment commands refuse further changes until recovery, even if the ledger already contains the version. A later data-only failure can therefore retain the marker of an earlier completed schema version.

Data-only migrations and their version records commit together. When a data-only migration fails, confirm its data and version rolled back before retrying. A failed schema version requires the procedure below; do not replay it based only on an absent ledger row.

## Reconcile interrupted schema execution

1. Keep writers and jobs stopped. Preserve the failure output, backup identity, migration source revision, `SHOW CREATE TABLE` results, recovery rows and ledger rows. Confirm the selected server/database explicitly.
2. Prepare a reviewed, version-specific recovery script outside the web root. Load the locked Composer dependencies, create `MigrationRuntime::fromEnvironment()`, and perform **all** inspection and repair inside its `withLock()` callback. Use the same lock for the whole operation; a separate SQL session does not hold this lock for the script.
3. Inspect every marked version against its exact source and the real schema: columns, types, nullability, defaults, indexes, constraints and any other effects. Check the ledger independently. A marker alone, a table name, or the presence of one column is insufficient evidence for a multi-statement migration.
4. If execution is partial or uncertain, restore the verified backup into the selected recovery target, verify schema/data/files and ledger consistency, then retry from that restored state. Alternatively, use a reviewed repair that completes or reverses **all** effects and verifies the resulting state. Do not clear a marker just to bypass the refusal.
5. If all effects are verified complete, reconcile under the lock in one InnoDB transaction: insert the exact version into `doctrine_migration_versions` only if absent, then delete only that verified version's recovery row. Preserve an already recorded version. An unrelated or unknown marker remains a blocker.
6. Re-run `status` and `migrate`, inspect their exit codes and verify the resulting schema and business data. A fully reconciled version must produce a zero-change retry. Start dependent services only after successful migration.

If fresh initialization was interrupted after the schema baseline but before the initial-data baseline completed, schema reconciliation alone still leaves a partial baseline. Ordinary startup intentionally refuses this state. Restore/recreate the disposable database, or perform a separately reviewed completion of the initial-data version and its ledger record after inspecting the actual data. Do not falsely mark the initial-data version complete merely because the tables exist. Enrollment of an existing database has its own evidence requirements and is not an interruption bypass.

The single-statement synthetic fixture `Version20990101000001` illustrates step 5. Its complete schema effect is exactly `settings.recovery_note VARCHAR(30) NULL DEFAULT NULL`. Within `withLock()`, the rehearsal checks the exact marker, column definition and unchanged prior ledger before inserting the missing version and deleting the marker transactionally. `Version20990101000002` exercises the other side: its schema and ledger already completed, so verification permits deleting its marker without inserting a duplicate version. Real migration verification must cover that migration's complete effects.

## Disposable MariaDB rehearsal

Create an **empty disposable** database named `amea_doctrine_recovery` on MariaDB 10.11, with a user allowed to create tables/triggers and inspect its database processes. Select it through the command environment, then run:

```sh
APP_ENV=test DB_NAME=amea_doctrine_recovery php tests/apps/doctrine-recovery.php
```

The test exercises the real migration CLI, two simultaneous runners, transactional rollback at a failing version write, backup refusal, durable interruption after successful DDL, verified reconciliation, marker-write failure before DDL and marker-cleanup failure after a committed ledger row. Test versions live only in a disposable source copy; routine migration discovery never sees them. The database is intentionally retained for inspection. Recreate only that disposable database before repeating the test.
