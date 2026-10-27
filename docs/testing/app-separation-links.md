# Ticket 08 — compatible links and complete route boundary

Verified locally on 2026-10-24 against freshly built public/staff PHP 8.4 images, isolated MySQL 8 and two local HTTPS hosts. No production/DNS/SMTP action occurred.

## Behavior

- Exact legacy staff GET pages redirect from public to their matching route under configured `STAFF_APP_URL`. Known query fields (member id, filters, reset token) survive; irrelevant tracking keys are discarded. Destinations never derive from request Host. Missing/invalid staff origin fails closed instead of redirecting to the public host.
- Old staff POST submissions return French HTTP409 recovery guidance. No body, credentials or CSRF token is forwarded, reflected or replayed; a valid synthetic deletion form left its record unchanged through staff HTTP verification.
- Staff authentication remembers only exact allowed local GET destinations with vetted scalar query fields. External/authority URLs, backslashes, controls, encoded aliases, fragments, unknown routes/keys, arrays, auth/logout routes and POST-only destinations are rejected. Return state is consumed once. Expired and revoked sessions also preserve a safe bookmarked GET through fresh login.
- Explicit methods refuse unsupported requests before Router legacy fallback; `confirm-upgrade.php` is POST-only. Existing public member URLs/tokens and staff authorization/CSRF remain unchanged. Direct private files, internals, unknown PHP/PATH_INFO and encoded path attacks are unavailable.
- Public navigation points to staff sign-in. Staff shared header/footer/login point to the configured public homepage/registration. KYC uses `PUBLIC_APP_URL` (ticket06); recovery emails use `STAFF_APP_URL`, retaining legacy APP_URL fallback. Configure both canonical HTTPS origins for split targets.

## Executed evidence

TDD tracers failed for legacy GET redirect, desired login destination, staff navigation and expired-session bookmark preservation before their respective fixes. The same real HTTP journeys then passed.

- `tests/apps/run.sh`: **249 HTTP/schema assertions and 13 actual Chrome assertions passed** on the final application tree, including complete explicit staff/member/AJAX/download/recovery/method/encoded-path matrix, safe stale form and login-return browser journeys.
- `tests/apps/tools-run.sh`: **84 HTTP assertions and one concurrent job-owner proof passed** after the session follow-up; the preceding full tools run also passed9 Chrome checks. The final ticket09 candidate will run all browser suites again.
- PHPUnit:141 tests/241 assertions,10 existing deprecations. Standalone permission/OOP/password scripts:47/186/78/24 passed. Legacy isolated HTTP35 and tokenHTTP28 passed after the final session fix.
- Screenshots inspected: [safe stale form](screenshots/legacy-staff-recovery.png), [validated login return](screenshots/staff-login-return.png).

Reproduce using the existing fresh-project harness: `AMEA_PUBLIC_PORT=18446 AMEA_STAFF_PORT=19446 bash tests/apps/run.sh`. Docker Desktop anonymous task configuration was used only to avoid its stalled credential helper; no user Docker configuration, DNS or system trust was modified. All synthetic resources cleaned on exit.

## Remaining qualification

Ticket09 still owns independent replacement/persistence, backup/restore, rollback to a real prior image, full final browser/regression checks and operational runbook. Final independent code review and exact-head CI are separate milestones.
