# English technical content and light default — completion

## Authorized scope

On 31 October 2026 the user invoked `implement` for **ticket 01: Canonical agent instructions** only. At that checkpoint, tickets 02–06 remained unstarted. The approved specification and ticket breakdown are unchanged; their readiness does not authorize execution beyond the selected ticket.

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


## Ticket 02 — light default with persistent user preference

On 31 October 2026 the user invoked `implement` for ticket 02. Only that additional ticket is authorized; tickets 03–06 remain unstarted. Worktree: `/private/tmp/amea-theme-20261031`, branch `fix/light-default-theme`, starting from fetched `dev` revision `b139f57`. The original checkout remains unchanged.

- Implemented a fixed light fallback at the existing blocking pre-render script. Only exact saved `light`/`dark` values override it; the system-preference API is no longer consulted.
- Storage read/getter failure leaves light usable; write failure permits page-local switching and correct existing French target-action labels. Controls, templates and styles remain unchanged.
- Added five shared-script boundary tests and a disposable real-Chrome regression covering public, authentication, registration, staff member-edit, tokenized member correction and one-use export-preview surfaces. Updated the previous system-following browser expectations to fixed-default/explicit-choice behavior. The focused script tests also run in the existing single CI workflow.
- TDD observed the old dark-on-first-visit, invalid-value and storage exceptions failing before their corresponding minimal fixes. Final focused tests: **5/5**.
- Actual rendered colors and 13 screenshots, simulated storage restrictions, keyboard/focus and no-JavaScript results are recorded in [ticket-02 evidence](evidence/ticket-02/README.md). The exact final browser execution used verified signed source `b82a550f6905b5c22b0dbb29e70a1d23f90c992b`.

### Local validation

- PHPUnit: **159 tests / 310 assertions**, with the same 10 existing metadata deprecations.
- Standalone PHP suites: **48/186/78/24 passed**. Changed PHP fixture and existing source/preview syntax checks passed; migration-access assertions passed. JavaScript syntax and `git diff --check` passed. No separate typechecker is configured.
- Existing isolated baseline HTTP plus homepage/registration Chrome journeys passed (`AMEA_BROWSER_CHECK=1 AMEA_REGISTRATION_BROWSER=1 python3 tests/http/baseline.py`); token HTTP plus Chrome journeys passed (`AMEA_BROWSER_CHECK=1 python3 tests/http/tokens.py`).
- `AMEA_PUBLIC_PORT=28443 AMEA_STAFF_PORT=29443 AMEA_SKIP_BROWSER=1 bash tests/apps/run.sh` passed. Task-owned project `amea-ui-test-3780-58495` and its volumes were removed by the runner. The first attempt met an occupied default port; its own resources were also removed, and existing services were preserved. Browser flags were disabled for this rehearsal, preserving the journal browser deferral.
- Initial new-runner iterations exposed fixture-only limitations (already-authenticated login redirect, SQLite dashboard dialect, missing synthetic export permission) and an about:blank storage access in the updated token runner. These were corrected in the test setup; the final commands passed. Dashboard rendering is not certified by the loopback SQLite run.

Full local logs are `/private/tmp/amea-theme-{phpunit,permissions,unit,integration,password,syntax,baseline,tokens,apps}.log`; final theme browser output is version-controlled. The application source was unchanged after its full regression run; the later fixture-only change was verified by the exact-source browser run.

### Independent review

Pinned comparison: `git diff b139f57...b82a550`; commits `9627d54` and `b82a550`, both verified cryptographically signed.

**Standards:** Zero overall findings, including the additional guarded CLI fixture. No actionable documented-standard violations or baseline design smells.

**Spec:** Zero findings through `b82a550`. The synthetic permission supports the required preview checks; no missing requirement, scope creep or incorrect implementation found.

Total: **Standards 0 / Spec 0**. Local validation/review are complete. Remote final-head CI, actual merge and release remain separate delivery milestones. No production deployment, DNS, real SMTP, migration or unrelated cleanup is included. Deferred journal browser QA remains deferred.


### Ticket 02 — PR publication

[PR #24](https://github.com/ibrahima-isi/amea/pull/24) targets `dev` from the signed `fix/light-default-theme` branch. Final-head CI is reported by the [PR checks](https://github.com/ibrahima-isi/amea/pull/24/checks); an earlier green run does not qualify a later evidence commit. PR publication does not claim actual merge, production deployment or local-main integration. The task worktree/branch is retained for review until its work is safely merged; unrelated branches/worktrees remain untouched.


## Ticket 04 — English contributor and operations documentation

The user explicitly authorized ticket 04 on 31 October 2026 while ticket 03 awaited CI, and requested completion updates during execution. Worktree `/private/tmp/amea-docs-20261031`, branch `docs/english-contributor-operations`, starts from fetched `dev` revision `7e5c398fa0d7870c454e25a65287fcb7b1a0dbfd`, which includes merged ticket 02 (PR #24). Ticket 03 remains independently published in PR #25; ticket 04 has no blockers and does not import its application changes. Tickets 05/06 are not authorized by this request.

### Inventory checkpoint — in progress

Maintained corpus: root README/glossary; the existing independent-entry ADR; `docs/email-queue.md`; `docs/operations/public-staff-rollout.md`; `docs/testing/README.md`; `docs/testing/association-journal.md`; and `tests/http/README.md`. French technical narrative remains in README, the journal glossary entries, the ADR and the maintained journal qualification guide. The other four guides are already English and will be retained unless translated anchors require a precise link repair. Dated root/feature completion, `.scratch/` plans/specs/reviews and `docs/superpowers/` history belong to ticket 05 and are excluded. The canonical instruction files belong to ticket 01 and remain untouched.

Translation will preserve French product labels as explicit glossary aliases, published decisions, exact commands/environment contracts, deployment/rollback prerequisites and testing limits. No application, theme or deployment behavior is changed. Validation/review/CI are pending; this checkpoint is not a completion claim.


### Ticket 04 — translation checkpoint

Translated README onboarding/reference prose and inline technical examples, the six French journal glossary entries (keeping exact French labels as aliases), ADR 0001 and the maintained journal qualification guide. The ADR retains the 28 October decision, local public 404/no staff bridge, shared database/files/business code, deferred physical split and preserved public correction/staff recovery paths. Journal validation limits remain explicit. The README's email navigation now points to the existing email runbook instead of the pre-existing missing section; translated headings have matching anchors. A short clarification distinguishes anonymous local entry points from the already-decided public/staff deployment boundary. These are documentation presentation/link repairs, not new deployment decisions.

The four already-English maintained guides remain byte-identical. The README command changes are English placeholder examples (`repository-url`, `REPLACE_WITH_HASH`, `YourPassword`, SMTP example values) and comment translations; SQL/paths/keys/actual origins and sample human name `Système` are preserved. Compatibility/link checks and independent review are next; runtime behavior is unchanged.
