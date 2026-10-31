# English technical content and light default — completion

## Authorized scope

On 31 October 2026 the user invoked `implement` for **ticket 01: Canonical agent instructions** only. Tickets 02–06 remain unstarted. The approved specification and ticket breakdown are unchanged; their readiness does not authorize execution beyond the selected ticket.

Worktree: `/private/tmp/amea-english-policy-20261031`, current branch `docs/english-policy-light-default`. Implementation baseline: signed planning commit `2575def178c25fdbf03d575e021410085f6f425e`; remote starting base `e08f8cb26d96658e237395608b495ef61d11b21e`. Preserve the primary checkout and unrelated preview/journal worktrees.

## Ticket 01 — implementation

- [x] Consolidate durable rules into the existing canonical instruction file and remove the three redundant guides.
- [x] Retain the exact existing Claude/Gemini read pointers without editing them.
- [x] State the English technical/commit/PR convention, French compatibility boundaries and approved light-default requirement without claiming theme implementation.
- [x] Preserve useful workflow/security/tracker/triage/domain rules; clarify per-feature execution evidence as required by the approved spec.
- [x] Replace the completed cleanup plan's removed-guide pointer with its immutable historical source; keep current active guidance canonical.
- [x] Verify complete rule coverage, pointers, links and unchanged out-of-scope sources.
- [x] Run the required existing regression command set once; no new application behavior or test seam is introduced by this documentation ticket.
- [x] Obtain independent Standards and Spec reviews: Standards 0 / Spec 0 findings, no fix wave required. Source commit `e626bc4` has a verified ED25519 signature.
- [x] Publish [PR #23](https://github.com/ibrahima-isi/amea/pull/23), targeting `dev` from the current branch. The previously approved spec and ticket-planning commits are included; only ticket 01 is implemented.

Exact-final-head CI and review readiness are reported by [PR #23 metadata/checks](https://github.com/ibrahima-isi/amea/pull/23/checks), separately from these local implementation and review results. An earlier green run cannot qualify a later evidence commit. Actual remote merge, local integration and task-owned cleanup are not claimed by PR publication and remain conditional on a later merge.

This change does not translate the remaining documentation corpus, rename application identifiers, implement the theme, rewrite published history, alter external personal/platform memory or resume deferred journal browser QA. No production, DNS, database migration, real SMTP or existing Docker project is touched.

## Local validation

The [rule-coverage matrix](evidence/ticket-01-rule-coverage.md) maps the baseline canonical instructions and all three guides to their retained destinations and records the two factual clarifications. Exact delegation bytes and their baseline SHA-256 values are unchanged. All three guides are removed, active canonical references no longer point to them, relative Markdown links resolve, and the historical cleanup-plan target exists in its immutable Git revision. `git diff --check` passes.

The approved specification, design, ticket index and tickets 02–06 are byte-identical to the implementation baseline. The primary checkout and two unrelated preview/journal worktrees match their captured head/status/diff snapshots. Application source, test source, schema, deployment and CI files are unchanged by ticket 01.

The existing local full command set passed once: PHPUnit **159 tests / 310 assertions**, with the same 10 existing doc-comment metadata deprecations; standalone PHP scripts **48/186/78/24**; isolated HTTP journeys **35 + 28**. PHP syntax and existing migration-access assertions passed. Browser flags were explicitly disabled for the HTTP runs, preserving the journal QA deferral. No new permanent test or production test seam was added for this instruction-only change.

Local artifacts: `/private/tmp/amea-ticket01-baseline-20261031.json`, `/private/tmp/amea-ticket01-phpunit-20261031.log`, the four matching standalone logs, `amea-ticket01-php-syntax-20261031.log`, and the two `amea-ticket01-http-*-20261031.log` files. These local results are distinct from final-head remote CI and actual merge.

## Review and signing handoff

The [independent review record](evidence/ticket-01-review.md) keeps Standards and Spec outcomes separate at the pinned implementation source `2575def...e626bc4`. Both reviewers found zero actionable issues and performed no runtime tests. Subsequent delivery-record changes do not modify the canonical instruction bytes they reviewed.

An initial 1Password helper failure created no commit. After the user's explicit interactive validation, the same configured helper and signing key produced verified signed `e626bc4`. No signing setting, key or protection was changed. Tickets 02–06 remain byte-identical to the ticket-01 starting snapshot and unstarted; the runtime theme still belongs to ticket 02.
