# 05 — Qualify write pause, backups and actual R2 behavior

Status: ready-for-agent

Blocked by: 04, verified complete.

## Scope

Implement [plan Task 5](../plan.md#task-5--write-pause-backup-and-r2-qualification) under the [approved design](../spec.md). Wire the write-admission/draining gate, coordinated backup/restore, full isolated migration rehearsal and dedicated test-bucket R2 qualification.

## Acceptance criteria

- [x] All HTTP/CLI file writers participate in the gate or are explicitly stopped/drained by the procedure.
- [x] Pausing drains in-flight operations, rejects new mutations and survives process/deployment interruption.
- [x] Final checks and reopening verify the current manifest and public/staff/jobs target identities.
- [x] Restored backups include matching DB/object contents, operation ledger, lifecycle timestamps and required session state.
- [x] Rehearsals cover online copy, final reconciliation, pre-reopening recovery and reverse-copy after new uploads.
- [x] Missing post-cutover bytes keep recovery paused with an explicit limitation.
- [x] A dedicated R2 test bucket proves the actual selected client/API operations and streaming verification.
- [x] Existing CI commands, deterministic S3 checks and actual browser journeys pass on the reviewed revision.
- [x] Exact code revision, R2 evidence, measured outage/recovery times, reviews and signed delivery are recorded separately.

## Comments

2026-11-12 — R2 qualification requires a designated test bucket and scoped credentials. Test credentials remain private. Production transition is Ticket 06.


2026-11-15 — Ticket 05 repository/local qualification is implemented on the isolated
`feat/object-storage-05` branch, based on integration revision `6201369`.

- Durable shared admission precedes HTTP session mutation and all scheduled/direct
  file workers. Pause persists before drain, nested admitted work finishes, killed
  pause stays closed and redirect shutdown persists sessions before unlock.
- Completed pause permits existing-session private GET smoke reads with PHP GC and
  cookie emission disabled. Expiry/logout/reset/POST writes remain closed. HTTP
  checks force GC and prove unchanged session bytes/mtime and no new cookie.
- Final-check/resume bind current full-byte verification, immutable JSONL identity,
  finalized checkpoint, fresh actual public/staff/jobs reports, gate/session-volume
  inodes, code/configuration and a matching coordinated DB/session recovery point.
  Cutover requires every source-owned file intent to drain. Privileged purge retry
  retains its explicit verified-backup acknowledgement.
- Coordinated capture/isolated restore retain DB schema/data, ledger ownership,
  complete selected object bytes and original age, both session stores, persistent
  queue/state and private manifest/config identities. Restore rejects an active/
  populated DB or namespace and unrelated existing session/state files. Integrity
  failures keep the target paused. Views/triggers/routines/events fail explicitly.
- Synthetic local/S3 rehearsals cover online additions/source conflict reconciliation,
  local recovery before reopening, new post-cutover member/CV/pending uploads,
  current-state reverse-copy, outage/missing unique bytes, matching source/S3
  backups and restored pre-existing preview/confirmation through actual Chrome.
  Actual baked-role reports and capture/restore/resume-restored CLI paths are covered.
- The full PHPUnit run passed 311 tests / 931 assertions with two opt-in skips and
  ten inherited deprecations. Final affected runs, PHP/JS/HTTP regressions,
  deterministic S3 and both canonical Doctrine Compose startup/recovery checks
  pass. Exact commands, later affected results, private snapshot hashes, measured
  timings and signed revision are in the external task report and root-owned
  feature completion record. Focused storage browser scope avoids the inherited
  general full-load synchronization timeout.

The R2 harness accepts a private dedicated-test configuration, uses fabricated
PDF/PNG bytes under its own random namespace and cleans its exact owned keys.
Actual R2 qualification remains unchecked until the dedicated bucket credentials
are supplied and the harness runs against the frozen signed revision. Independent
integration reviews, remote final-head CI/merge and production transition evidence
remain separately owned. This comment authorizes no production change or Ticket 06
execution.

2026-11-17 — Actual R2 qualification passed on frozen signed revision `ad80af6` against the user-selected `amea-sandbox` test bucket, with synthetic-only data and exact-key cleanup. Provider operations, content digests/age/pagination/retry and actual isolated application download/role checks passed. Global Standards/Spec reviews and scoped recovery-disposition correction reviews are approved. Exact provider receipt, hashes, revision, local measurements and remaining PR/production steps are recorded in [completion](../completion.md). No production tag or live migration occurred.
