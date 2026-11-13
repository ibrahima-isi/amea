# 02 — Integrate member documents and consistent file lifecycle

Status: ready-for-agent

Blocked by: 01, verified complete.

## Scope

Implement [plan Task 2](../plan.md#task-2--member-documents-and-retryable-lifecycle) under the [approved design](../spec.md). Cover registration staging/confirmation, correction, staff editing, private downloads, soft deletion and retryable permanent deletion.

## Acceptance criteria

- [x] Existing per-flow file limits, French UI contracts, authorization and headers are preserved through both backends.
- [x] Pre-existing staged sessions preview and confirm using the same session ownership boundary.
- [x] Partial-copy, DB-save and concurrent-replacement failures preserve valid references and files.
- [x] Finalization verifies destination objects before publishing references and retains sources until commit.
- [x] Durable deletion intents retain keys through failures and process interruption.
- [x] Soft-delete/restore retains content, and permanent deletion retries safely with eligibility rechecks.
- [x] Any schema version and the maintained baseline follow the Doctrine backup/recovery rules.
- [x] PHPUnit and actual HTTP/browser document journeys pass on both backends, with reviews and signed delivery recorded.

## Comments

2026-11-12 — Planned after design approval. The auxiliary operation ledger is a planning choice for retry consistency. File references continue to use path keys.

2026-11-13 — Implemented in signed commit `b1adb91` after the user invoked `implement` for the next ticket. Both independent Standards and Spec reviews passed without actionable defects. The optional cleanup-block duplication is documented. See [completion evidence](../completion.md) for full local/S3, browser, concurrency and migration/recovery checks. Hosted CI and actual merge remain separately tracked delivery steps.
