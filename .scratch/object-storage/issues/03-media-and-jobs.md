# 03 — Integrate public media, reconciliation and jobs

Status: ready-for-agent

Blocked by: 02, verified complete.

## Scope

Implement [plan Task 3](../plan.md#task-3--public-media-reconciliation-and-scheduled-jobs) under the [approved design](../spec.md). Adapt journal and slider delivery, file reconciliation and all scheduled cleanup consumers.

## Acceptance criteria

- [ ] Published journal covers, permissioned draft previews and withdrawn-cover denial work through private storage.
- [ ] Historical journal covers remain retained after replacement/removal.
- [ ] Existing slider URLs work in public/staff applications through validated active records.
- [ ] Complete-key paginated reconciliation distinguishes identical basenames under different directories.
- [ ] Pending cleanup uses original staging age, including migrated objects.
- [ ] A single jobs owner handles cleanup/retries and honors backup requirements.
- [ ] Actual browser/HTTP media journeys, reconciliation and job failure/retry checks pass on both backends.
- [ ] Reviews, signed delivery and verification limitations are recorded in `completion.md`.

## Comments

2026-11-12 — Depends on the member lifecycle contract and durable cleanup from Ticket 02.
