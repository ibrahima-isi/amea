# 05: English planning and delivery records

**What to build:** A maintainer can understand the currently tracked technical plans, task records and delivery evidence in English without losing the original decisions, provenance or the distinction between completed delivery and unexecuted acceptance work.

**Blocked by:** None (can start immediately once implementation is authorized).

**Status:** ready-for-agent

Specification: [English technical content, canonical agent instructions, and a light default theme](../spec.md).

- [x] Inventory current first-party planning, design, specification, ticket, research/review and completion narratives, including documents added by the already-merged deployment consolidation. Translate their remaining French technical prose without changing the approved parent specification.
- [x] Preserve dates, approval meaning, scope boundaries, source revisions, commit/PR/run references, test counts, interruptions and explicit qualification limits. A translated quotation or summary must preserve its attribution and decision meaning.
- [x] Preserve all recorded unfinished work, especially the user-deferred journal browser QA. Do not convert a partial observation, mockup, HTTP test or green CI run into a completed visual acceptance claim.
- [x] Keep published Git commit messages and historical external PR metadata untouched. Existing immutable history links remain provenance references rather than targets for retrospective language cleanup.
- [x] Preserve exact French UI/contract literals and proper names when necessary to describe the real system, while keeping the surrounding engineering narrative in English.
- [x] Repair affected relative links and translated-heading anchors within and between the planning/evidence records. Preserve valid immutable historical references and clearly distinguish them from current active guidance.
- [x] Keep each existing record's role and provenance; do not delete records, consolidate unrelated efforts into a global ledger, or introduce redundant progress tracking as a substitute for translation.
- [x] Verify corpus coverage through classified review and link/reference checks, and record the translated scope and remaining legitimate exceptions in the feature's completion record.

## Boundaries

Current maintained contributor/domain/operations guides belong to ticket 04. No application behavior change, new implementation ticket graph, historical Git rewrite, data migration, journal QA execution or production change is included.

## Comments

2026-10-31: The user approved publication of this six-ticket breakdown. Readiness and delivery states in the existing records must remain truthful after translation.

2026-10-31: The user explicitly authorized ticket-05 implementation via `implement`. Translation, coverage, validation and independent review are recorded in [completion](../completion.md#ticket-05--english-planning-and-delivery-records); CI/merge remain separate milestones.
