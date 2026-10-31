# Specification: English technical content, canonical agent instructions, and a light default theme

Status: ready-for-agent

Design and testing boundaries approved: 31 October 2026.

Execution state: specification only. The user will later choose direct implementation or a ticket breakdown. This triage status records specification readiness and does not authorize either execution path.

## Problem Statement

Contributors encounter mixed English and French technical documentation, comments and internal names. An English contribution policy is not explicit, and durable agent rules are distributed across a canonical file and additional guides. This makes instructions harder to maintain consistently.

The application currently uses a saved theme preference or the operating-system preference. Consequently, a first-time visitor with a dark system preference sees a dark interface, while the approved product default is light. Existing tests partly encode the previous system-following behavior.

The maintainer wants consistent English technical material and one authoritative instruction source, while preserving French member/staff experiences and existing compatibility contracts.

## Solution

Use English for current first-party technical documentation, code comments, internal identifiers, future commit messages and new PR text. Retain French application and email content, existing URLs, serialized/session/form contracts and persisted business values.

Keep `AGENTS.md` as the sole source of durable project instructions. `CLAUDE.md` and `GEMINI.md` each contain exactly `read AGENTS.md`. Consolidate the remaining agent guides into that source and remove the redundant guides after repairing references. README, operational guides, glossary, ADRs and task records remain separate English documents.

Apply light on the first visit regardless of the system preference. A valid explicitly saved light or dark choice takes priority. Keep manual switching, preference persistence when available, French controls and a usable fallback when browser storage is unavailable.

## User Stories

1. As a contributor, I want current technical documentation in English, so that engineering context is consistent across the repository.
2. As a contributor, I want existing technical documents translated as well as new ones, so that the policy does not leave the current mixed-language material unresolved.
3. As a contributor, I want first-party code comments in English, so that I can understand implementation intent without switching languages.
4. As a contributor, I want internal methods, functions and variables named in English, so that related modules use consistent technical vocabulary.
5. As a maintainer, I want identifier changes applied to every caller and relevant test, so that renaming does not break application behavior.
6. As a member, I want the existing French interface preserved, so that technical-language cleanup does not change my registration experience.
7. As a staff user, I want existing French administration labels and feedback preserved, so that my workflows remain familiar.
8. As a member or staff user, I want existing French emails preserved, so that technical cleanup does not change the language of messages I receive.
9. As a contributor, I want future commit messages and PR text in English, so that new engineering history follows the same convention as the source documentation.
10. As a maintainer, I want published Git history preserved, so that adopting this policy does not rewrite existing commits or invalidate shared references.
11. As a maintainer, I want translated task records to retain their dates, decisions, evidence and limitations, so that translation does not create a false delivery or validation claim.
12. As a reader, I want document links and translated heading references to work, so that I can still navigate the project history and guides.
13. As a contributor, I want exact French interface labels and contract values identifiable within English prose, so that documentation remains precise about the real application.
14. As a maintainer, I want legal sources, dependency content and human data preserved, so that technical translation does not alter unrelated material.
15. As a returning member, I want my existing URLs and secure links to remain valid, so that I can continue the same public workflows.
16. As a member with an unfinished registration, I want staged session data to remain compatible, so that a deployment does not invalidate my progress merely because an internal name changed.
17. As a maintainer, I want form, JSON and persisted business values preserved, so that technical naming cleanup requires no data migration or consumer change.
18. As an agent, I want one canonical project instruction file, so that durable rules are maintained in one place.
19. As a Claude-based agent, I want the delegation file to contain only its exact pointer, so that it cannot accumulate a competing instruction set.
20. As an agent using the other existing delegation file, I want the same canonical pointer, so that all project entry points reach the same rules.
21. As a maintainer, I want the current tracker, triage and domain-document rules retained during consolidation, so that centralization does not discard project conventions.
22. As a contributor, I want documentation and task records to remain in their appropriate locations, so that one instruction source does not become a combined manual and progress ledger.
23. As a maintainer, I want security, signing, isolated-worktree and delivery rules preserved, so that instruction cleanup does not weaken the collaboration workflow.
24. As a first-time visitor, I want the application to open in light mode even when my system is dark, so that the product has the agreed default appearance.
25. As a user who explicitly selected dark mode, I want that saved choice to win on my next visit, so that the new default does not erase my preference.
26. As a user who explicitly selected light mode, I want that choice to remain effective, so that preference handling is consistent for both themes.
27. As a member or staff user, I want to switch themes manually, so that I can choose the appearance that suits me.
28. As a user with working browser storage, I want my selection to survive navigation and reload, so that I do not need to select it on every page.
29. As a user, I want the appropriate theme applied before the page is visibly rendered, so that initialization does not introduce an avoidable theme flash.
30. As a user with an invalid saved theme value, I want a predictable light fallback, so that stale storage cannot leave the page in an unsupported theme state.
31. As a user whose browser blocks storage reads, I want the page to remain usable in light mode, so that theme initialization does not prevent normal interaction.
32. As a user whose browser blocks storage writes, I want theme switching to work for the current page, so that a failed preference save does not break the control or its label.
33. As a member or staff user, I want the shared default rule to apply consistently to public, administration, authentication and existing preview surfaces, so that appearance does not change unpredictably between supported pages.
34. As a keyboard user, I want existing theme controls and visible feedback preserved, so that the default change does not reduce accessibility.
35. As a maintainer, I want tests to observe theme behavior and real application workflows, so that validation remains useful when internal implementation details change.
36. As a maintainer, I want simulated storage failures labelled separately from normal browser execution, so that test evidence accurately describes what was demonstrated.
37. As a maintainer, I want theme regression work distinguished from the deferred journal browser QA, so that one feature's checks do not incorrectly close another feature's remaining work.
38. As a project owner, I want a specification before selecting implementation or ticket breakdown, so that this planning step does not silently start execution.

