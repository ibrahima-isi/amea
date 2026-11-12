# 04 — Build inventory and verified migration tools

Status: ready-for-agent

Blocked by: 03, verified complete.

## Scope

Implement [plan Task 4](../plan.md#task-4--migration-inventory-manifests-and-verified-transfer) under the [approved design](../spec.md). Provide private manifests, complete source/reference inventory, resumable copying, read-back verification, reference corrections and reverse-copy tooling.

## Acceptance criteria

- [ ] Full recursive inventory includes unreferenced, historical, staged and trashed-member files.
- [ ] Special files, invalid references, collisions and pre-existing missing sources are reported explicitly.
- [ ] Every mapped file records full key, size, SHA-256, original timestamps and copy/verification states.
- [ ] Destination bytes are read back and compared, independently of ETags and object counts.
- [ ] Interrupted copies resume safely, and source changes reconcile against a final inventory.
- [ ] Reference corrections require reviewed expected-old mappings under the existing transaction/locking rules.
- [ ] Reverse-copy preserves new submissions using current DB/session references.
- [ ] Wrong targets, denied reads and ambiguous mappings fail explicitly. Synthetic regressions and signed delivery evidence are recorded.

## Comments

2026-11-12 — Inventory/transfer qualification uses synthetic or explicitly selected isolated data. Live data and mutation require separately approved operational scope.
