# Doctrine Migrations adoption

**Status** — Approved design. Implementation and deployment require separate authorization.

## Scope

Replace the eight-script replay in the local and Dokploy Compose stacks with one Doctrine Migrations runner. Keep the database readiness check and require the migration service to succeed before starting the web, backoffice, and optional jobs services. Keep the existing shared database, volume identities, member data, and files.

See [ADR 0002](../../docs/adr/0002-adopt-doctrine-migrations.md) for the decision.

## Fresh database

Create a Doctrine baseline with separate versions for schema creation and initial data. Derive the initial schema from the maintained `schema.sql` and preserve the current defaults added by the legacy installer. The legacy scripts remain available as historical evidence and are excluded from routine startup. Keep `schema.sql` aligned with schema changes and document how an imported schema enters the verified enrollment path.

An empty database initializes automatically. Once the baseline is recorded, subsequent starts run only pending versions. A partially initialized database is blocked until an operator establishes its state and chooses a recovery path.

## Existing database

Require an explicit enrollment operation against a selected database. Verify its schema against documented supported variants and use deployment evidence and targeted data checks to establish that the baseline is already satisfied. Schema shape alone is insufficient. The old settings migration inserts missing defaults while preserving custom values. The old KYC migration can approve locked dossiers still marked as pending.

After verification, record the baseline versions in one InnoDB transaction without replaying the old scripts or changing member or settings data. Block ordinary startup if the existing database lacks a recorded baseline. If verification cannot establish the state, refuse enrollment and report which checks failed. Repeating enrollment after successful registration is safe and does not add duplicate versions.

Serialize enrollment and migration execution against the same database. Keep application writers stopped during existing-database enrollment so that the verification remains valid until the ledger transaction commits.

## Transaction and recovery boundaries

- Enrollment writes and each data-only migration are transactional. A data-only migration and its version record commit together or roll back together.
- Provisioning the ledger and changing the schema are separate from transactional data writes. MariaDB implicitly commits statements such as `CREATE TABLE` and `ALTER TABLE`. A successful schema change can therefore remain applied if writing its version record fails.
- Require a verified, restorable backup before schema changes on an existing database. Check the result of each schema migration and stop on failure. Document how to verify the actual schema and reconcile the ledger after an interruption before retrying. Recovery follows the [rollout guide](../../docs/operations/public-staff-rollout.md) and requires separate approval for a production restore.

## Acceptance criteria

- Both Compose configurations invoke the same Doctrine runner. An unsuccessful migration keeps dependent services from starting.
- A fresh MariaDB database reaches the expected schema and defaults. A second run applies zero migrations.
- A restored copy of the demo can be enrolled. Compare schema compatibility and business data before and after, including review statuses, settings, journal entries, and document references. The original demo is kept available.
- An unrecognized or partially migrated fixture is refused before enrollment. A concurrent runner cannot apply the same version twice.
- Inject a failure during enrollment and a failure during a data-only migration. Verify that no partial versions are recorded and that data changes roll back with the version record. Rehearse recovery after a schema change succeeds but its version is unrecorded.
- Preserve existing CI coverage for the supported database variants and exercise the new runner on the MariaDB version used by Compose.
