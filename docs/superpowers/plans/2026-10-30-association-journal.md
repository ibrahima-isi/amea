# Association Journal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver the approved AEESGS association journal with independent public reading and permission-controlled staff editing, publishing and withdrawal.

**Architecture:** Extend existing PHP/Twig/PDO application with a journal repository, editorial service and controlled image service. Public and staff route allowlists remain distinct; archive membership is computed from event dates, not a scheduler. Use one shared article partial without staff controls, and separate staff/public controllers.

**Tech Stack:** Existing PHP8, PDO/MySQL8, Twig, Bootstrap5/vanillaJS; PHPUnit11/SQLite plus isolated Docker HTTPS HTTP journeys.

**Spec:** `docs/superpowers/specs/2026-10-29-association-journal-design.md`, fully approved interactively 2026-10-30.

## Global Constraints

- Work only in `/Users/ibrahimadiallo/.codex/worktrees/association-journal/amea`, branch `feat/association-journal`, based on reviewed ref `4b16e1644bcf34c28db0d3922fd7687ddf9a0a8d` (former PR17 head). PR17 was squash-merged as dev23b5c55 on2026-10-30; its full tree equals the original base. Reconcile this history without losing journal changes or force-pushing. Browser QA and current-head CI remain independent gates.
- Original checkout and `/private/tmp/amea-local-20261027` demo worktree/data are preserved. No production action, main push, unsigned commit, bulk staging or journal demo content seeded into a real application.
- Sign every commit with the existing configured key; if signing fails stop the commit operation and resolve it, never retry unsigned. PRs target dev. Do not merge a draft or bypass protections.
- Strict public→staff isolation: no links, login/edit controls, previews or redirect bridges from any public page or error. Wrong-app staff requests return404 without Location. Authentication, module permissions, CSRF, session expiry/revocation and existing private documents remain unchanged.
- Preserve every existing French UI, enum, registration/correction/consent/carousel workflow. No WordPress, new framework, HTML editor, comments, payments, RSVP, newsletter, scheduling or destructive delete.
- Statuses DRAFT/PUBLISHED; types ACTIVITY/REPORT/ANNOUNCEMENT. An ended ACTIVITY stays ACTIVITY in archives; never synthesize REPORT. First published_at and slug remain stable after edit/withdraw/republish.
- Clock is explicit Africa/Dakar and injectable for tests. All public queries require PUBLISHED. Draft text and covers must be inaccessible publicly even with guessed identifiers or prior staff cookies.
- Tests at existing accepted seams: PHPUnit integration for repositories/services; real HTTP behavior for roles, CSRF, publication/withdrawal, covers and date views; browser for responsive/light/dark/keyboard. No CSS snapshots or implementation-mirroring tests.
- Execute one implementation agent at a time, then scoped spec/quality review; broad final branch review. No implementation worker may spawn agents.

## Fixed module interfaces

New table `journal_posts`: id, revision(default1), type, status, slug(unique), title, summary, body, cover_path(nullable), cover_mime(nullable), cover_alt, activity_start_at(nullable), activity_end_at(nullable), activity_start_time_set(bool), activity_end_time_set(bool), location(nullable), published_at(nullable), created_by, updated_by, created_at, updated_at. Datetimes are formatted `Y-m-d H:i:s` in Africa/Dakar (same UTC offset, but keep explicit zone). No destructive FK cascade into publications. Revision-based compare-and-set protects concurrent state/date writes.

`JournalRepository(PDO $pdo)` exposes:

```php
findById(int $id): ?array;
findPublishedBySlug(string $slug): ?array;
findAdminPage(int $page = 1, int $perPage = 20, array $filters = []): array; // items,total
findPublicPage(string $view, int $page, int $perPage, string $now): array; // items,total
findRecentPublished(int $limit = 3): array;
insert(array $values): int;
update(int $id, array $values, int $expectedRevision): bool; // atomic CAS, increments revision; false if stale
```

Public views: all/upcoming/reports/announcements/archives, whitelist only. upcoming: published ACTIVITY ending at or after now; archives: published ACTIVITY ending before now. all includes all published types, with normal pagination. New list limits bounded1..50; page >=1. All/default/reports/announcements newest published_at/id first; upcoming oldest start/id first; archive newest end/id first. Bind values; SQL field/order fragments come solely from internal whitelist.

`JournalService(JournalRepository $posts, ?callable $clock = null)` exposes:

```php
save(?int $id, array $input, int $actorId, bool $publish = false, ?array $trustedCover = null): array;
validate(?int $id, array $input, int $actorId, bool $publish = false): void; // same validation, no writes
withdraw(int $id, int $actorId, ?int $expectedRevision = null): array;
```