## Implementation Decisions

- Apply the English policy to the current tracked first-party technical content, including existing task/design/evidence narratives, maintained guides, glossary and ADR text. Inventory the implementation base rather than treating a preliminary language-marker scan as an exhaustive compliance verdict.
- Preserve the meaning and provenance of translated records: dates, historical decisions, source revisions, test counts, unresolved limitations and delivery states remain unchanged. Translation must not turn a deferred check into a completed one.
- English technical prose may quote exact French interface labels, contract literals and proper names when needed for accuracy. These exceptions are not permission to retain French explanatory narrative.
- Translate first-party technical comments across the relevant source and template languages. Separate comments from user-visible strings, business data and third-party code before editing.
- Rename internal identifiers coherently across models, repositories, services, controllers, frontend/template consumers and tests. Determine compatibility at the boundary: names submitted by clients, exposed through existing contracts, persisted, or stored in an in-progress session remain stable. Translate surrounding internal names without changing those contract literals.
- Preserve existing database schemas, persisted values, request/response shapes, route names, secure-link behavior and staged-registration compatibility. No migration or normalization of stored values is part of this work.
- Consolidate durable agent rules into the canonical root instruction source. Retain the current project/security/delivery rules and the useful tracker, triage and domain-document conventions before removing their redundant guide sources. Repair references to the removed instruction guides.
- Keep the two existing delegation files as one-line pointers to the canonical source. Introduce no differently named competing instruction file. Keep ordinary project documentation and per-task evidence separate from durable agent rules.
- Preserve existing glossary concepts and ADR decisions while translating their technical presentation. English terminology may retain the established French interface labels as explicit labels or aliases; this is not a domain redesign.
- Make the shared theme initialization choose a valid saved light/dark value, otherwise light, independently of operating-system preference. Apply the result at the existing pre-render initialization point across the supported surfaces using that shared behavior.
- Preserve manual theme switching and French action labels. Save a user's explicit selection when storage works. An unavailable read, invalid saved value or unavailable system-preference API must not prevent the light fallback; a failed write must not prevent page-local switching or correct control feedback.
- Preserve existing light no-JavaScript styling, accessible controls and normal page behavior. Origin-scoped preferences remain origin-scoped; this change introduces no cross-host synchronization, new System option or new theme-selection workflow.
- Update the existing tests that encode system-following defaults to the newly approved fixed default. Preserve French UI and compatibility assertions when translating test names or renaming internal interfaces.
- Future commits and PR text use English. Existing published commits and historical external PR metadata are not rewritten to satisfy the new convention.

