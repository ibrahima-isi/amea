# 05: Rehearse independently deployed public and staff login

**What to build:** Two isolated services share existing member data while public registration and staff login/recovery work with independent sessions.

**Blocked by:** [03: Modernize registration through confirmation](03-modernize-registration-confirmation.md), [04: Modernize secure completion, correction, and consent](04-modernize-completion-correction-consent.md).

**Status:** ready-for-agent

## Parent

[UI modernization and application separation specification](../spec.md).

## Acceptance criteria

- [ ] Separate build/deploy targets expose a public route set and a staff authentication surface in rehearsal.
- [ ] Shared PHP rules and schema compatibility remain; host-scoped cookies/session storage prevent crossover.
- [ ] Existing administration stays usable while the new staff surface is expanded; partial targets cannot be cut over prematurely.
