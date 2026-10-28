# AEESGS Public and Back-office Refresh Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Deliver the approved matte back-office and warm public interface with independent entry points.

**Architecture:** Keep existing PHP controllers, repositories, Twig forms and actions. Introduce focused shared admin visual tokens and chrome, with an operational dashboard using server-side records. Keep public styles independent and retain the already implemented public-to-staff 404 boundary.

**Tech Stack:** PHP 8, PDO, Twig, Bootstrap 5, vanilla JavaScript, PHPUnit 11, Docker/MySQL.

**Spec:** `docs/superpowers/specs/2026-10-28-public-backoffice-visual-refresh-design.md` (approved interactively 2026-10-28).

## Global Constraints

- All edits, tests and commits in `/private/tmp/amea-local-20261027`, branch `feat/public-backoffice-refresh`; original checkout untouched.
- Every commit cryptographically signed; stop on signing failure, never disable signing. Stage explicit task files only, never local-runtime, vendor, generated caches or secrets.
- Preserve enum values, forms/actions/CSRF, permissions, session expiry/revocation, private document endpoints and existing fields.
- Shared database/files temporarily retained. No schema migration, directory reorganization, framework replacement or production change.
- Public unknown staff routes return local 404 without Location or staff links. Never restore the compatibility bridge.
- Clear/dark themes, responsive navigation, keyboard focus, reduced motion and readable table/form contrasts are required.
- Use existing HTTP/browser journeys as the accepted behavior boundary. New repository behavior gets focused SQLite integration tests; no CSS snapshot tests.
- Product CSS must use theme variables and load after legacy page styles so every existing module receives the new appearance. No generic green surfaces or moving table values.

## Task 1: Matte admin shell, navigation and authentication

**Files:** Create `assets/css/admin.css`, `assets/js/admin-shell.js`, `templates/layouts/staff-auth.html.twig`; modify `templates/layouts/admin.html.twig`, `templates/partials/sidebar.html.twig`, `templates/auth/login.html.twig`, `templates/forgot-password.html.twig`, `templates/reset-password.html.twig`, `src/Controller/BaseController.php`; create admin-only header/footer partials if necessary. Do not change public partials or controllers beyond shared view context.

**Interfaces:** Consumers use existing Bootstrap `.card`, `.table`, `.form-control`, `.btn-*` and permission function. Produce `body.admin-app` for authenticated layout and `body.staff-auth` for authentication, and theme variables `--admin-bg`, `--admin-surface`, `--admin-ink`, `--admin-muted`, `--admin-line`, `--admin-navy`. BaseController provides `staff_navigation` with explicit allowed module booleans and `staff_route` from parsed request path; no public-to-staff URL global.

- [x] Read existing layout/sidebar/auth templates and page style includes. Preserve every form field/action/CSRF and password recovery copy.
- [x] Implement shared tokens: light navy `#17263C`, background `#F3F5F8`, white surfaces, text `#293447`; dark neutral navy/charcoal surfaces with contrasting text. `body.admin-app .card-header` is neutral, primary actions navy; semantic warning/danger/success states remain distinct. Load the new sheet after page-specific styles. Style all existing cards/forms/tables, including secondary modules, without changing their business logic.
- [x] Add dedicated admin top bar with mobile menu button, local breadcrumb/current page, theme control, profile and logout. Keep footer concise and free of the old green navbar/footer. Sidebar includes every existing module permitted to the current staff user, matching controller authorization (KYC: admin; other modules use existing role/permission rules). Use real logo where present.
- [x] Implement accessible mobile drawer: button `aria-controls`/`aria-expanded`, Escape and overlay close, focus returns to menu button, no focusable hidden links; preserve desktop navigation. Use a single shell script, no document-wide catch-all handlers.
- [x] Put login/recovery/reset on the split navy/light staff-auth layout; mobile stacks; retain login title containing `Connexion Administration` for existing journeys. Support theme switching and preserve authenticated redirect behavior.
- [x] Check Twig compilation via `Amea\Core\View::getTwig()->load($template)` for touched templates, PHP syntax and JS syntax; run `./vendor/bin/phpunit --colors=never` once before commit. Pure visual edits do not need new PHPUnit assertions.
- [x] Self-review diff, create signed commit `feat: refresh admin navigation and authentication`, write task report with checks and any browser limitation.

## Task 2: Server-backed review queue and side-by-side dossier

**Files:** Modify `src/Repository/StudentRepository.php`, `src/Controller/DashboardController.php`, `templates/admin/pages/dashboard.html.twig`, `templates/admin/pages/kyc-list.html.twig`, `templates/admin/pages/kyc-detail.html.twig`, `templates/admin/pages/student-details.html.twig`; add focused tests to `tests/Integration/Repository/StudentRepositoryTest.php`; extend admin.css only for these components.

Implementation adjustment: also update `templates/partials/sidebar.html.twig` and `templates/partials/staff-header.html.twig` for the approved Adhérents wording and conditional groups, retaining every existing permission and route.

