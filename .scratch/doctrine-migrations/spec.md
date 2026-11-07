# Doctrine Migrations adoption

Status: ready-for-agent

This status describes specification readiness. Implementation and deployment require separate authorization.

## Problem Statement

AMEA's one-shot migration service runs all eight historical PHP scripts on each startup or deployment. The scripts have no shared execution ledger. Some change data as well as schema, so existing tables and columns do not prove which historical changes took effect. Replaying the KYC backfill during adoption can approve locked dossiers still awaiting confirmation.

## Solution

Use Doctrine Migrations as the shared migration runner in local and Dokploy Compose. Let a fresh database initialize automatically. Enroll an existing database explicitly after verifying its schema and migration evidence, preserving business data. Each subsequent startup applies pending versions and blocks dependent services on failure.

## User Stories

1. As a developer, I want a fresh local database to initialize automatically, so that I can start AMEA without applying scripts by hand.
2. As a deployment operator, I want local Compose and Dokploy to use the same runner, so that the rehearsal reflects deployment behavior.
3. As a deployment operator, I want the site, backoffice and optional jobs to wait for migration success, so that they start against a compatible schema.
4. As a maintainer, I want a database-specific version ledger, so that I can see what was applied and what remains pending.
5. As a developer, I want a second startup with no new versions to apply zero migrations, so that historical data operations stay complete.
6. As an operator, I want to explicitly select and verify an existing database, so that enrollment applies to the intended environment.
7. As an operator, I want unknown or partial databases to stop with actionable diagnostics, so that I can investigate before retrying.
8. As a staff user, I want existing review statuses preserved during enrollment, so that dossiers retain their current review state.
9. As a staff user, I want custom settings, journal entries and document references preserved, so that administration can continue after enrollment.
10. As an operator, I want enrollment to record all baseline versions together, so that an interruption cannot leave the ledger partially enrolled.
11. As an operator, I want a migration and its version record to commit together when it changes only data, so that retries see a consistent state.
12. As an operator, I want concurrent runners to be serialized per database, so that they cannot apply the same version twice.
13. As an operator, I want a verified restorable backup and a recovery procedure for existing schema changes, so that I can reconcile interrupted changes safely.
14. As a maintainer, I want to rehearse the transition on a restored demo copy, so that the original demo remains available.

## Implementation Decisions

- Use separate Doctrine baseline versions for initial schema and initial data. Keep the maintained SQL baseline aligned with schema changes. Preserve historical scripts for reference and exclude them from routine Doctrine startup.
- Support fresh initialization, normal migration of an enrolled database, and explicit enrollment of a verified existing database. Ordinary startup refuses an existing database without a complete recorded baseline.
- Verify supported schema variants and use deployment evidence and targeted data checks to establish the baseline. Refuse enrollment when evidence is insufficient. Keep application writers stopped during verification and enrollment. Preserve existing settings and dossier statuses, including those that the historical backfill could otherwise change.
- Serialize enrollment and migration execution against the selected database. Record all historical baseline versions in one InnoDB transaction. Commit each data-only migration and its ledger entry together.
- Provision the ledger separately from enrollment writes. Separate schema changes from transactional data migrations because MariaDB implicitly commits schema statements. Verify schema changes and halt on failure. Require a verified restorable backup before schema changes to an existing database. Reconcile actual schema and ledger state after interruption before retrying.
- Keep the single one-shot service and database readiness gate in both Compose configurations. Preserve database and upload volume identities, shared storage, and dependent service startup behavior.

## Testing Decisions

- Exercise observable behavior through the migration and enrollment commands on MariaDB, plus dependent service startup through Compose. Assert the resulting schema, rows, version ledger, command exit status and service readiness.
- Verify fresh initialization and a zero-change second run. Compare a restored demo copy before and after enrollment, including settings, review statuses, journal entries and document references.
- Test unknown and partial states, enrollment retries, and concurrent runner and enrollment attempts. Inject failures in enrollment and data-only migrations and verify rollback of rows and version records.
- Rehearse a schema change that succeeds before version recording fails. Verify startup blocks and the recovery procedure reconciles the state before retry.
- Extend the existing Docker and recovery rehearsal. Preserve coverage of supported legacy variants and run Doctrine against the MariaDB version used by Compose.

## Out of Scope

Production deployment or restore, changes to the original demo, database or volume separation, changes to membership behavior, and adoption of a PHP framework.

## Further Notes

See [ADR 0002](../../docs/adr/0002-adopt-doctrine-migrations.md) and the [rollout guide](../../docs/operations/public-staff-rollout.md). Confirm the target database and verify recovery readiness before any separately authorized live rollout.

## Implementation Tickets

1. [Initialize a fresh database with Doctrine](issues/01-initialize-fresh-database.md)
2. [Enroll a verified existing database](issues/02-enroll-verified-existing-database.md)
3. [Verify migration concurrency and recovery](issues/03-verify-concurrency-and-recovery.md)
4. [Switch both Compose stacks to Doctrine](issues/04-switch-compose-migration-runner.md)
