# 02: Light default with persistent user preference

**What to build:** A first-time member or staff user sees a light application regardless of the operating-system theme, can switch to dark using the existing French controls, and retains an explicit valid preference when browser storage works. Storage restrictions leave the page usable and its theme control functional.

**Blocked by:** None (can start immediately once implementation is authorized).

**Status:** ready-for-agent

Specification: [English technical content, canonical agent instructions, and a light default theme](../spec.md).

- [x] At the existing pre-render initialization point, choose a valid saved light or dark preference; choose light for an absent, empty, invalid or unreadable saved value, independently of the system preference.
- [x] Demonstrate an unsaved first visit is light with both light and dark system preferences, including when the system-preference API is unavailable. Do not introduce a dependency on that API for the fixed fallback.
- [x] Preserve a saved valid dark or light choice across supported navigation and reload. Keep public and staff preferences origin-scoped; add no cross-host synchronization or new System-mode workflow.
- [x] Preserve manual switching, French target-action labels and visible feedback. A storage-write failure still allows the actual page theme and control feedback to change for the current page.
- [x] Preserve existing light no-JavaScript styling, accessible theme controls, keyboard interaction, focus visibility and normal page behavior.
- [x] Replace existing system-following test expectations with the approved behavior and verify the real shared theme behavior across public, administration/authentication and existing member/preview surfaces using the approved isolated browser boundary.
- [x] Exercise saved-value validation, read failure and write failure through observable behavior; clearly label deliberately injected storage failures as simulations rather than real browser privacy settings.
- [x] Record actual rendered light/dark evidence and the executed revision. A mocked DOM or a changed accessible label alone is not evidence of rendered browser behavior. Use only disposable synthetic fixtures, without a certificate-warning bypass or real email.
- [x] Keep new comments, technical test descriptions and identifiers in English. Record relevant checks and remaining limits in the feature's completion record without closing the separately deferred journal browser QA.

## Boundaries

No visual redesign, new animation, French UI/email translation, account/data migration or production deployment is included. The existing journal browser deferral remains unchanged.

## Comments

2026-10-31: The user approved publication of this six-ticket breakdown. The observable theme-script/browser seams were already approved in the design; this ticket does not authorize starting implementation.

2026-10-31: The user explicitly authorized ticket-02 implementation via `implement`. Implementation, validation and independent review evidence is recorded separately in [completion](../completion.md#ticket-02--light-default-with-persistent-user-preference).
