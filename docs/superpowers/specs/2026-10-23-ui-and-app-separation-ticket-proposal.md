# Ticket proposal — UI modernization and application separation

Approved historical breakdown. All nine tickets are now published separately in the [local tracker](../../../.scratch/ui-and-app-separation/issues/). Published ticket files are authoritative for execution.

## 01: Characterize member and staff journeys

**Blocked by:** None

**What it delivers:** An isolated test environment exercises existing registration, secure document access, and staff login before changes; route/session/upload/job inventory identifies migration constraints.

- [ ] Baseline HTTP/browser scenarios cover a valid registration and authorized/unauthorized document access with safe fixtures.
- [ ] Existing PHP regression checks run; gaps and route/session/worker ownership inventory are recorded.
- [ ] Feature PRs targeting dev receive relevant CI checks without deployment.

## 02: Deliver the community homepage

**Blocked by:** 01

**What it delivers:** Visitors see direction B with real backend-managed carousel content and clear registration entry points in both themes.

- [ ] Neutral surfaces, Guinea accents, responsive layout, keyboard focus, and reduced motion match the selected direction.
- [ ] Staff photo/caption edits appear publicly; autoplay, pause, manual controls, and empty/single-image states work.
- [ ] Use reviewed public media and a verified real logo; unresolved assets remain explicit blockers.

## 03: Modernize registration through confirmation

**Blocked by:** 02

**What it delivers:** A member completes registration, validation, uploads, staged preview, review, and confirmation in the new visual system.

- [ ] Business rules and URLs stay unchanged; validation retains valid input.
- [ ] Only the owning member session can preview staged files.
- [ ] Registration and staff editing retain aligned fields/rules; evidence covers both themes and mobile/keyboard use.

## 04: Modernize secure completion, correction, and consent

**Blocked by:** 02

**What it delivers:** Existing emailed links let members finish/correct records and use consent/legal pages with consistent public styling.

- [ ] Existing valid URLs/tokens continue working and invalid/expired links fail safely.
- [ ] Correction and consent preserve business behavior and private-file authorization.
- [ ] Both themes, validation, keyboard and mobile journeys are verified.

## 05: Rehearse independently deployed public and staff login

**Blocked by:** 03, 04

**What it delivers:** Two isolated services share existing member data while public registration and staff login/recovery work with independent sessions.

- [ ] Separate build/deploy targets expose a public route set and a staff authentication surface in rehearsal.
- [ ] Shared PHP rules and schema compatibility remain; host-scoped cookies/session storage prevent crossover.
- [ ] Existing administration stays usable while the new staff surface is expanded; partial targets cannot be cut over prematurely.

## 06: Move staff member review and private documents

**Blocked by:** 05

**What it delivers:** Staff use the administration host to review/manage members submitted through the public app and access private documents securely.

- [ ] Member listing, detail/edit, KYC, archived records, and associated document actions remain available by role.
- [ ] Shared files/data are visible to authorized users only; staged uploads and public sessions cannot grant staff access.
- [ ] Public member preview/correction workflows continue working against shared records.

## 07: Move staff content and operational tools

**Blocked by:** 05

**What it delivers:** Staff manage carousel content, communications, exports, settings, accounts, profile, and progression on the administration host.

- [ ] Existing tool permissions and user-visible behavior are preserved without staff redesign.
- [ ] Carousel changes appear publicly; exports stay authorized; email behavior is tested through safe substitutes.
- [ ] One shared background-job owner is configured and duplicate execution is checked.

## 08: Preserve links and finish route isolation

**Blocked by:** 06, 07

**What it delivers:** Member links keep working and old staff pages guide staff to the new host without exposing staff actions publicly.

- [ ] Legacy staff GET URLs redirect to matching destinations with login and validated local return paths.
- [ ] Old form submissions recover safely without replaying actions across hosts.
- [ ] Complete route matrix proves public/staff isolation including downloads, recovery, and AJAX/auxiliary endpoints.

## 09: Verify integrated release and rehearse rollback

**Blocked by:** 08

**What it delivers:** A release candidate demonstrates the full public redesign and two-service separation, with evidence and an approved-action boundary for production.

- [ ] Build/start each target independently; replace containers and verify database/upload persistence in isolation.
- [ ] Run full workflow/accessibility/theme/browser and PHP regression checks on the final candidate.
- [ ] Record content/logo review, schema compatibility, backup/restore rehearsal, rollout order, rollback steps, and remaining external blockers.
- [ ] No production/DNS/real-email actions occur without explicit approval; completion tracker separates local, CI, merge, and release evidence.
