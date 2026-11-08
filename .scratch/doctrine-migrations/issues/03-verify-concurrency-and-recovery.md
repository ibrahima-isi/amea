# 03: Verify migration concurrency and recovery

Status: ready-for-agent

Blocked by: [01 — Initialize a fresh database with Doctrine](01-initialize-fresh-database.md).

## What to build

Prove that two migration attempts against the same database cannot apply a version twice and that an operator can recover from a failure at either side of the schema and ledger boundary. Ticket 02 owns the enrollment command. Ticket 04 verifies both commands together.

## Acceptance criteria

- [x] Concurrent migration attempts against the same database are serialized and each version is applied at most once.
- [x] An injected failure in a data-only migration rolls back both business data and its version record.
- [x] A rehearsal interrupts execution after a schema change succeeds but before its version is recorded. The runner reports failure, and the documented recovery procedure verifies the real schema and reconciles the ledger before retry.
- [x] The procedure separates schema changes from transactional data writes and requires a verified restorable backup before schema changes to an existing database.

## Comments

2026-11-08: Acceptance criteria verified on the local integration branch. See [completion evidence](../completion.md) for implementation, disposable MariaDB/Compose rehearsals, reviews and delivery limits. Triage status remains specification readiness; no hosted CI, remote merge or production deployment is claimed.
