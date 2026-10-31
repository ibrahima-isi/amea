# 01: Canonical agent instructions

**What to build:** A contributor or agent can obtain all durable project rules from the existing canonical instruction document, while the existing delegation documents point only to that source. The English technical convention and approved light-default requirement are explicit without combining ordinary documentation or task records into the instruction document.

**Blocked by:** None (can start immediately once implementation is authorized).

**Status:** ready-for-agent

Specification: [English technical content, canonical agent instructions, and a light default theme](../spec.md).

- [x] Account for every useful rule in the current canonical instructions and the three supplementary agent guides before consolidation; retain the project, security, database, tracker, triage, domain-document and delivery conventions.
- [x] Keep the existing canonical filename and make it the sole authoritative source of durable project instructions. Remove the three redundant agent guides after their rules have been retained.
- [x] Keep the Claude and Gemini delegation documents as the exact existing one-line canonical read pointer, with no additional rules or competing instruction source.
- [x] State that current first-party technical documentation, comments and internal identifiers use English; all new commit messages and PR text use English; French interface/email content and compatibility contracts remain preserved.
- [x] State the approved theme rule: a valid saved user choice wins; otherwise light is the product default. Describe it as a requirement rather than claiming the separate theme implementation is already complete.
- [x] Preserve cryptographic signing, isolated worktrees, protected-branch delivery, explicit production approval, authenticated private-document access, CSRF and existing session safeguards.
- [x] Repair active instruction consumers and links so removing the supplementary guides leaves no dangling active guidance. Keep ordinary guides, glossary, ADRs and task evidence separate, with no duplicated durable rules reintroduced.
- [x] Verify rule coverage and both exact delegation pointers against the approved specification, record the result in the feature's completion record, and distinguish instruction consolidation from the remaining tickets' behavior changes.

## Boundaries

This ticket does not translate the remaining project-document corpus, rename application internals, implement the theme, rewrite Git history, modify external personal/platform memory, or resume the deferred journal browser QA.

## Comments

2026-10-31: The user approved publication of this six-ticket breakdown. Ticket readiness is not implementation authorization.

2026-10-31: The user selected this ticket through `implement`. The instruction change and local document/regression checks are complete; review and delivery milestones are tracked separately in the [feature completion record](../completion.md). The triage role remains unchanged; tickets 02–06 are unstarted.
