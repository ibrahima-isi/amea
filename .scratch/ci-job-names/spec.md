# Descriptive CI check names

Status: ready-for-agent

## Approved scope — 1 November 2026

The user confirmed through the `grill-me` / `grilling` interview that the existing three jobs and their contents remain unchanged, names describe the verified scope, and required-check references are updated in coordination without changing merge requirements. The final exact names were explicitly approved for application.

| Existing ID/check | New job ID | Displayed check name |
| --- | --- | --- |
| `php-tests` | `static-checks` | `Static checks: Syntax & access` |
| `test` | `regression-tests` | `Regression tests: PHP, JS & HTTP` |
| `apps-rehearsal` | `system-tests` | `System tests: Docker & recovery` |

## Acceptance criteria

- Preserve every job's steps, commands, environment, runner, timeout and order; preserve triggers, permissions and concurrency. No dependency, test restructuring or deployment change.
- Keep all workflows in `.github/workflows/ci.yml`.
- Verify all three exact new names run successfully on the latest signed PR head before changing required-check references.
- Update only the two required context strings in active ruleset `8898075`: `test` becomes `Regression tests: PHP, JS & HTTP`, and `php-tests` becomes `Static checks: Syntax & access`. `System tests: Docker & recovery` remains non-required, as its predecessor was.
- Preserve all other ruleset fields, branch coverage (`main`/`dev`), enforcement, up-to-date checks, signatures, review requirements and bypass actors. Re-read before writing to avoid overwriting concurrent changes.
- Deliver signed isolated-worktree commits through a PR into `dev`. Keep exact-head CI, ruleset update, actual merge and local integration separate.

## Boundaries

No application, data, production, browser-QA, test-content or historical-record changes. Old check names in dated delivery evidence remain historical. This task does not start either deferred journal acceptance or orphan-test triage.

## Comments

2026-11-01: The user explicitly approved the exact labels, corresponding IDs and coordinated protection-reference update. No further naming or scope decision remains open.
