# 03: Verify migration concurrency and recovery

Status: ready-for-agent

Blocked by: [01 — Initialize a fresh database with Doctrine](01-initialize-fresh-database.md).

## What to build

Prove that two migration attempts against the same database cannot apply a version twice and that an operator can recover from a failure at either side of the schema and ledger boundary. Ticket 02 owns the enrollment command. Ticket 04 verifies both commands together.

## Acceptance criteria

- [ ] Concurrent migration attempts against the same database are serialized and each version is applied at most once.
- [ ] An injected failure in a data-only migration rolls back both business data and its version record.
- [ ] A rehearsal interrupts execution after a schema change succeeds but before its version is recorded. The runner reports failure, and the documented recovery procedure verifies the real schema and reconciles the ledger before retry.
- [ ] The procedure separates schema changes from transactional data writes and requires a verified restorable backup before schema changes to an existing database.

## Comments
