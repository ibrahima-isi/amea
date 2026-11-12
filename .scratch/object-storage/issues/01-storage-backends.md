# 01 — Introduce local and S3 storage backends

Status: ready-for-agent

Blocked by: None.

## Scope

Implement [plan Task 1](../plan.md#task-1--storage-contract-and-backends) under the [approved design](../spec.md). Provide the shared stream-based storage contract, local/S3 implementations, AWS PHP SDK integration, environment configuration and backend tests.

## Acceptance criteria

- [ ] Read/write/stat/delete/copy/paginated listing satisfy the documented shared interface.
- [ ] Full relative keys, original lifecycle age and accepted-content SHA-256 are preserved.
- [ ] Traversal, symlink escape and conflicting destination keys are rejected.
- [ ] Missing objects and storage/permission failures have distinct behavior.
- [ ] Local and disposable S3 contract suites cover failure, retry and bounded-memory streaming.
- [ ] Public, staff and jobs receive the same explicit backend configuration.
- [ ] Existing relevant checks pass and implementation/review/signed delivery evidence is recorded in `completion.md`.

## Comments

2026-11-12 — Planned after design approval. Execution requires user authorization. Provider credentials and actual R2 qualification belong to Ticket 05.
