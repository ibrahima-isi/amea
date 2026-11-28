# 05: Integrated qualification, split-host checks & tracker sync

**What to build:** Perform end-to-end qualification of both the fast Member Communications module (`/communications.php`) and the new Consent & RGPD module (`/consent.php`) across the full repository verification suite. Update split-host HTTP and permission regression suites to cover all new routes, permissions, CSRF checks, and public-host `404` boundaries; verify Doctrine fresh-database creation and migration parity; verify light-by-default and dark-mode (`[data-bs-theme="dark"]`) styling, keyboard accessibility, and responsive layout on both Administration pages; ensure all PHPUnit and standalone CI scripts pass cleanly; and sync repository documentation and local tracker records.

**Blocked by:** 02 — Communication history, self-test preview & Resume / Retry / Cancel controls; 03 — CLI mail worker fallback & unified daily quota; 04 — Dedicated Consent & RGPD module (/consent.php) & isolated cgu_token lifecycle

**Status:** ready-for-agent

- [x] Permission unit and integration test suites cover `'consent'` alongside existing modules and verify that `'communications'` and `'consent'` remain independently gated.
- [x] Split-host HTTP operational tools checks verify public-host local `404` (no redirect) for all new communications and consent routes, anonymous/unauthorized denial on the staff host, CSRF rejection on mutating endpoints, safe `APP_ENV=test` communications submission, and authorized `/consent.php` access.
- [x] Doctrine fresh-database test and schema verification pass cleanly with all new migrations aligned to `schema.sql`.
- [x] Both `/communications.php` and `/consent.php` render cleanly in light mode (default) and `[data-bs-theme="dark"]` with proper contrast, keyboard navigation, and mobile responsiveness.
- [x] Full CI verification set (`./vendor/bin/phpunit`, standalone regression scripts, and PHP syntax checks on all changed PHP files) passes with zero failures.
- [x] Project documentation (`docs/email-queue.md`, `GLOSSARY.md`, `docs/adr/0005-hybrid-bulk-communication-delivery.md`, and `.scratch/README.md`) accurately matches the implemented repository state.
