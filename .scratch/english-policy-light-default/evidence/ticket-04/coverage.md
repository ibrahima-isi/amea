# Ticket 04 — maintained-document translation coverage

Initial selected source: fetched `dev` revision `7e5c398fa0d7870c454e25a65287fcb7b1a0dbfd` (merged ticket 02). Signed translation checkpoint: `6581838c51f693b9169aaced760c9ed547236c42`. Ticket 03 later merged through PR #25; signed integration `bd0cbbb546abd75c7408af0000cde545f3f4dfe3` incorporates `7f4063b94cb4f7bf23abc2dc96dd3094a1dc25a7` before final qualification. This ticket's own diff against that current `dev` contains only the four maintained documents plus its feature evidence/completion/ticket updates.

## Classified maintained corpus

| Source | Coverage | SHA-256 at integrated dev | SHA-256 after translation |
| --- | --- | --- | --- |
| `README.md` | Translated remaining French narrative | `56d36208e586719f15a06438c2f84649f8156bdcfd1bb64e6ee3f61fefb57d4a` | `1a9bf3f8e6737f0f14fcf3d11306c7157170530d8d42096a5089b119aaaa50ae` |
| `GLOSSARY.md` | Translated remaining French narrative | `47b13698f5a1dc4fb3d9badbeb89518399714a59bb28e8a1d614a70095570bb0` | `27f90b77842151d0515cc2842a7fdaee4034c5e9e3ab99d5af2f2cac61ea4ab3` |
| `docs/adr/0001-independent-public-and-staff-entry.md` | Translated remaining French narrative | `19b7b2a0ab74f0514815f8b6bd9a3e027b132618623290bcdbbcb7e9c518ef97` | `c2636473255a92b851522ea052d93a0a8b55f439384123c3805c5420a9aeb5bd` |
| `docs/email-queue.md` | Already English; byte-identical | `b5b5f6fa3186d8c47a30fcae8718a19444b8de2f9995ed19d1862acf6bbf846f` | `b5b5f6fa3186d8c47a30fcae8718a19444b8de2f9995ed19d1862acf6bbf846f` |
| `docs/operations/public-staff-rollout.md` | Already English; byte-identical | `7227826ba3a62878d8ac8a21837ae91f90cc217e55ebff2c71f7128e9200cd01` | `7227826ba3a62878d8ac8a21837ae91f90cc217e55ebff2c71f7128e9200cd01` |
| `docs/testing/README.md` | Already English; byte-identical | `df6c01dff1bf819fd155dd7d6d96e1384a07cd062b7ba2f8105988333535ce77` | `df6c01dff1bf819fd155dd7d6d96e1384a07cd062b7ba2f8105988333535ce77` |
| `docs/testing/association-journal.md` | Translated remaining French narrative | `3f930994e9535b3a61c55161f728c29bed21bcd3729db6bd0d6d0a2ce680ddee` | `4c6037549da6bee6e949a9d5b1e8bf6e0c6cff716595ec842624255e9eabacbe` |
| `tests/http/README.md` | Already English; byte-identical | `dd5d694da2c8e531a9ddfecba48177792ac57c0f5a5319ef6ea4e4087c23f222` | `dd5d694da2c8e531a9ddfecba48177792ac57c0f5a5319ef6ea4e4087c23f222` |

- **README:** Translate all contributor/onboarding/reference narrative, table descriptions, shell/SQL-example comments and tree annotations. Preserve keys, paths, schemas, URLs, command order, credentials-as-untracked configuration and production authorization boundaries. English placeholders replace only the old repository URL, hash, password and SMTP example placeholders. Preserve the sample human name `Système`. The email TOC entry now links the existing runbook instead of a missing heading. The anonymous-entry heading/paragraph distinguishes the local legacy root layout from the existing split-host ADR; it introduces no new routing decision.
- **Glossary:** Translate six journal concept names/definitions into English and keep the six exact French labels as explicit aliases. Existing English member/staff/public/administration definitions are unchanged. No implementation details or new domain concepts are added.
- **ADR 0001:** Translate the existing decision, retaining the date, monorepo target, independent public/staff entry points, local public 404 without exposing the staff destination, shared business code/data/files, deferred physical split, abandoned public staff bookmarks/forms and public member correction/staff account recovery distinction. The historical redirect evidence remains historical. The title changes; filename and original line numbering are preserved, including an inbound historical `#L3` reference.
- **Journal qualification guide:** Translate the maintained testing guidance, preserving its executable example byte-for-byte. Keep the synthetic-only fixture boundary, HTTPS/form/CSRF/revision checks, relative clock dates, exact archive/homepage limits, migration/replacement/rollback/restore limits and explicit absence of browser/remote-delivery proof.
- **Already-English guides:** Email queue, deployment/rollback, testing index and isolated HTTP guidance remain byte-identical. Their existing deployment prerequisites, single-worker transition, rollback/data retention, no-production-qualification statements and browser limits are preserved.

## Excluded corpus and legitimate French

Root `completion.md`, `.scratch/` planning/delivery/review narratives and dated `docs/superpowers/` plans/designs belong to ticket 05. Existing `docs/testing/evidence/` JSON/images are historical/generated evidence. Canonical `AGENTS.md` and its delegation pointers belong to ticket 01. The approved parent spec/design are unchanged; only this feature's execution record and selected ticket are updated. No competing instruction guide is recreated.

French remains only as the association's proper name, explicit domain/UI aliases, exact business/contract values (`Masculin`, `Féminin`, `ELEVE`, `ETUDIANT`, `STAGIAIRE`, “Diplômé”, “CGU”), and the retained example human name. Country data, legal source, product/email copy, dependencies, generated content and published Git/PR history are untouched.

## Verification and limits

The [59 recorded checks](link-and-contract-checks.txt) validate maintained-document links and fragments, incoming anchors to translated files, the four already-English guide hashes, executable example equivalence after comment/placeholder translation, six exact glossary aliases, unchanged canonical/planning documents and the Markdown-only scope. An initial checker treated a valid `#L3` line reference as a heading anchor; line-number references are now checked separately, and the historical source remains untouched. Language-marker scans aided review; the file-by-file classification and independent bilingual source review establish coverage.

No production command from these documents was executed. Existing code-level regression suites and the disposable rehearsal were run separately, with browser flags disabled. This is translation/navigation qualification, not a fresh audit of every pre-existing technical assertion, deployment approval, production backup/restore/SMTP qualification or the deferred journal browser acceptance. [Completion](../../completion.md#ticket-04--english-contributor-and-operations-documentation) records revisions, tests, review and delivery milestones separately.
