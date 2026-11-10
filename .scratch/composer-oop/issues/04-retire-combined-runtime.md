# 04: Retire the combined runtime and qualify the migration

Status: ready-for-agent

Blocked by: [01](01-split-web-entry-points.md), [02](02-migrate-web-helpers.md), and [03](03-migrate-cli-commands.md).

## What to build

Make the split Compose applications the supported web runtime. Remove the combined launcher and live compatibility code after confirming callers have migrated.

## Acceptance criteria

- [ ] Root HTTP shims, file fallback, combined Docker and Apache setup, and obsolete active helper files are gone. Historical migration support stays confined to its callers.
- [ ] Maintained instructions, development setup, and CI use the new layout. The SQLite test harness remains disposable.
- [ ] PHP, JavaScript, HTTP, split-app and Doctrine Compose rehearsals verify the migration against disposable fixtures. Both Compose stacks preserve existing service layouts and volume identities.
- [ ] Validation and integration evidence is recorded separately from ticket triage, and the work is ready for review through the signed PR to `dev`.
