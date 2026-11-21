# 06 — Execute the approved production storage transition

Status: ready-for-human

Blocked by: 05, verified complete; explicit approval of the concrete live transition.

## Scope

Execute [plan Task 6](../plan.md#task-6--approved-production-transition) under the [approved design](../spec.md). An operator selects and approves the actual production database, source storage, R2 bucket, backup/recovery point and measured maintenance window.

## Acceptance criteria

- [ ] Merged code, latest-commit CI, actual deployed revision and Ticket 05 qualification are verified.
- [ ] Actual production source/reference inventory is reconciled, and a coordinated backup restores successfully.
- [ ] Backup capacity/cadence, all writers, session persistence and the measured maintenance window are recorded.
- [ ] Explicit approval identifies the live resources and final transition procedure.
- [ ] Final frozen inventory, read-back hashes, guarded corrections and target configuration all agree before reopening.
- [ ] Authorized application access, public media, privacy denials and pre-existing registration staging pass.
- [ ] Post-reopening behavior and ongoing object backup coverage are verified, with actual results in `completion.md`.
- [ ] Local source copies and the associated backup remain available until validation and a separate deletion decision.

## Comments

2026-11-12 — Human-owned because selecting/approving the live resources and executing a production change require explicit operator involvement under AGENTS.md. Design approval and ticket readiness do not constitute that approval.
