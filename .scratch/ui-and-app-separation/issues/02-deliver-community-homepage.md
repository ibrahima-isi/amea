# 02: Deliver the community homepage

**What to build:** Visitors see direction B with real backend-managed carousel content and clear registration entry points in both themes.

**Blocked by:** [01: Characterize member and staff journeys](01-characterize-member-and-staff-journeys.md).

**Status:** ready-for-agent

## Parent

[UI modernization and application separation specification](../spec.md).

## Acceptance criteria

- [x] Neutral surfaces, Guinea accents, responsive layout, keyboard focus, and reduced motion match the selected direction.
- [x] Staff photo/caption edits appear publicly; autoplay, pause, manual controls, and empty/single-image states work.
- [ ] Use reviewed public media and a verified real logo; unresolved assets remain explicit blockers.

## Execution evidence

Implemented and locally verified UI/carousel behavior; see [browser and HTTP evidence](../../../docs/testing/community-homepage.md) and root completion.md. Ticket remains incomplete: approved logo path contains invalid ASCII bytes, so the verified-real-logo criterion is blocked pending a valid asset.
