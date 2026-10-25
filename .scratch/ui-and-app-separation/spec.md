# Specification: AEESGS public UI modernization and application separation

**Status:** ready-for-agent

Published to the configured local Markdown tracker on 2026-10-23. Test boundaries and nine-ticket breakdown approved. Implementation and validation state is recorded in the root completion.md.

## Problem Statement

The public interface uses dense color surfaces and inconsistent typography that obscure AEESGS’s community identity and make the experience feel dated. Public member journeys and staff administration are deployed together. The association needs a lighter public experience that retains Guinea’s colors, preserves registration and private documents, and permits independent public/staff deployments.

## Solution

Deliver the selected B, “Community & energy” direction: bold sans-serif typography, large community photography, neutral surfaces, restrained red/yellow/green accents, light/dark themes, and controlled motion. Apply the design across public journeys. Separate public and staff PHP/Twig applications on the same Docker/Dokploy host, sharing business code, member data, and controlled persistent storage. Deliver public design first; move administration afterward without redesigning staff screens.

## User Stories

1. As a visitor, I want to recognize Guinea’s red, yellow, and green in a lighter design, so that the website remains culturally recognizable and comfortable to read.
2. As a visitor, I want to see the selected community-focused homepage direction, so that I understand the association’s identity.
3. As a visitor, I want to read an introduction to AEESGS, so that I understand who the association serves.
4. As a eligible member, I want to find a clear registration entry point, so that I can join the association.
5. As a visitor, I want to see community photos and captions, so that I can discover association life.
6. As a visitor, I want to use automatic carousel rotation with pause and manual controls, so that I can view photos at my own pace.
7. As a staff user, I want to manage carousel photos and captions through administration, so that public content remains current.
8. As a visitor, I want to understand the association’s three themes, so that I understand the value of participating.
9. As a visitor, I want to see readable light and dark themes following my device preference, so that the website suits my viewing conditions.
10. As a mobile visitor, I want to use layouts that fit my screen, so that I can read and register without horizontal scrolling.
11. As a keyboard user, I want to navigate links, forms, and carousel controls with visible focus, so that I can complete the same tasks as pointer users.
12. As a visitor sensitive to motion, I want to have reduced-motion preferences respected, so that animations do not prevent comfortable use.
13. As a member, I want to register through the existing public URLs, so that bookmarks and shared links remain useful.
14. As a member, I want to receive clear validation errors without losing valid form data, so that I can correct my registration.
15. As a member, I want to upload required documents and preview my own staged files, so that I can verify my submission securely.
16. As a member, I want to review and confirm registration, so that my member record is correctly saved.
17. As a member, I want to complete or correct my record through existing secure links, so that previously received links continue to work.
18. As a member, I want to read legal information and complete consent workflows, so that I can make an informed choice.
19. As a member, I want to see consistent styling throughout registration and completion, so that the journey feels coherent.
20. As a staff user, I want to sign in and recover access on the administration host, so that staff workflows remain available.
21. As a staff user, I want to reach the corresponding administration page from an old staff link, so that my bookmarks remain useful.
22. As a staff user, I want to receive safe recovery guidance for a form submitted to an old address, so that a migration does not silently repeat my action.
23. As a member, I want to keep registration sessions separate from staff sessions, so that my public interaction cannot become a staff login.
24. As a member, I want to have private documents accessible only to authorized requests, so that sensitive uploads are protected.
25. As a staff user, I want to review records and documents submitted through the public app, so that the two applications share current member information.
26. As a staff user, I want to retain existing exports, communications, settings, and member-management functions, so that application separation does not remove essential tools.
27. As a visitor, I want to see approved carousel photos without gaining access to private uploads, so that public photography and private records stay distinct.
28. As a maintainer, I want to deploy the public and administration applications independently from one repository, so that a public UI release does not require replacing the staff app.
29. As a maintainer, I want to reuse shared PHP business rules and one database, so that validation and member records do not diverge.
30. As a maintainer, I want to replace containers without losing uploads or database records, so that deployments preserve association data.
31. As a maintainer, I want to coordinate backward-compatible shared database changes, so that one application can remain available while the other is updated.
32. As a maintainer, I want to run shared background work under one designated owner, so that emails and scheduled work are not duplicated.
33. As a association representative, I want to review reused text, photos, and the real logo before release, so that public material accurately represents AEESGS.
34. As a maintainer, I want to rehearse migration and rollback with verified backups before production approval, so that the transition has a recoverable operational path.
35. As a association representative, I want to keep a future admin-published activities blog recorded as deferred work, so that upcoming and past activities can be addressed without expanding this delivery.
36. As a contributor, I want to follow tickets with explicit blockers and completion evidence, so that progress is verifiable and work starts in the right order.