## Testing Decisions

- The testing boundaries were proposed in the design and confirmed with the user's scope approval. Prefer the existing highest observable boundaries: actual theme-script/browser behavior and existing model/repository/service/HTTP regression interfaces. Do not test private function layout, mirror constants from the implementation, or create production interfaces solely for testing.
- Use focused shared-theme checks and the existing disposable loopback/headless Chrome harness. Existing homepage and tokenized-member browser scenarios are prior art; some currently assert a system-following default and must change with the requirement.
- Verify an unsaved first visit is light with both light and dark system preferences. Verify valid saved light and dark preferences take priority, toggling updates the actual page and French control feedback, and the saved selection survives reload/navigation when storage is available.
- Verify invalid stored values and unavailable reads produce a usable light fallback. Verify unavailable writes still permit page-local switching and feedback. Distinguish deliberately injected storage failures from a browser's real privacy configuration in the evidence.
- Check the shared behavior on public, staff/authentication and existing member/preview surfaces without treating a mock DOM alone as proof of actual browser rendering. Retain the existing keyboard/accessibility and no-JavaScript light fallback behavior.
- Reuse SQLite-backed PHP integration/unit suites and the current standalone and isolated HTTP regressions to protect business behavior after internal renaming. Cover registration staging/confirmation, existing French values, authorization, secure documents and applicable correction/consent/reset workflows through their established interfaces.
- Audit current first-party documentation, technical comments and identifiers against the English policy while classifying legitimate contract/data/third-party exceptions. Check translated-heading links and references to removed instruction guides. A simple accent or word scan alone is insufficient to prove completeness.
- Verify both delegation files contain only their exact canonical pointer, all durable rules were accounted for, and the canonical instructions explicitly state the approved language and theme conventions.
- Future implementation must use signed isolated-worktree commits, independent Standards/Spec review, a PR into the existing development branch and CI on the exact final head. Keep local validation, CI, merge and any deployment as distinct milestones.
- This specification publication runs no implementation, browser, business or production tests. The test strategy is recorded for the execution path the user chooses later. No certificate-warning bypass, real email or existing live dataset is needed for these isolated checks.

## Out of Scope

- Implementing the changes, creating implementation tickets, or choosing the task graph during this specification-only step.
- Translating the French product interface or emails into English, changing member/staff journeys, or rewriting human-authored application content.
- Renaming compatibility URLs, form/JSON/session keys, persisted enum values or database schemas; migrating or normalizing data.
- Rewriting published Git commits or retrospectively changing historical external PR metadata.
- Translating dependencies, vendor/minified third-party code, legal source documents, user data, media names or generated/runtime artifacts as if they were technical narrative.
- Adding a System theme mode, cross-host preference synchronization, a new visual design, animations or unrelated accessibility redesign.
- Resuming or declaring completion of the separately deferred association-journal browser QA.
- Changing the completed deployment-stack consolidation, deploying production, modifying DNS, sending real SMTP mail, changing production resources or cleaning unrelated worktrees.
- Moving global personal/platform memory into the repository or modifying external skill/provider instructions. The canonical-source decision concerns this project's durable agent rules.

## Further Notes

The [approved design](design.md) records the interview decisions. The implementation base must include the already-merged Compose consolidation; its additional technical task documents belong in the translation inventory.

Exact preserved examples include the French business values `Masculin`, `Féminin`, `ELEVE`, `ETUDIANT`, `STAGIAIRE` and `Diplômé(e)`, and the staged-registration key `valid_pays_ids`. Their language does not make them ordinary internal names that can be replaced cosmetically. Country labels and the French legal source also retain their existing meaning and role.

The earlier journal browser record contains partial observations and an explicit user deferral. Preserve that status when translating it. A theme check, technical translation or successful CI run does not replace the outstanding journal acceptance work.

The user has approved the scope and requested only this local specification. Stop here. A later explicit instruction will select direct implementation or a numbered ticket breakdown; `ready-for-agent` is specification readiness, not execution authorization.
