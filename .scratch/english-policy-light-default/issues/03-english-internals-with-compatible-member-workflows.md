# 03: English internals with compatible member workflows

**What to build:** Contributors can follow first-party application internals and comments in English from the entry into a member/staff workflow through its services, persistence, rendering and tests, while members and staff retain the same French experience and existing integration contracts.

**Blocked by:** None (can start immediately once implementation is authorized).

**Status:** ready-for-agent

Specification: [English technical content, canonical agent instructions, and a light default theme](../spec.md).

- [x] Inventory first-party internal identifiers and technical comments separately from user-visible text, contract literals, country labels, legal material, human data and third-party/generated content. Classify ambiguous names at their actual consumer boundary before renaming.
- [x] Rename French internal methods, functions, variables and technical test names coherently, including the complete caller set across models, repositories, services, controllers, frontend/template consumers and tests.
- [x] Keep each shared rename and all of its callers/tests in one complete green batch. Do not land a repository/service-only rename that leaves another member or staff workflow calling the old internal name.
- [x] Translate first-party technical comments in the relevant PHP, JavaScript, template/HTML and other source languages. English explanatory comments may retain exact French UI/contract literals and proper names where accuracy requires them.
- [x] Preserve existing URLs, secure links, request/response shapes, form/JSON/session keys, database schemas, persisted values and staged-registration compatibility. No schema/data normalization, migration or historical migration-logic change is included.
- [x] Keep application/email copy and business-value literals in French where currently required. Internal result aliases, view keys and template placeholders may change only after verifying they are not compatibility contracts and updating every local producer/consumer together.
- [x] Demonstrate relevant registration staging/confirmation, correction, administration, filtering/reporting/graduation and shared lookup behavior through their existing interfaces after the rename; retain authorization, CSRF and controlled-document behavior.
- [x] Run the existing PHP unit/integration, standalone and isolated HTTP regression checks relevant to these workflows; add focused coverage only at the approved observable seams where an actual coverage gap makes it necessary.
- [x] Verify no first-party caller remains dependent on a removed internal name, while documenting legitimate preserved boundary literals. Record the checked revision, results and limits in the feature's completion record.

## Boundaries

This is a bounded complete internal-language refactor, not a layer-by-layer migration or a new public API. Documentation-corpus translation and theme behavior belong to their own tickets. Do not rewrite published history, alter production data or resume deferred journal browser acceptance.

## Comments

2026-10-31: The user approved publication of this six-ticket breakdown. The current shared rename set is kept together with all callers; no speculative prefactoring or compatibility adapter is requested.

2026-10-31: The user explicitly authorized ticket-03 implementation via `implement`. See [completion](../completion.md#ticket-03--english-internals-with-compatible-member-workflows) for implementation, tests and independent review; delivery milestones remain separate.