Input whitelist: type,title,summary,body,cover_alt,activity_start_date,activity_start_time,activity_end_date,activity_end_time,location,remove_cover. Cover path/MIME never accepted from posted input; trustedCover is produced by the media service. Title1..180 and validtype needed for draft. Publish requires summary1..320 and body1..50000, required event dates/place, nonempty alt if cover; editing existing PUBLISHED must also validate publish completeness. Draft may be incomplete, but provided dates/times must be valid. Missing event end date defaults to start date; absent hours normalize start00:00:00/end23:59:59 with flags preserving the original optional-time representation. Reject end<start, invalid dates and array-valued text; non-ACTIVITY clears event fields. Immutable first published_at and URL, updated_at on change. Missing ID throws OutOfBoundsException; validation throws InvalidArgumentException with safe French message. Generate stable unique ASCII slug with random suffix on creation; changing title never changes it. Existing published edit remains published. remove_cover clears association, not physical file deletion. created_by/updated_by are internal only.

For an existing record, posted revision is a concurrency precondition, never an editable value. Staff forms must include and validate it for save/publish/withdraw. Service read+write also checks the captured revision atomically; stale state raises `JournalConflictException` (safe French message, controller409). The migration is still unapplied, so revision belongs in new migration07, baseline and SQLite fixture. `validate()` permits field checks before storing an upload without duplicating business rules or modifying content.

## Task 1: Schema, persistence and editorial state machine

**Files:** create `migrations/07_create_association_journal.php`, `src/Repository/JournalRepository.php`, `src/Service/JournalService.php`, `src/Service/JournalConflictException.php`, `tests/Integration/Repository/JournalRepositoryTest.php`, `tests/Integration/Service/JournalServiceTest.php`; modify `schema.sql`, `tests/bootstrap.php` to add empty SQLite journal fixture schema.

**Consumes:** existing PDO/service conventions and makeTestDb(). **Produces:** exact module interfaces/table above; no routes or UI yet.

- [x] RED: repository test with DRAFT notice, PUBLISHED notice, PUBLISHED future ACTIVITY and PUBLISHED ended ACTIVITY at fixed `2026-10-30 12:00:00`; assert public all only3, upcoming onlyfuture, archives onlyended, unknowndraftslug null. Assert pagination/count/order and bound limits. Run `./vendor/bin/phpunit --filter JournalRepositoryTest` and record class/method-missing failure.
- [x] Implement idempotent MySQL `CREATE TABLE IF NOT EXISTS journal_posts` migration, matching baseline schema and SQLite test fixture. Use indexes `(status,published_at,id)` and `(status,type,activity_end_at)`. Preserve migrations00–06. Query pattern:

```sql
SELECT * FROM journal_posts
WHERE status = ? AND type = ? AND activity_end_at >= ?
ORDER BY activity_start_at ASC, id ASC LIMIT ? OFFSET ?
```

- [x] GREEN repository seam, then service RED tests: draft invisible; publish visible; withdraw invisible; republish preserves slug/first date; invalidcalendar/end-before-start rejected; no-hour same-day event ends23:59:59 and flags roundtrip; duplicate title generates distinct slug; notices/reports cannot retain event dates. Use closure `fn() => new DateTimeImmutable('2026-10-30 12:00:00', new DateTimeZone('Africa/Dakar'))` and explicit later clock for expiry boundary tests.
- [x] Implement service using input whitelist and explicit clock/validation above. Only a positive actorId is accepted; permissions are controller responsibility, tested at HTTP seam later. No mass-assignment of author/status/slug/timestamps/cover path. Re-run focused service tests.
- [x] Regression RED/GREEN for deterministic interleaved edit/withdraw and first publication, with two real services sharing a DB through a public repository timing seam. Assert atomic revision rejects stale writes and preserves privacy/first date; verify pre-upload validate performs no insert/update.
- [x] Run full PHPUnit once, PHP syntax and diff check. Create signed `feat: add journal persistence and editorial lifecycle`; verify signature and report RED/GREEN, exact method signatures and checks.

## Task 2: Private management, secure covers and permissions

**Files:** create `src/Service/JournalMediaService.php`, `src/Controller/JournalAdminController.php`, `templates/admin/pages/journal-list.html.twig`, `journal-edit.html.twig`, `journal-preview.html.twig`, `templates/journal/article.html.twig`, `assets/css/journal.css`, optional focused `assets/js/journal-editor.js`, `tests/apps/journal-fixture.php`, initial `tests/apps/journal.py`; modify `src/bootstrap.php`, `docker/apps/routes.php`, `src/Core/StaffNavigation.php`, `src/Controller/BaseController.php`, `templates/partials/sidebar.html.twig`, `staff-header.html.twig`, `templates/admin/pages/add-user.html.twig`, `edit-user.html.twig`, `tests/apps/run.sh` for migration07 and synthetic staff test staging.

