# 03 — Integrate public media, reconciliation and jobs

Status: ready-for-agent

Blocked by: 02, verified complete.

## Scope

Implement [plan Task 3](../plan.md#task-3--public-media-reconciliation-and-scheduled-jobs) under the [approved design](../spec.md). Adapt journal and slider delivery, file reconciliation and all scheduled cleanup consumers.

## Acceptance criteria

- [x] Published journal covers, permissioned draft previews and withdrawn-cover denial work through private storage.
- [x] Historical journal covers remain retained after replacement/removal.
- [x] Existing slider URLs work in public/staff applications through validated active records.
- [x] Complete-key paginated reconciliation distinguishes identical basenames under different directories.
- [x] Pending cleanup uses original staging age, including migrated objects.
- [x] A single jobs owner handles cleanup/retries and honors backup requirements.
- [x] Actual browser/HTTP media journeys, reconciliation and job failure/retry checks pass on both backends.
- [x] Reviews, signed delivery and verification limitations are recorded in `completion.md`.

## Comments

2026-11-12 — Depends on the member lifecycle contract and durable cleanup from Ticket 02.

2026-11-13 — Authorized through `implement` for the next ticket. Signed implementation `9b61c04` and correction `5c6165b` passed local/S3 media, reconciliation, retention, jobs and focused Chrome checks. Independent Standards/Spec reviews and scoped re-reviews verified the correction of the generic-retry purge bypass and service/repository separation. One optional reason-identity representation remark remains documented. See [completion evidence](../completion.md) for exact results, browser qualification limits and pending hosted delivery.

2026-11-13 — Delivered through [PR 42](https://github.com/ibrahima-isi/amea/pull/42), merged into `dev` as `f532cd4`. Four CI jobs passed on exact PR head `48dca0c` and again on the actual merge commit. The actual merged result was integrated into local `main` from an isolated worktree. See [completion](../completion.md) for signed source/review, local/S3 browser/runtime proof, qualification limits and cleanup. Triage status retains its readiness meaning.
