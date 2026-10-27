# 05: Rehearse independently deployed public and staff login

**What to build:** Two isolated services share existing member data while public registration and staff login/recovery work with independent sessions.

**Blocked by:** [03: Modernize registration through confirmation](03-modernize-registration-confirmation.md), [04: Modernize secure completion, correction, and consent](04-modernize-completion-correction-consent.md).

**Status:** ready-for-agent

## Parent

[UI modernization and application separation specification](../spec.md).

## Acceptance criteria

- [x] Separate build/deploy targets expose a public route set and a staff authentication surface in rehearsal.
- [x] Shared PHP rules and schema compatibility remain; host-scoped cookies/session storage prevent crossover.
- [x] Existing administration stays usable while the new staff surface is expanded; partial targets cannot be cut over prematurely.

## Execution evidence

Locally verified: [application/identity rehearsal](../../../docs/testing/app-separation-login.md). 76 HTTP/schema checks, 8 actual HTTPS Chrome checks, legacy HTTP 35 + token 28, PHPUnit 141/241 and standalone regressions passed. Staff target remains partial; 06/07 are the next frontier. Independent review and exact-head CI are separate pending milestones.
