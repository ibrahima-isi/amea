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


## Ticket 03 — English internals with compatible member workflows

On 31 October 2026 the user invoked `implement` for ticket 03. It has no blockers. This branch implements only ticket 03; ticket 02 remains independently published in [PR #24](https://github.com/ibrahima-isi/amea/pull/24), and tickets 04–06 remain unstarted. Worktree `/private/tmp/amea-internals-20261031`, branch `refactor/english-internals`, starts at fetched `dev` revision `b139f57`. The primary checkout and unrelated worktrees are untouched.

### Implementation and boundary inventory

The [complete inventory](evidence/ticket-03/inventory.md) classifies method/class/local-variable/view/statistics/email renames and every preserved compatibility boundary. All shared repository/model renames land with their callers/tests in signed `42c018bebe49cbe687a43496fa92168dd09fee33`. Signed `f0543102bcb599dad70ef0afd6ca299425c71d97` resolves the review finding for staff-notification aliases and adds full producer-shaped rendering coverage. Both signatures verified.

French UI/email wording, form/JSON/session keys, existing route paths, secure-link formats, French business values, country labels, schema and migration logic are preserved. In particular `valid_pays_ids` retains the pre-refactor pending-session contract. The staff notification now pairs its placeholders with the existing English producer fields, including `id`/`update_date`; the complete notification values are tested. Its earlier mismatched placeholders could leave fields blank; this pairing resolves that internal inconsistency without translating the French message.

Technical comments are English across the inventoried first-party source. Documentation-corpus translation remains in tickets 04/05. No new production interface, compatibility adapter, visual redesign or theme behavior is introduced.

### Local validation and executed revisions

- Focused baseline model/repository tests passed before edits: **36 tests / 89 assertions**. The added statistics regression then failed on the old French result aliases before the coherent rename batch; [red output](evidence/ticket-03/statistics-red.txt) records that failure. Model/repository checks passed after the batch.
- Final full PHPUnit run at **`f054310`**: **171 tests / 338 assertions**, with the same 10 existing doc-comment metadata deprecations. New coverage includes French-value statistics/filtering and soft-delete boundaries, reusable sorted lookups/country labels, institution reporting, escaped French greetings, and full staff-update email values.
- Standalone PHP command set at **`42c018b`**: permissions **48/48**, OOP unit **186/186**, OOP integration **78/78**, password reset security **26/26**. The reset suite adds full-recipient-name checks for both French emails. Subsequent changes affect only the staff-update template and its test; the final full suite verifies that fix.
- Isolated HTTP baseline **35 checks**, token correction/consent **28 checks**, and pre-refactor staged-session compatibility **12 checks** passed at **`42c018b`**. The [staged HTTP output](evidence/ticket-03/staged-http-output.txt) demonstrates preview ownership, French values, CSRF, confirmation, staff editing/shared lookup values, finalized download authorization and session consumption. No database/session migration is required.
- `AMEA_PUBLIC_PORT=38443 AMEA_STAFF_PORT=39443 AMEA_SKIP_BROWSER=1 bash tests/apps/run.sh` passed at **`42c018b`** with **535 PASS observations**. It exercises real MySQL registration/confirmation, correction, administration/filtering, exports/reporting, member progression, permissions, protected documents, shared jobs and restore/isolation checks on synthetic resources. Project `amea-ui-test-25361-65911` and its task-owned volumes were removed by the runner. The later template-only fix does not change those flows; final-head remote CI remains a separate milestone.
- **34 static compatibility checks** at **`f054310`** verify unchanged static template content, effective JavaScript/Apache/SQL behavior after stripping comments, immutable schema/migrations/legal/country/third-party/config sources, unchanged HTTP methods/paths, and absence of removed executable names. [Audit](evidence/ticket-03/compatibility-audit.txt).
- **Nine email templates** render exactly equivalent HTML under their corresponding old/new data names: [equivalence output](evidence/ticket-03/email-equivalence.txt). This check proves wording/escaping/layout equivalence for equivalent supplied values; it does not hide the pre-existing producer/alias mismatch corrected above. Active password-reset producers are verified through the real service callback; the staff update is checked at the rendering boundary with its source-audited producer payload. Real SMTP is not used.
- PHP syntax, JavaScript syntax and `git diff --check` passed. No separate typechecker is configured. The original checkout remains clean. Legacy source-scanning scripts aimed at pre-OOP root implementations were not used as behavior proof.

Full local logs are `/private/tmp/amea-internals-{phpunit-final,permissions,unit,integration,password,syntax,baseline,tokens,staged-final,apps}.log`. Version-controlled evidence contains the relevant contract observations; generated caches and disposable resources are not delivery artifacts.

### Independent review

Pinned source comparison: `git diff b139f57...f054310`.

**Standards:** 0 actionable violations. Existing duplicated lookup-insertion shapes were a pre-existing judgment call; extracting them would exceed this bounded rename. No unrelated refactor was made.

**Spec:** 1 initial P2 finding: the newly translated staff email placeholders did not match its existing `id`/`update_date` producer. Fixed in `f054310`, with a failing-then-passing rendering regression. Re-review: **0 remaining findings**; no further caller, language-inventory or scope gaps found.

Final totals: **Standards 0 / Spec 0**. Reviewers performed read-only source review; they did not rerun runtime tests. Final-head CI, actual merge and any release remain separate milestones.

No production deployment, live data, real SMTP, schema/data migration or unrelated cleanup is performed. Browser flags remain disabled, and the journal browser acceptance explicitly deferred by the user remains deferred.
