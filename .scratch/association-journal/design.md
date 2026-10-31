# Association journal — scope in progress

## Choices confirmed on 29 October 2026

The user confirmed through interactive input a single public section with three publication types: upcoming activities, reports on past activities and announcements.

Writing and publication take place only in the independent staff application. The public website provides no login link, administration button or redirect to management. The access separation defined in [ADR 0001](../../docs/adr/0001-independent-public-and-staff-entry.md) applies to the journal.

The current website design is approved; the journal must fit into it. The [association-site research](../ui-visual-refresh/journal-research-2026-10-29.md) provides references without imposing their CMS or internal organization.

## Confirmed behavior choices

On 29 October, the user confirmed both recommendations: authorized administrators, draft followed by direct publication; automatic removal of ended activities from “À venir”, retaining their pages in the archives. Activity reports are written separately.

The [proposed complete design](../../docs/superpowers/specs/2026-10-29-association-journal-design.md) details the screens and rules needed for a first version. New details are identified there as proposals before final approval.

## State

An exploratory public mockup was prepared on 29 October and is available locally at `http://localhost:18444/journal.html`. It retains the approved palette, presents the three types and supports filtering and opening a publication. Content/dates are fictional and explicitly identified as such. No Administration/Connexion entry is present. No actual management, authentication, writing or publication functionality is added.

The Accueil/Inscription buttons explain their future public destinations in this mockup; they do not replace existing journeys. The editorial workflow and archiving have since been confirmed through explicit interactive input, without treating the earlier “continue” message as those answers.

The complete design was approved on 30 October and implementation authorized. Actual routes, data, management and reading are implemented in the dedicated worktree; [evidence and limits](completion.md). No addition to PR17 or production deployment. The local synthetic demo received the additive migration after an explicit identity check, without a reset or fictional content.