Also modify `src/Service/UserService.php` to add journal to its existing allowed module list and extend `tests/test_permissions_unit.php` only where needed for the new valid permission. Preserve role downgrade clearing, unknown permission rejection and existing superadmin behavior. This whitelist is a confirmed integration seam, not an optional UI-only change.

**Consumes:** Task1 exact repository/service. **Produces:** admin methods index/edit/save/preview/cover; staff routes GET manage-journal.php/edit-journal.php/journal-preview.php/journal-preview-cover.php and POST edit-journal.php. Add only edit-journal.php to POST method whitelist. Shared article partial consumes `post`, `cover_url` and no staff booleans/links; journal.css scopes within journal containers using public/admin tokens.

`JournalMediaService(string $projectRoot)` exposes `store(array $upload): array` returning path/mime, `resolve(string $relativePath): ?array` returning absolute path/mime/length, and `discard(string $relativePath): void` only for newly created unreferenced upload cleanup. Store only actual JPEG/PNG/WebP <=5MiB, finfo and image-content checks, random32hex filename derived from detected MIME under uploads/journal/covers/. Resolve canonical path inside that exact directory with separator boundary, reject traversal/symlinks outside and unsupported MIME. No direct public file alias. Normal replace/remove retains old file to avoid destroying referenced content; no user delete operation.

- [x] Implement constructor guard exactly: `requireAdmin(); if (!hasPermission('journal')) { flash error; redirect('dashboard.php'); }`. Every action, including image/private preview, uses it. UI permission label Journal follows existing user-permission mechanism; no automatic grants beyond existing superadmin bypass. Public layout never receives a management link. Sidebar/route labels French.
- [x] Create staff list (type/status/page), editor (all fields, safe error preservation, coveralt/remove/optional datehours) and private preview. Save/publish/withdraw are POST+CSRF only. Verify CSRF before storing uploads; validate input before storage, discard only newly uploaded orphan on failed save. Render validation errors422 without exposing SQL/path details. Service IDs/strings arrays invalid -> safe404/422. Retain first published_at/slug. Preview uses shared article partial without public navigation pointed at wrong-app routes.
- [x] Media served with detected Content-Type, nosniff and private/no-store; no arbitrary requested file path. Draft covers only through private permissioned endpoint; read public endpoints not implemented yet and remain404. Public negatives for all four staff routes GET/POST remain404, no Location or staff-link error.
- [x] Add guarded CLI synthetic fixture (APP_ENV=test, DB_NAME=amea_rehearsal) with three named users: journal-admin(admin+[journal]), journal-denied(admin+[]), journal-reader(user+[journal]). No modifications to existing fixture users or actual demo. HTTP RED then GREEN for denied roles, CSRF, draft persistence/private preview, invalid dates, spoofed MIME/oversize cover and real accepted image preview. Existing endpoint assertions remain.
- [x] Run fresh targeted Docker stage/tests using explicit synthetic DB/migrations00–07; journal fixture/test staging after tools fixture before release rehearsal is acceptable. Add migration07 to runner initial sequence, preserving legacy enum before/after06 proof. No workers/SMTP outside existing synthetic safety. Compile touched Twig, PHP/JS syntax and relevant tests; sign `feat: manage journal privately with guarded cover uploads` and report.

## Task 3: Public journal, temporal archives and homepage integration

**Files:** create `src/Controller/JournalPublicController.php`, `templates/journal/index.html.twig`, `show.html.twig`; modify shared `journal.css`/article partial only as needed, `src/bootstrap.php`, `docker/apps/routes.php`, `src/Controller/HomeController.php`, `templates/layouts/public.html.twig`, `templates/home/index.html.twig`, `tests/apps/journal.py` and `tests/http` fixtures only if needed (global makeTestDb already has empty journal schema).

Also allow `templates/admin/pages/journal-edit.html.twig` to show the stable public article URL inside the private editor, using configured public_home_url and post.slug. Label drafts as unavailable publicly until published; this helps the publisher and lets HTTP coverage exercise the actual known draft slug. This is staff→public only; no management control added publicly.

**Consumes:** Task1 published-only queries and Task2 shared article/MediaService. **Produces:** public GET journal.php, journal-article.php?slug=..., journal-cover.php?slug=...; controller methods index/show/cover. All are GET-only and absent on staff. No staff methods/routes added.

