# UI modernization and application separation

Status: design discussion consolidated into the [published local specification](../../../.scratch/ui-and-app-separation/spec.md). Test boundaries and all nine implementation tickets are approved. This document remains design context; the local specification and tickets govern implementation. Production changes require explicit approval.

## Purpose and agreed scope

Modernize the AEESGS public website with a lighter interface that preserves Guinea’s red, yellow, and green identity. Introduce the association and encourage eligible pupils, students, and interns to register. Separate the public and staff applications into independent deployments while preserving existing member workflows.

Deliver the public design first, starting with the homepage and extending the approved styling to registration, review/completion, correction, consent, and legal pages. Separate administration afterward with its current interface; its visual redesign is a later phase.

## Public experience

Selected direction: **B — Community & energy**, based on the reviewed throwaway mockup. Use bold sans-serif typography, large community photography, spacious neutral surfaces, and restrained national-color accents. Green remains suitable for primary actions; yellow and red provide visible identity without overwhelming backgrounds or text. Support light and dark themes, initially following device preference.

Homepage sections: association introduction, community photo carousel, three association themes, and registration entry points. Reuse existing association text and suitable public photos. Mockup copy is proposed content and must be reviewed before release. Staff manage carousel photos and captions; introduction and registration sections remain fixed initially.

Retain the existing backend-managed carousel with automatic rotation, pause, and manual controls. Inspect current implementation before changing it; preserve existing data and settings. Include restrained transitions and interaction feedback, respecting reduced motion. Final timing and easing are builder choices.

The repository’s `assets/img/logo.png` is currently a placeholder; finding and verifying the real logo is a release prerequisite. Review images before inclusion; existing upload directories must not be treated as an approved public media library.

## Application boundaries

Keep one repository and PHP/Twig. Use CSS and JavaScript for the design and interactions. Deploy two PHP applications on the same host through the existing Docker/Dokploy approach:

- Public: `aeesgs.org`, including member registration and tokenized completion/correction workflows.
- Staff: `admin.aeesgs.org`, including login/recovery, member administration, document review, carousel management, exports, communications, and settings.

“Shared backend” means shared PHP business code and repositories packaged with each application, accessing one database. It does not mean a separately deployed API service. Each application has its own server, entry points, route availability, and build/deployment target. Merely changing navigation or proxying the same unrestricted application twice is insufficient.

Keep existing business validation in shared services. Independent deployment does not promise arbitrary compatibility between releases: coordinate shared schema changes through backward-compatible migrations. Folder names, build layout, and dependency packaging are builder choices.

## Sessions, files, and compatibility

Use separate public and staff session namespaces/storage and host-scoped cookies. Staff login works only on the administration host after migration. Preserve CSRF protection, role checks, session expiry, and secure document authorization. Public token workflows remain public without granting access to staff resources.

Use shared persistent file storage with access controls. Identity documents and CVs remain private and are served through authorized handlers. Approved carousel images remain publicly accessible. Preserve staged-upload ownership and the existing review/confirmation lifecycle.

Keep existing member URLs and emailed secure links valid. Redirect legacy staff page GET requests to the corresponding administration pages; require login there. Handle legacy form submissions explicitly, with safe recovery rather than automatic replay across hosts. Preserve intended staff destinations through login using validated local paths.

Shared storage and database access do not permit a public route to invoke staff functionality. Expose only each application’s allowed routes and assets; retain authorization checks within handlers.

## Delivery and operations

1. Inventory current routes, sessions, carousel settings, email links, uploads, database migrations, and deployment configuration. Preserve unrelated working-tree changes.
2. Implement and validate the public visual refresh while retaining the existing administration during transition.
3. Separate public/staff deployment targets; rehearse routing, sessions, shared data/files, and compatibility in an isolated environment.
4. Prepare a reviewed production cutover and rollback procedure. Keep existing administration available until the replacement is verified; document how the final move ends old-host staff access.

Production, DNS, and real email actions require explicit approval. Before any production migration, verify backups and a restore path. Only one scheduler/worker owner should execute shared background jobs; choosing which service owns them is a builder decision after inventory.

## Acceptance

- Consistent public styling across the scoped pages, in both themes; Guinea’s identity remains visible.
- Usable mobile layouts and desktop layouts without horizontal overflow; keyboard-accessible controls, visible focus, readable contrast, and reduced-motion behavior.
- Carousel uses real backend-managed content; rotation can be paused and controlled manually.
- Registration, validation errors, staged previews, review, confirmation, secure completion/correction links, and consent continue to work.
- Staff login/recovery, authorized downloads, carousel updates, and existing staff functions work after separation.
- Public and staff sessions remain separate; unauthenticated or wrong-app requests cannot reach protected resources or private files.
- Existing member links remain valid; staff redirects and legacy form recovery behave as specified.
- Both deployment targets build independently; persistent database and uploads survive container replacement. Relevant PHP/regression tests and browser checks pass. Final validation evidence and known limitations are recorded before release.

## Deferred work and builder discretion

Future blog: administrators will publish upcoming and past association activities. Track this as follow-up work; no blog schema, editor, or publishing workflow in this version. Member accounts, personal dashboards, and a staff visual redesign are also deferred.

Builder discretion covers exact CSS tokens/font selection consistent with B, responsive breakpoints, animation timing, directory layout, CI organization, and compatible migration mechanics. No new API service or frontend framework is required. Public content and the real logo require review before release.