**Interfaces:** Add `StudentRepository::findReviewQueue(int $limit = 6): array` returning `Student[]` for active `UNDER_REVIEW`, sorted by registration_date then id ascending with bounded limit; counts use existing `countAll(['kyc_status' => ...])`. Dashboard context adds `review_queue`, `review_count`, `clarification_count`, `can_review` (admin role). Existing statistics/charts remain below the queue and do not expose new dossier data to non-admin users.

- [x] RED: create student fixtures with UNDER_REVIEW, NEEDS_CLARIFICATION, APPROVED, REJECTED, PENDING_CONFIRMATION and soft-deleted rows; assert queue includes only active review records, oldest first with deterministic ties and limit. Run focused PHPUnit filter and record missing-method failure.
- [x] GREEN: prepared SQL `SELECT * FROM students WHERE deleted_at IS NULL AND kyc_status = ? ORDER BY registration_date ASC, id ASC LIMIT ?`; bind `UNDER_REVIEW` and a limit clamped to 1..50. Map rows through `Student::fromRow`. Re-run focused test; add a bounds test if needed.
- [x] Query review details/counts only for admin in DashboardController; staff without review authorization keeps allowed overview/statistics. Display real names/statuses/dates with escaping and direct `kyc-detail.php?id=...` links. Clarification count is labelled waiting for member response, never mixed into immediate review work. Show useful empty state.
- [x] Retain existing dashboard statistics/charts below operational work; update chart colors/labels for navy theme and dark/light legibility. Preserve all stat meanings.
- [x] Reshape KYC detail so personal/academic data and authorized document preview are side by side on desktop, stacked on mobile; keep approve/clarify/reject and required note rules. Preserve all student detail/edit information and use download.php for documents, including KYC list thumbnails. PDF previews must remain usable as PDF, not broken image placeholders.
- [x] Run repository tests, full PHPUnit and syntax checks. Existing Docker member/role journeys will validate integration in Task 4. Create signed commit `feat: prioritize staff review dossiers`; report RED/GREEN, files and checks.

## Task 3: Warm public design across member journeys

**Files:** Modify `assets/css/public.css`, `assets/css/public-forms.css`, `assets/css/public-consent.css`, `templates/home/index.html.twig`, `templates/layouts/public.html.twig`; keep `assets/js/community-carousel.js` behavior and registration/edit field rules intact.

**Interfaces:** Retain existing public CSS variable names and carousel markup/data hooks. Use background `#FFFAF3`, surface `#FFFCF7`, soft section `#F4E4CC`, text `#302C2A`, muted `#70635B`, red primary `#A83434`; map existing primary-action variable compatibly so all public forms follow the same palette. Dark uses neutral warm charcoal, never a green background.

- [x] Read current public/form/consent styles and home/layout templates. Preserve approved logo, real carousel content, pause/manual controls and empty/single-image behavior.
- [x] Apply warm opaque tokens to homepage and every member workflow; use Manrope with system fallback, no layout shift if unavailable. Hero copy may use approved warm proposal but retain accurate registration purpose and existing association content.
- [x] Add public-only contact anchor/navigation if current contact content exists; retain association/registration/legal links and remove no required member links. Never add administration/login/dashboard links or a staff-host configuration dependency.
- [x] Preserve both themes, focus styles, error contrast, reduced-motion support and 320px layouts; avoid oversized headings/sections and keep real community photographs prominent.
- [x] Compile Twig, check whitespace; run `python3 tests/http/baseline.py` and `python3 tests/http/tokens.py` using isolated fixtures. No CSS snapshot or mirrored implementation tests. Create signed commit `feat: warm public pages and member journeys`; report validation.

## Task 4: Integrated qualification and delivery

**Files:** Extend `tests/apps/links.py` or add `tests/apps/visual-refresh.py` and invoke from `tests/apps/run.sh`; update `completion.md`, `.scratch/ui-visual-refresh/completion.md`, design approval status, plan checkboxes and testing evidence. Keep runtime scratch artifacts untracked.

- [x] Run all four standalone PHP scripts plus PHPUnit and historical HTTP suites. Add HTTP behavior checks for operational queue visibility/empty state and admin/non-admin navigation using existing synthetic clients; retain the no-public-staff-link matrix.
- [x] Run fresh `AMEA_SKIP_BROWSER=1 AMEA_PUBLIC_PORT=18445 AMEA_STAFF_PORT=19445 AMEA_REHEARSAL_PROJECT=amea-ui-test-refresh-20261028 bash tests/apps/run.sh` and confirm owned project cleanup. Do not alter demonstration data to run fixtures.
- [x] Rebuild local demonstration public/staff with current task code and original volumes. Verify HTTP login, public entry, registration and staff route 404 matrix.
- [ ] Perform browser QA via the supported browser tool, including desktop/mobile, light/dark, sidebar keyboard behavior, forms and dossier. If the browser is disabled, preserve that concrete blocker and do not claim visual qualification or mark delivery complete; complete unaffected code/tests/review.
- [ ] Independent whole-branch spec/quality review; fix confirmed findings with focused re-verification. Do not broaden unrelated scope.
- [ ] Record exact test outcomes, source commits, screenshots when available and external blockers; create signed evidence commit. PR must target dev; only latest-head passing required checks allow auto-merge. No production change. Integrate into local main only after actual remote merge, preserving the original checkout and the active demo's worktree mounts.
