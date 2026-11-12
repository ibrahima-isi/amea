# 02 — Integrate member documents and consistent file lifecycle

Status: ready-for-agent

Blocked by: 01, verified complete.

## Scope

Implement [plan Task 2](../plan.md#task-2--member-documents-and-retryable-lifecycle) under the [approved design](../spec.md). Cover registration staging/confirmation, correction, staff editing, private downloads, soft deletion and retryable permanent deletion.

## Acceptance criteria

- [ ] Existing per-flow file limits, French UI contracts, authorization and headers are preserved through both backends.
- [ ] Pre-existing staged sessions preview and confirm using the same session ownership boundary.
- [ ] Partial-copy, DB-save and concurrent-replacement failures preserve valid references and files.
- [ ] Finalization verifies destination objects before publishing references and retains sources until commit.
- [ ] Durable deletion intents retain keys through failures and process interruption.
- [ ] Soft-delete/restore retains content, and permanent deletion retries safely with eligibility rechecks.
- [ ] Any schema version and the maintained baseline follow the Doctrine backup/recovery rules.
- [ ] PHPUnit and actual HTTP/browser document journeys pass on both backends, with reviews and signed delivery recorded.

## Comments

2026-11-12 — Planned after design approval. The auxiliary operation ledger is a planning choice for retry consistency. File references continue to use path keys.
