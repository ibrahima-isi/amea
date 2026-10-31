# 04: English contributor and operations documentation

**What to build:** A contributor or maintainer can read the project's current onboarding, domain vocabulary, architectural decisions, testing and operational guidance in consistent English and follow working links, while the real French product labels and existing architectural decisions remain accurate.

**Blocked by:** None (can start immediately once implementation is authorized).

**Status:** ready-for-agent

Specification: [English technical content, canonical agent instructions, and a light default theme](../spec.md).

- [x] Inventory the current maintained contributor/operations documentation, README, glossary and ADR material; translate all remaining French technical narrative in that corpus without gratuitously rewriting material already in English.
- [x] Preserve the domain model and existing ADR decisions, including independent public/staff entry, current shared data/files and deferred physical separation. Translate their presentation rather than inventing new domain or architecture decisions.
- [x] Keep established French interface labels as explicit labels/aliases and exact contract values where needed for accuracy. Do not translate application copy, email copy, country data, human content or legal source documents as technical prose.
- [x] Preserve the meaning of commands, environment contracts, deployment prerequisites, approval boundaries, rollback instructions and test limitations. Correct only translation-related discrepancies; do not change the completed deployment design or claim production qualification.
- [x] Repair affected navigation links and heading anchors after translation, including links between the maintained documents and domain references.
- [x] Keep durable agent rules assigned to the canonical-instruction ticket; do not recreate supplementary instruction guides or copy those rules into a competing source.
- [x] Verify completeness by a classified content inventory and review, using language-marker searches only as aids. Document legitimate quoted-label/data/third-party exceptions rather than treating every French word as a violation.
- [x] Record translation coverage, link checks and limitations in the feature's completion record. No application, theme or deployment behavior is changed by this ticket.

## Boundaries

Dated planning and delivery narratives belong to ticket 05. The parent specification and its approved scope are not rewritten. Published Git/PR history, legal sources, dependencies, generated artifacts and production resources remain unchanged.

## Comments

2026-10-31: The user approved publication of this six-ticket breakdown. This corpus is independently verifiable and has no implementation prerequisite on the theme or internal-name refactor.

2026-10-31: The user explicitly authorized ticket-04 implementation and requested progressive completion updates. See [completion](../completion.md#ticket-04--english-contributor-and-operations-documentation) for inventory, translation checkpoints, checks and independent review; CI/merge remain separate milestones.
