# 01 — Remove obsolete artifacts and repair maintained consumers

Status: ready-for-agent

Blocked by: none

Specification: [Repository cleanup](../spec.md). Execution: [implementation plan](../plan.md). Evidence and delivery state: root `completion.md`.

## Acceptance criteria

- Remove the approved obsolete artifact manifest; keep uncertain consumers, meaningful tests, active journal work, and all unrelated worktrees intact.
- Maintain current operational/testing guidance and working links; retain feature planning under `.scratch/`.
- Retire cPanel support and consolidate CI into `ci.yml`, preserving required `test` and `php-tests` check contexts and unique checks/events.
- Run the agreed PHP/HTTP/browser/container validations and report actual results and limitations.
- Create signed commits and one PR into `dev` after independent review; production operations and remote `main` changes are excluded.

## Comments

2026-10-30: Scope, retention, tooling removal, cPanel retirement, single-PR delivery, and concrete deletion plan approved through interactive input.
