# 03: Modernize registration through confirmation

**What to build:** A member completes registration, validation, uploads, staged preview, review, and confirmation in the new visual system.

**Blocked by:** [02: Deliver the community homepage](02-deliver-community-homepage.md).

**Status:** ready-for-agent

## Parent

[UI modernization and application separation specification](../spec.md).

## Acceptance criteria

- [x] Business rules and URLs stay unchanged; validation retains valid input.
- [x] Only the owning member session can preview staged files.
- [x] Registration and staff editing retain aligned fields/rules; evidence covers both themes and mobile/keyboard use.

## Verification

Implemented on `feat/ui-separation-03`. Evidence: [registration public journey](../../../docs/testing/registration-public-journey.md); execution and limits recorded in root completion.md. Local validation complete; integration/remote CI/release tracked separately.
