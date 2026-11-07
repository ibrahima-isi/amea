# 04: Switch both Compose stacks to Doctrine

Status: ready-for-agent

Blocked by: [02 — Enroll a verified existing database](02-enroll-verified-existing-database.md) and [03 — Verify migration concurrency and recovery](03-verify-concurrency-and-recovery.md).

## What to build

Use the same Doctrine runner in the local and Dokploy one-shot services. Rehearse a successful startup and prove that a failed migration blocks dependent services.

## Acceptance criteria

- [ ] Both Compose stacks wait for database readiness and require a successful migration before starting the public website, administration and optional jobs.
- [ ] Startup uses Doctrine and excludes the historical scripts from routine execution. Existing volume identities and shared storage are preserved.
- [ ] Integration verifies fresh initialization, zero-change startup after enrollment of a restored demo copy, and startup refusal after a failed migration or for an unrecognized database.
- [ ] Competing enrollment and migration attempts share the same database lock. Injected enrollment failure leaves no partially recorded baseline versions.
- [ ] The Docker and recovery rehearsal covers the Compose MariaDB version and preserves supported legacy database coverage. The production deployment remains separately authorized.

## Comments
