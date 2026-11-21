# 01 — Introduce local and S3 storage backends

Status: ready-for-agent

Blocked by: None.

## Scope

Implement [plan Task 1](../plan.md#task-1--storage-contract-and-backends) under the [approved design](../spec.md). Provide the shared stream-based storage contract, local/S3 implementations, AWS PHP SDK integration, environment configuration and backend tests.

## Acceptance criteria

- [x] Read/write/stat/delete/copy/paginated listing satisfy the documented shared interface.
- [x] Full relative keys, original lifecycle age and accepted-content SHA-256 are preserved.
- [x] Traversal, symlink escape and conflicting destination keys are rejected.
- [x] Missing objects and storage/permission failures have distinct behavior.
- [x] Local and disposable S3 contract suites cover failure, retry and bounded-memory streaming.
- [x] Public, staff and jobs receive the same explicit backend configuration.
- [x] Existing relevant checks pass and implementation/review/signed delivery evidence is recorded in `completion.md`.

## Comments

2026-11-12 — Planned after design approval. Execution requires user authorization. Provider credentials and actual R2 qualification belong to Ticket 05.

2026-11-13 — Authorized implementation is locally complete in signed commits `53b2d10` and `45d6a35`. Independent task review and scoped re-review verified the copy-integrity correction and shared key validation. See [completion evidence](../completion.md) for backend tests, actual disposable-provider checks, image runtime proof and remaining delivery steps.

2026-11-13 — Whole-branch review additionally identified a truncated destination GET in the identical-retry check. Signed correction `3fed1ba` and its clean scoped review close that finding. Local acceptance criteria are verified. Hosted CI and actual merge remain separately recorded delivery steps.

2026-11-13 — Delivered in [PR 40](https://github.com/ibrahima-isi/amea/pull/40), merged into remote `dev` as `e3fb180`. All four CI jobs passed on exact PR head `7732f15` and again on the actual merge commit. The merged result was integrated into local `main` from an isolated worktree. See [completion evidence](../completion.md) for signed reviews/commits, actual provider/runtime tests, merge and cleanup. Triage status continues to describe specification readiness.