- [x] HTTP assertions executed: drafts and guessed IDs/slugs/covers absent404, published content readable and escaped, proper3type filters + temporal archives, up to3 recent homepage items and stable article URLs. Use actual staff HTTP save/publish to create data; do not assert through DB side channels. Keep test clock boundaries in Task1; HTTP uses relative dates derived from server fixture clock. A strict pre-implementation RED run was not recorded for these Task3 cases; only the later anonymous-session defect has a reproduced RED/GREEN result.
- [x] Implement index pagination/filter whitelist and useful empty state; publication page uses shared safe partial. Author label AEESGS only. Cover endpoint resolves only a currently PUBLISHED record then MediaService canonical path; Cache-Control:no-store, nosniff. Missing/draft/withdrawn/contentless cover ->404, never staff redirect. Reject array/malformed slug safely.
- [x] Integrate public Journal navigation + homepage max3 current published summaries only when rows exist. Real public links, no simulation. Preserve carrousel, inscription/correction/consent. No edit/login/management controls even when receiving staff cookies or copied session IDs. Archives derive end_at relative to Africa/Dakar read clock; ongoing labels retain event page until end. No auto REPORT insertion.
- [x] GET/POST negatives against new staff routes on public and public routes on staff; wrong-app404 before dispatch. Public list/article/error text never reveals staff host/preview URL. Published withdrawal removes text/image from public, preserved URL usable again after authorized republish.
- [x] Run focused journal HTTP and historical baseline/token suites; full PHPUnit once before task commit, syntax/Twig/diff checks. Sign `feat: publish association journal and automatic event archives`; report exact scope/tests.

## Task 4: Integrated regression, real local runtime and delivery evidence

**Files:** journal HTTP fixture/tests/runner, `docs/testing/association-journal.md`, `.scratch/association-journal/completion.md`, root completion record, plan checkboxes and task evidence. Local runtime/credential files stay untracked. Workflow files change only if necessary; if changed, consolidate into ci.yml per AGENTS.

Integration completion scope: add `templates/journal/card.html.twig` used by journal index and homepage, and extend article partial/journal.css to show the approved publication date/type and optional cover on actual public listings. The first Task3 implementation omitted these approved presentation details, while detail images worked; avoid copying those omissions from the tests. Card consumes post and optional now; renders escaped title/summary, AEESGS signature, first publication date, event dates with optional-time flags and current cover endpoint (no staff links). Preserve privacy by using only published records already supplied by repository/controllers.

- [x] Run full PHP CI command set + historical HTTP, and fresh `AMEA_SKIP_BROWSER=1 AMEA_PUBLIC_PORT=18445 AMEA_STAFF_PORT=19445 AMEA_REHEARSAL_PROJECT=amea-ui-test-journal-20261030 bash tests/apps/run.sh`, preserving runtime/draft/cover/date/security checks and rollback/restore tests. Existing pre-journal rollback image ignores additive table; forward restores journal. Check migration07 idempotence without erasing content.
- [x] Test real cover request before publish/after publish/after withdrawal; separate-session ordinary user/unauthenticated/denied admin; canonical traversal and forged path cannot serve students/. No public auth bridges and no new public alias for uploads/journal.
- [x] Update local demo only after explicit synthetic DB identity verification: build new images, run migration07 manually via one-off image command on project amea-local-20261027/DBamea_rehearsal BEFORE replacing web containers. Preserve DB and uploads volumes; no fixture seeding/reset there. Then replace public/staff using own config referring to existing shared demo volumes and validate routes/assets. Keep prototype18444 clearly distinct; actual journal is https://localhost:18443/journal.php.
- [ ] Real browser QA: public list/filter/article, private create/edit/publish/withdraw, no access links, mobile/light/dark/keyboard and image behavior. Local certificate interstitial must be handled by the user; never bypass it. If blocked, preserve concrete gap, finish unaffected code/tests/reviews and keep PRdraft.
- [x] Whole-branch independent Standards/Spec/security review, one scoped fix wave/re-review. Sole P3 corrected in signed7dc5faa and scoped rereview PASS.
- [x] Sign/verify documentary checkpoint bb2745e, publish with explicit authorization and attach PR18 targeting dev. All three checks passed on that exact head; PR17 is now merged as23b5c55.
- [x] Verify fresh CI on signed conflict-repair head `69a3773`; PR18 merged. Follow-up PR20 also merged with three green checks on signed `09a414c` and green merged-dev CI. No remote main push or production action.
- [x] Archive useful proof and safely clean owned delivery worktrees; retain unrelated demo mounted files. Local-main integration was explicitly approved and signed; prior demo data/branch are preserved.

Status clarification (31 October): source, reviews and delivery are complete through merged PR18/20 and exact-head/merged-dev CI. PHP 159/310, standalone48/186/78/24, historical HTTP35+28 and final535PASS rehearsal are recorded in `.scratch/association-journal/completion.md`. Browser QA is partial and explicitly deferred to the user, who will perform it later; keep that checkbox open. Tool activation did not make native keyboard actions verifiable, and no Playwright run occurred. No production action or remote main push.
