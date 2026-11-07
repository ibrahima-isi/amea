# 01: Initialize a fresh database with Doctrine

Status: ready-for-agent

Blocked by: None.

## What to build

Run the Doctrine migration command against a disposable empty MariaDB database. Establish the expected AMEA schema and initial settings, then show that repeating the command applies zero migrations. Keep the current Compose entry points available until the cutover ticket.

## Acceptance criteria

- [ ] The runner initializes an empty MariaDB database using separate schema and data versions.
- [ ] The resulting schema matches the maintained SQL baseline and the initial settings match the legacy installer defaults.
- [ ] Doctrine reports the recorded versions and applies no migrations on a second invocation.
- [ ] The isolated rehearsal uses the MariaDB version used by both Compose stacks. Existing supported database variant coverage still passes.

## Comments
