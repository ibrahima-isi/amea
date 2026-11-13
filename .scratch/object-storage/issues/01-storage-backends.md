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
- [ ] Existing relevant checks pass and implementation/review/signed delivery evidence is recorded in `completion.md`.

## Comments

2026-11-12 — Planned after design approval. Execution requires user authorization. Provider credentials and actual R2 qualification belong to Ticket 05.

2026-11-13 — Authorized implementation is locally complete in signed commits `53b2d10` and `45d6a35`. Independent task review and scoped re-review verified the copy-integrity correction and shared key validation. See [completion evidence](../completion.md) for backend tests, actual disposable-provider checks, image runtime proof and remaining delivery steps.
