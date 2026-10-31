# Approved ticket breakdown

Approved interactively on 31 October 2026 using `to-tickets`. Source: [approved specification](spec.md); interview decisions: [design](design.md). The parent specification and design remain unchanged by this publication.

| Ticket | Delivery | Blocked by |
| --- | --- | --- |
| [01](issues/01-canonical-agent-instructions.md) | Canonical agent instructions | None |
| [02](issues/02-light-default-with-persistent-user-preference.md) | Light default with persistent user preference | None |
| [03](issues/03-english-internals-with-compatible-member-workflows.md) | English internals with compatible member workflows | None |
| [04](issues/04-english-contributor-and-operations-documentation.md) | English contributor and operations documentation | None |
| [05](issues/05-english-planning-and-delivery-records.md) | English planning and delivery records | None |
| [06](issues/06-whole-spec-verification-and-handoff.md) | Whole-spec verification and handoff | 01, 02, 03, 04, 05 |

## Graph and scope boundaries

Initial prerequisite-free frontier: **01, 02, 03, 04, 05**. Ticket 06 opens only after all five results are verified complete. The independent tickets can be selected without inventing a dependency merely to impose a preferred order; shared integration still requires coordination and current-source checks.

Each ticket owns its observable checks and must be independently verifiable; testing is not postponed to ticket 06. Shared internal renames stay in one bounded complete batch with all callers/tests, rather than landing broken layer-by-layer changes. The current inventory did not establish a need for a separate prefactoring or expand-contract sequence.

Instruction consolidation, maintained documentation, and dated planning/evidence are separate ownership scopes. The final gate checks their combined consistency, links and compatibility. The legal/data/third-party/history exclusions and deferred journal browser QA from the specification apply to every ticket.

## Specification coverage

- Stories 9 and 18–23, including the prospective commit/PR convention: ticket 01.
- Stories 24–34 and theme/browser evidence: ticket 02.
- Stories 3–8 and 15–17, plus complete internal caller compatibility: ticket 03.
- Stories 1–2, 12–14 and current domain/operations guidance: ticket 04.
- Stories 10–14 and truthful historical planning/delivery narratives: ticket 05.
- Stories 35–37 and whole-spec delivery assurance: ticket 06; each contributing ticket also owns its relevant validation.
- Story 38 and the latest user instruction: this publication creates only the approved tickets. It starts no implementation.

## Publication state

Six separate local tickets are published with `ready-for-agent` triage status. No remote issue/label, application edit, theme change, instruction consolidation or implementation execution is performed. A later explicit user instruction is required to begin implementation. Track execution and evidence separately in the feature's completion record when execution starts.
