# 04: Modernize secure completion, correction, and consent

**What to build:** Existing emailed links let members finish/correct records and use consent/legal pages with consistent public styling.

**Blocked by:** [02: Deliver the community homepage](02-deliver-community-homepage.md).

**Status:** ready-for-agent

## Parent

[UI modernization and application separation specification](../spec.md).

## Acceptance criteria

- [x] Existing valid URLs/tokens continue working and invalid/expired links fail safely.
- [x] Correction and consent preserve business behavior and private-file authorization.
- [x] Both themes, validation, keyboard and mobile journeys are verified.

## Completion evidence

Verified locally 2026-10-24; see root `completion.md` and `docs/testing/member-completion.md`. No time-based review-token expiry exists; revoked/deleted/status-invalid links qualified. Remote CI and release remain separate.
