# Ticket 05 — planning/delivery translation coverage

Baseline: fetched remote `dev` at `4d021fc3f04783de8b0ff2c6ee2a629f3a58bece` (actually merged PR #26). Signed translation source: `74ea429cc0ff446c36522e173ff7be53fb496f10`; signed readability follow-up: `09e4d81f9dbc2019992f4fa1c63ddc0da2017bfe`. The follow-up changes spacing/readability, retaining the same historical evidence and references. Subsequent evidence commits do not alter runtime source.

## Complete baseline corpus

All **44** tracked first-party planning/delivery/observation records are classified below: **13 translated**, **29 byte-identical English/observation records**, and **2 active execution-tracking records** updated only for this ticket's progress. The selected ticket's readiness/status and approved scope are retained. New evidence files created for this execution are separate from that baseline count.

| Existing record | Treatment |
| --- | --- |
| `.scratch/association-journal/browser-qa.md` | Already English / literal observation; unchanged |
| `.scratch/association-journal/builder-check.md` | Translated technical narrative |
| `.scratch/association-journal/completion.md` | Translated technical narrative |
| `.scratch/association-journal/design.md` | Translated technical narrative |
| `.scratch/association-journal/evidence/reviews/final-rereview.md` | Already English / literal observation; unchanged |
| `.scratch/association-journal/evidence/reviews/final-review.md` | Already English / literal observation; unchanged |
| `.scratch/association-journal/issues/01-editorial-core.md` | Translated technical narrative |
| `.scratch/association-journal/issues/02-private-management.md` | Translated technical narrative |
| `.scratch/association-journal/issues/03-public-reading.md` | Translated technical narrative |
| `.scratch/association-journal/issues/04-integrated-qualification.md` | Translated technical narrative |
| `.scratch/association-journal/restore-ci.md` | Already English / literal observation; unchanged |
| `.scratch/association-journal/spec.md` | Translated technical narrative |
| `.scratch/chores/triage-orphan-tests/spec.md` | Already English / literal observation; unchanged |
| `.scratch/deployment-compose-consolidation/completion.md` | Translated technical narrative |
| `.scratch/deployment-compose-consolidation/design.md` | Translated technical narrative |
| `.scratch/english-policy-light-default/completion.md` | Active execution tracking only |
| `.scratch/english-policy-light-default/design.md` | Already English / literal observation; unchanged |
| `.scratch/english-policy-light-default/evidence/ticket-01-review.md` | Already English / literal observation; unchanged |
| `.scratch/english-policy-light-default/evidence/ticket-01-rule-coverage.md` | Already English / literal observation; unchanged |
| `.scratch/english-policy-light-default/evidence/ticket-02/README.md` | Already English / literal observation; unchanged |
| `.scratch/english-policy-light-default/evidence/ticket-02/browser-output.txt` | Already English / literal observation; unchanged |
| `.scratch/english-policy-light-default/evidence/ticket-03/compatibility-audit.txt` | Already English / literal observation; unchanged |
| `.scratch/english-policy-light-default/evidence/ticket-03/email-equivalence.txt` | Already English / literal observation; unchanged |
| `.scratch/english-policy-light-default/evidence/ticket-03/inventory.md` | Already English / literal observation; unchanged |
| `.scratch/english-policy-light-default/evidence/ticket-03/staged-http-output.txt` | Already English / literal observation; unchanged |
| `.scratch/english-policy-light-default/evidence/ticket-03/statistics-red.txt` | Already English / literal observation; unchanged |
| `.scratch/english-policy-light-default/evidence/ticket-04/coverage.md` | Already English / literal observation; unchanged |
| `.scratch/english-policy-light-default/evidence/ticket-04/link-and-contract-checks.txt` | Already English / literal observation; unchanged |
| `.scratch/english-policy-light-default/issues/01-canonical-agent-instructions.md` | Already English / literal observation; unchanged |
| `.scratch/english-policy-light-default/issues/02-light-default-with-persistent-user-preference.md` | Already English / literal observation; unchanged |
| `.scratch/english-policy-light-default/issues/03-english-internals-with-compatible-member-workflows.md` | Already English / literal observation; unchanged |
| `.scratch/english-policy-light-default/issues/04-english-contributor-and-operations-documentation.md` | Already English / literal observation; unchanged |
| `.scratch/english-policy-light-default/issues/05-english-planning-and-delivery-records.md` | Active execution tracking only |
| `.scratch/english-policy-light-default/issues/06-whole-spec-verification-and-handoff.md` | Already English / literal observation; unchanged |
| `.scratch/english-policy-light-default/spec.md` | Already English / literal observation; unchanged |
| `.scratch/english-policy-light-default/ticket-breakdown.md` | Already English / literal observation; unchanged |
| `.scratch/repository-cleanup/issues/01-remove-obsolete-artifacts.md` | Already English / literal observation; unchanged |
| `.scratch/repository-cleanup/plan.md` | Already English / literal observation; unchanged |
| `.scratch/repository-cleanup/review.md` | Already English / literal observation; unchanged |
| `.scratch/repository-cleanup/spec.md` | Already English / literal observation; unchanged |
| `.scratch/ui-visual-refresh/journal-research-2026-10-29.md` | Translated technical narrative |
| `completion.md` | Translated technical narrative |
| `docs/superpowers/plans/2026-10-30-association-journal.md` | Already English / literal observation; unchanged |
| `docs/superpowers/specs/2026-10-29-association-journal-design.md` | Translated technical narrative |

## Translation and provenance decisions

- Journal builder review, scope/spec, four tickets and completion history retain their separate roles. Historical draft/pending PR claims, unchecked implementation boxes and later closeout states remain in their original records; translation does not silently rewrite their chronology. The interrupted agent work, concurrency/422/session findings, browser-control failures, partial observations and user-owned deferral are retained.
- The complete journal design retains its original user approval and still-proposed historical details, data/media/date/access rules, excluded features and expected browser qualification. Its translation is not a new design or execution authorization. The already-English implementation plan is unchanged; its historical instructions are not executed by this task.
- The deployment-consolidation design/completion retain MariaDB/service/volume/network choices, synchronous-mail/optional-jobs/build defaults, actual synthetic runtime results, temporary-helper failure, unverified production identities and their then-pending CI/merge checkbox. No retrospective update to delivery status is folded into translation.
- Research remains a dated documentary observation/proposal, with the same external source URLs, observed dates, unavailable detail pages and uncertainty about freshness/private editorial workflows. No new browsing, source refresh, CMS inference or recommendation is claimed. Three pinned historical GitHub blob sources were verified against local immutable Git objects; published history is untouched.
- Only the French closeout section of root `completion.md` is translated. Its already-English dated sections retain their bytes/content and their original distinction between server delivery, incomplete restore evidence and incomplete browser work. Existing feature records are not consolidated into a new global ledger.
- The original user quotation “laisse tomber cela je le ferai après” is retained with an English gloss, preserving attribution and deferral meaning. Exact UI labels such as “Journal de l’amicale”, “À venir”, “Nos activités”, “Annonces”, “Comptes rendus”, “Archives”, “En cours” and Administration/Connexion/Accueil/Inscription remain literal where describing actual screens. Proper names, paths, contract literals and observed tool messages remain exact.

## Verification boundaries

The **245-check** [reference/provenance audit](reference-and-provenance-checks.txt) checks the translated records' numerical evidence, inline technical-reference multisets, complete link-target multisets and unchanged historical checklist/status sequences. All tracked local Markdown links/fragments, valid line references and pinned Git sources are checked. Translated headings have no remaining stale incoming fragment. No historical immutable target is rewritten to present-day guidance.

The parent English-policy specification/design, canonical instruction files and all maintained ticket-04 guides remain byte-identical. Runtime/application/schema/migration/configuration/workflow/dependency sources are outside the diff. English records and generated observation output are retained rather than translated again. The complete `browser-qa.md` record remains byte-identical and explicitly incomplete. Language-marker scans were discovery aids; classified inventory, provenance checks and independent bilingual review establish coverage.

Existing PHP/HTTP and isolated rehearsal checks are regression evidence only; they do not implement an old plan, resume journal browser acceptance or qualify production/live data/SMTP. No new product behavior or test seam is introduced, so an artificial TDD red/green test is inappropriate. [Completion](../../completion.md#ticket-05--english-planning-and-delivery-records) separates tested source, review, CI, merge and production limits.
