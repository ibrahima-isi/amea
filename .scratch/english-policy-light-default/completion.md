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


### Ticket 03 — PR publication

[PR #25](https://github.com/ibrahima-isi/amea/pull/25) targets `dev` from `refactor/english-internals`. Independent Standards and Spec evidence review through `05d2155` found zero actionable issues or inconsistencies. Final-head CI is available in the [PR checks](https://github.com/ibrahima-isi/amea/pull/25/checks); earlier green results cannot qualify a later evidence commit. Publication does not claim actual merge, production deployment or local-main integration. Keep this task worktree/branch for review until safely merged; unrelated worktrees/branches remain untouched.


### Ticket 03 — integrate the merged theme ticket

While ticket 03 awaited CI, PR #24 actually merged into remote `dev` on 31 October 2026 as `7e5c398fa0d7870c454e25a65287fcb7b1a0dbfd`. Its completion record conflicted with the independent ticket-03 append, so GitHub could not generate a CI merge revision. Integrate that fetched `dev` result with a signed merge, retaining both complete evidence sections and the combined single CI workflow. Earlier statements that ticket 02 was still open describe the initial inventory/publication checkpoints. No ticket-03 application logic is changed by this integration; the merged theme behavior and tests are the already-reviewed ticket-02 delivery. Final-head CI must run again after this merge.


## Ticket 04 — English contributor and operations documentation

The user explicitly authorized ticket 04 on 31 October 2026 while ticket 03 awaited CI, and requested completion updates during execution. Worktree `/private/tmp/amea-docs-20261031`, branch `docs/english-contributor-operations`, starts from fetched `dev` revision `7e5c398fa0d7870c454e25a65287fcb7b1a0dbfd`, which includes merged ticket 02 (PR #24). Ticket 03 remains independently published in PR #25; ticket 04 has no blockers and does not import its application changes. Tickets 05/06 are not authorized by this request.

### Inventory checkpoint — in progress

Maintained corpus: root README/glossary; the existing independent-entry ADR; `docs/email-queue.md`; `docs/operations/public-staff-rollout.md`; `docs/testing/README.md`; `docs/testing/association-journal.md`; and `tests/http/README.md`. French technical narrative remains in README, the journal glossary entries, the ADR and the maintained journal qualification guide. The other four guides are already English and will be retained unless translated anchors require a precise link repair. Dated root/feature completion, `.scratch/` plans/specs/reviews and `docs/superpowers/` history belong to ticket 05 and are excluded. The canonical instruction files belong to ticket 01 and remain untouched.

Translation will preserve French product labels as explicit glossary aliases, published decisions, exact commands/environment contracts, deployment/rollback prerequisites and testing limits. No application, theme or deployment behavior is changed. Validation/review/CI are pending; this checkpoint is not a completion claim.


### Ticket 04 — translation checkpoint

Translated README onboarding/reference prose and inline technical examples, the six French journal glossary entries (keeping exact French labels as aliases), ADR 0001 and the maintained journal qualification guide. The ADR retains the 28 October decision, local public 404/no staff bridge, shared database/files/business code, deferred physical split and preserved public correction/staff recovery paths. Journal validation limits remain explicit. The README's email navigation now points to the existing email runbook instead of the pre-existing missing section; translated headings have matching anchors. A short clarification distinguishes anonymous local entry points from the already-decided public/staff deployment boundary. These are documentation presentation/link repairs, not new deployment decisions.

The four already-English maintained guides remain byte-identical. The README command changes are English placeholder examples (`repository-url`, `REPLACE_WITH_HASH`, `YourPassword`, SMTP example values) and comment translations; SQL/paths/keys/actual origins and sample human name `Système` are preserved. Compatibility/link checks and independent review are next; runtime behavior is unchanged.


### Ticket 04 — integration checkpoint

Ticket 03 reached **3/3 successful CI checks** on signed head `7f01e292e7e5e292059264d1ef68f391866b7a59` ([CI run](https://github.com/ibrahima-isi/amea/actions/runs/41227828737)) and actually merged through [PR #25](https://github.com/ibrahima-isi/amea/pull/25) on 31 October 2026 as remote `dev` revision `7f4063b94cb4f7bf23abc2dc96dd3094a1dc25a7`. The completed ticket-02/03 records are preserved in this branch before publishing ticket 04. Earlier ticket-04 checkpoints describe the state at their time.

Local `main` incorporated those actually merged deliveries from an isolated integration worktree in verified signed merge `dd520c4c298699fa050293ed1c86bd6495c3ddac`; remote `main` was not pushed. Ticket 04 integrates the fetched remote `dev` with a signed merge; its translation diff remains confined to the four maintained documents plus feature evidence. The initial full regression run passed on translation source `6581838`; a final PHP/HTTP check will also qualify the combined source after integration. No journal browser acceptance or production action is claimed.


### Ticket 04 — validation and review checkpoint

The [classified coverage inventory](evidence/ticket-04/coverage.md) accounts for all eight maintained documents: **four translated / four already-English guides retained byte-for-byte**. The [59 link/anchor/example/alias/scope checks](evidence/ticket-04/link-and-contract-checks.txt) pass after integrating current `dev`. Incoming historical line references and all six French glossary labels remain valid. Canonical instructions, parent spec/design, historical ticket-05 corpus and every application/runtime file are unchanged by this ticket's diff.

Local validation on signed translation checkpoint **`6581838`** passed: PHPUnit **159 tests / 310 assertions** (10 existing metadata deprecations), standalone suites **48/186/78/24**, theme-script **5/5**, isolated HTTP **35 + 28**, PHP syntax and migration-access assertions. `AMEA_PUBLIC_PORT=48443 AMEA_STAFF_PORT=49443 AMEA_SKIP_BROWSER=1 bash tests/apps/run.sh` passed with **535 PASS observations**; task-owned project `amea-ui-test-3771-71195` and its volumes were removed. No documented production/migration/SMTP command was run against an existing environment.

After the user-delivered ticket 03 was incorporated, final combined-source validation at signed merge **`bd0cbbb`** passed: PHPUnit **171 tests / 338 assertions** (the same 10 metadata deprecations), standalone suites **48/186/78/26**, theme-script **5/5**, and isolated HTTP **35 + 28 + 12**. PHP syntax, access assertions and `git diff --check` pass. No separate typechecker is configured; no new behavior/test seam is introduced by a documentation-only translation, so no artificial red/green test was added. Full local logs use `/private/tmp/amea-docs-*.log` and `/private/tmp/amea-docs-merged-*.log`. Runtime checks demonstrate regression status, not production or browser acceptance.

Independent source review of `git diff 7e5c398...6581838`:

**Standards:** 0 actionable findings. English policy, French labels, domain/ADR decisions and feature-local evidence follow the canonical instructions; no applicable baseline code smells.

**Spec:** 0 actionable findings. The maintained corpus is complete, commands/contracts/decisions/approval boundaries retain their meaning, and excluded history/runtime files remain outside scope. The later signed merge incorporates the already-reviewed ticket 03 without changing the translation; final evidence review is recorded separately below.

Total source findings: **Standards 0 / Spec 0**. Final-head PR CI and actual ticket-04 merge are still separate pending milestones. Production and deferred journal browser QA remain untouched.


### Ticket 04 — publication and final review

[PR #26](https://github.com/ibrahima-isi/amea/pull/26) targets `dev` from `docs/english-contributor-operations`. Final independent review of `git diff 7f4063b...35a7413` reports **Standards 0 / Spec 0**: translation bytes are unchanged from the reviewed source, coverage is complete, progressive completion is accurate, and initial/final validation revisions and delivery limits remain distinct. Reviewers did not rerun runtime tests.

Final-head CI is recorded in the [PR checks](https://github.com/ibrahima-isi/amea/pull/26/checks). Publication does not claim an actual ticket-04 merge or production release. The current task worktree/branch remains available for review until safely merged.

After the actual PR #24/#25 merges and signed local-main integration recorded above, cleanup verified that the complete task-02/task-03 feature trees exactly match preserved merge revisions `7e5c398`/`7f4063b`, both ancestors of fetched remote `dev`. Their clean task-owned worktrees (`/private/tmp/amea-theme-20261031`, `/private/tmp/amea-internals-20261031`) and local feature branches were then removed. Their remote feature branches were already absent. The temporary local-main integration worktree was also removed; local `main` remains at signed `dd520c4`. The original checkout and all unrelated worktrees/branches remain unchanged.


## Ticket 05 — English planning and delivery records

The user explicitly authorized ticket 05 on 31 October 2026. It has no blockers. Worktree `/private/tmp/amea-records-20261031`, branch `docs/english-planning-delivery`, starts from fetched `dev` revision `4d021fc3f04783de8b0ff2c6ee2a629f3a58bece`, which includes actually merged ticket 04 (PR #26). Ticket 06 is not authorized by this request.

### Inventory checkpoint — in progress

The tracked planning/delivery corpus includes feature specs/designs/tickets/completion/review/research under `.scratch/`, dated `docs/superpowers/` plans/designs and root `completion.md`. Thirteen Markdown records contain remaining French engineering narrative, concentrated in the journal design/delivery history, deployment consolidation design/completion, association-site research and root cleanup closeout. Already-English records, generated observation output and exact French labels remain distinct. Parent specification, maintained ticket-04 guides, canonical instructions and runtime sources stay unchanged.

Translation will preserve each record in place, with its original dates, references, status/checklist state, approvals, partial observations and deferred work. Journal browser QA remains explicitly user-deferred and incomplete. Source/contract/reference checks and independent review are pending; no completion claim is made at this checkpoint.


### Ticket 05 — translation checkpoint

Translated the thirteen identified historical records in place: journal builder review, scope/specification, four tickets and completion history; deployment-consolidation design/completion; dated association-site research; the complete journal design; and the French root closeout section. All original numerical evidence tokens, Markdown link targets, checklist states and technical inline references are retained. Historical pending/draft/unchecked statements remain historical rather than being silently updated to today's delivery state. The original user deferral quotation is preserved with an English gloss; real French UI labels remain literal. The parent specification and already-English records are unchanged.

Reference/anchor validation and independent review are next. This translation does not execute the old plans, rerun journal browser QA or qualify production.


### Ticket 05 — validation and review

The [classified inventory](evidence/ticket-05/coverage.md) accounts for **44 baseline records: 13 translated, 29 byte-identical, 2 active execution trackers**. Exact historical numbers, inline technical references, link targets, checkbox/triage states, user approvals, interruptions and qualification limits are preserved. The initial **239 provenance/reference checks** passed before the final coverage links were added; the final audit passes **247 checks**, including all new evidence links and unchanged English/root/runtime scope checks. Three pinned Git sources exist in local immutable history. French remains only in attributed human quotation, actual UI/contract literals, proper names and unchanged observation artifacts. The parent specification/design, maintained guides, canonical instructions and runtime sources are unchanged.

Local validation on signed source **`74ea429`** passed: PHPUnit **171 tests / 338 assertions**, with the same 10 existing metadata deprecations; standalone suites **48/186/78/26**; theme-script **5/5**; isolated HTTP **35 + 28 + 12**; PHP syntax and migration-access assertions. `AMEA_PUBLIC_PORT=58443 AMEA_STAFF_PORT=59443 AMEA_SKIP_BROWSER=1 bash tests/apps/run.sh` passed with **535 PASS observations**. Task-owned project `amea-ui-test-23866-81010` and its volumes were removed normally. Signed `09e4d81` changes only translated prose spacing/readability and rechecks provenance; no runtime code changes justify another full runtime run. Local logs are `/private/tmp/amea-records-*.log`. No separate typechecker is configured; no new behavior or test seam required a TDD test.

Independent source review of `git diff 4d021fc...74ea429`:

**Standards:** 0 documented violations / 0 material judgment-call smells. English narrative, literal exceptions, evidence placement and historical record roles follow the canonical instructions.

**Spec:** 0 actionable findings. Bilingual review confirms the complete translation set, dates/counts/attribution/approval/interruptions, unchanged historical pending milestones and incomplete journal browser QA. No scope creep or omitted French engineering narrative found. Reviewers did not rerun runtime tests. Final evidence/readability review remains separate from these initial source results.

Total initial source findings: **Standards 0 / Spec 0**. PR publication, final-head CI and actual merge remain separate milestones. No production, data migration, browser QA execution, real SMTP or history rewrite is performed; ticket 06 remains unstarted.

### Ticket 04 — completed integration housekeeping

Actually merged PR #26 (`4d021fc`) was integrated into local `main` from a temporary isolated worktree by verified signed merge `7d75d4f279f36c09e21dff6a7879915cecaf7677`. Remote `main` was not pushed. The complete ticket-04 feature tree was verified equal to its preserved merge revision before removing `/private/tmp/amea-docs-20261031` and local branch `docs/english-contributor-operations`. Its remote branch was already absent; the temporary integration worktree was also removed. The original checkout and unrelated worktrees/branches remain untouched.


### Ticket 05 — publication and final review

[PR #27](https://github.com/ibrahima-isi/amea/pull/27) targets `dev` from `docs/english-planning-delivery`. Final independent review through `6291642` reports **Standards 0 / Spec 0**: readable English, classified coverage, preserved provenance and historical states, no remaining source/evidence inconsistency. Reviewers performed no runtime reruns.

Final-head CI is available in the [PR checks](https://github.com/ibrahima-isi/amea/pull/27/checks). Earlier successful checks do not qualify later evidence commits. Publication does not claim actual ticket-05 merge, production release or completion of ticket 06. Keep this task's clean worktree/branch for review until its work is safely merged; unrelated work remains untouched.
