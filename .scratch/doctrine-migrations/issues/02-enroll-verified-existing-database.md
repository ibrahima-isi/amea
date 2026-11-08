# 02: Enroll a verified existing database

Status: ready-for-agent

Blocked by: [01 — Initialize a fresh database with Doctrine](01-initialize-fresh-database.md).

## What to build

Give operators an explicit way to enroll an existing AMEA database after checking its schema and migration evidence. Demonstrate enrollment against a restored copy of the demo.

## Acceptance criteria

- [x] Enrollment requires explicit database selection, stopped application writers, supported schema checks, and migration evidence with targeted data checks.
- [x] Enrollment records all baseline versions in one InnoDB transaction. Repeating it adds no duplicate versions.
- [x] A before-and-after comparison on the restored demo copy confirms settings, dossier statuses, journal entries and document references are preserved. Historical scripts are never replayed during enrollment.
- [x] Unknown or partial states are refused before any version is recorded, with diagnostic output identifying the failed checks. Ordinary Doctrine startup refuses an existing database without a complete baseline.
- [x] The operator procedure explains how to verify a database imported from the maintained SQL baseline. Rehearsals use isolated databases and preserve the original demo.

## Comments

2026-11-08: Acceptance criteria verified on the local integration branch. See [completion evidence](../completion.md) for implementation, disposable MariaDB/Compose rehearsals, reviews and delivery limits. Triage status remains specification readiness; no hosted CI, remote merge or production deployment is claimed.
