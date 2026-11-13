# 05 — Qualify write pause, backups and actual R2 behavior

Status: ready-for-agent

Blocked by: 04, verified complete.

## Scope

Implement [plan Task 5](../plan.md#task-5--write-pause-backup-and-r2-qualification) under the [approved design](../spec.md). Wire the write-admission/draining gate, coordinated backup/restore, full isolated migration rehearsal and dedicated test-bucket R2 qualification.

## Acceptance criteria

- [ ] All HTTP/CLI file writers participate in the gate or are explicitly stopped/drained by the procedure.
- [ ] Pausing drains in-flight operations, rejects new mutations and survives process/deployment interruption.
- [ ] Final checks and reopening verify the current manifest and public/staff/jobs target identities.
- [ ] Restored backups include matching DB/object contents, operation ledger, lifecycle timestamps and required session state.
- [ ] Rehearsals cover online copy, final reconciliation, pre-reopening recovery and reverse-copy after new uploads.
- [ ] Missing post-cutover bytes keep recovery paused with an explicit limitation.
- [ ] A dedicated R2 test bucket proves the actual selected client/API operations and streaming verification.
- [ ] Existing CI commands, deterministic S3 checks and actual browser journeys pass on the reviewed revision.
- [ ] Exact code revision, R2 evidence, measured outage/recovery times, reviews and signed delivery are recorded separately.

## Comments

2026-11-12 — R2 qualification requires a designated test bucket and scoped credentials. Test credentials remain private. Production transition is Ticket 06.
