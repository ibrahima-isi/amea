# 01: Initialize a fresh database with Doctrine

Status: ready-for-agent

Blocked by: None.

## What to build

Run the Doctrine migration command against a disposable empty MariaDB database. Establish the expected AMEA schema and initial settings, then show that repeating the command applies zero migrations. Keep the current Compose entry points available until the cutover ticket.

## Acceptance criteria

- [x] The runner initializes an empty MariaDB database using separate schema and data versions.
- [x] The resulting schema matches the maintained SQL baseline and the initial settings match the legacy installer defaults.
- [x] Doctrine reports the recorded versions and applies no migrations on a second invocation.
- [x] The isolated rehearsal uses the MariaDB version used by both Compose stacks. Existing supported database variant coverage still passes.

## Comments

2026-11-08: Acceptance criteria verified on the local integration branch. See [completion evidence](../completion.md) for implementation, disposable MariaDB/Compose rehearsals, reviews and delivery limits. Triage status remains specification readiness; no hosted CI, remote merge or production deployment is claimed.