## Implementation Decisions

- One repository, two independently buildable and deployable PHP applications; no additional backend API service or JavaScript frontend framework.
- Public host is aeesgs.org; staff host is admin.aeesgs.org. Each exposes an explicit allowed route set; navigation changes alone do not constitute separation.
- Shared backend means shared PHP business services and repositories packaged into both apps, with one database. Coordinate backward-compatible migrations; independent deployments do not imply arbitrary release compatibility.
- Public workflows include registration, staged review/confirmation, tokenized completion/correction, consent, and legal pages. Preserve French UI and existing business validation.
- Staff app retains login/recovery, role authorization, member management, document review, carousel management, exports, communications, and settings.
- Separate host-scoped session cookies, namespaces, and server-side session storage prevent public/staff session crossover. Preserve CSRF protection, expiry, and authorization.
- Use shared persistent file storage with controlled access. Private identity documents and CVs remain behind authorized handlers; publish only approved carousel photography. Preserve staged-upload ownership.
- Preserve existing public member URLs and emailed secure links. Redirect legacy staff GET pages to corresponding staff routes and require login. Handle stale staff form submissions without cross-host action replay; use validated local return destinations.
- Retain backend-managed carousel content and current automatic rotation behavior, adding or retaining accessible pause/manual controls. Respect reduced motion and handle empty/single-image states gracefully.
- Introduction, association themes, and registration sections remain fixed initially. Staff manage carousel photos/captions. Review association copy and suitable public photos; the real logo must replace the current placeholder before release.
- Light and dark themes follow device preference initially. Typography, spacing, accessible contrast, responsive layout, and motion follow the chosen direction across public journeys.
- Introduce public design before administration extraction. Preserve current staff availability during the transition; remove old-host staff access only after the replacement is verified.
- Reuse the existing Docker/Dokploy approach with separate services on one host, persistent database/uploads, and one designated owner for shared background jobs.
- Capture implementation, tests, CI, merge, and release as distinct completion milestones. Preserve unrelated working-tree edits and use feature branches targeting dev.

## Testing Decisions

Approved test boundaries:

- Primary boundary: real browser/HTTP journeys against two locally or staging-deployed applications with isolated test database/files and safe email handling. Test externally visible behavior, not template internals, class layout, or private methods.
- Cover homepage rendering and carousel changes from staff administration; light/dark themes; mobile and desktop layouts; keyboard/focus behavior; reduced motion; and registration through validation, own-file preview, review, confirmation, correction, and consent.
- At the same HTTP boundary, prove wrong-app routes are unavailable, host/session isolation, CSRF rejection, unauthorized document denial, expired/invalid links, legacy staff redirects, safe stale-form handling, and valid member-link compatibility.
- Verify shared data/file visibility across the two apps, independent build/start, container replacement persistence, backward-compatible schema transition, and one background-job owner in an isolated deployment rehearsal.
- Reuse PHPUnit model/repository/service tests and standalone permission/password-reset/OOP regression scripts for focused business regressions. Existing controller integration tests largely assert dispatchable signatures; they are prior scaffolding, not proof of the required user journeys.
- Introduce only the minimal test harness needed for HTTP/browser execution; avoid new production interfaces solely for tests. Include CI coverage for the actual dev-targeted delivery workflow rather than relying on main-only triggers.
- Record screenshots for public layouts in both themes, executed checks, revision, and limitations. Test data and local fakes do not qualify production storage, delivery, or restoration.

## Out of Scope

- New member accounts or personal dashboards.
- Staff visual redesign.
- Activities blog implementation; track future administrator publication of upcoming and past activities separately.
- New API service, frontend framework migration, duplicated databases, or multiple repositories.
- Production cutover, DNS changes, real email sends, or other external qualification without explicit approval.

## Further Notes

The agreed design is the source for detailed visual intent. Mockups are exploratory, not production code or approved final copy. The real logo and reviewed public content are release prerequisites. Before an approved production transition, verify backups and a restore path and review a rollback procedure. An isolated rehearsal can be delivered without production authorization.

The configured tracker is local Markdown with five default triage statuses. Nine implementation tickets are published separately in dependency order. Read tracker configuration before changing status. Record execution evidence in the root completion record; ready-for-agent does not override blockers. Implementation and validation state is recorded in the root completion.md.
