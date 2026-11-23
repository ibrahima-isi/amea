# AEESGS back-office redesign

The existing Twig staff routes now use the supplied login and dashboard prototype. The dashboard reads the existing aggregates and review queue, and its shortcuts use the same permissions as the sidebar.

## Local verification

- Real Chrome and the disposable split-app HTTPS stack verified both themes at 390, 768, 1024 and 1440 px. Checks covered horizontal overflow, a long staff name, the responsive grids, live chart colors, keyboard menu access and focus restoration.
- The same stack verified login, rejected credentials and CSRF, staff return routes, password recovery, logout, session separation, review queue permissions and the shared staff modules.
- PHPUnit on PHP 8.4 covered 373 tests, with 950 assertions, 41 skips and 10 PHPUnit deprecations. The two dashboard template regressions cover empty charts and escaped institution labels.
- The standalone PHP, theme, HTTP, storage and Doctrine Compose checks passed. The initial PHP 8.6 run encountered session-fixture failures. The PHP 8.4 rerun passed.
- Local screenshots are in `/private/tmp/amea-backoffice-screenshots`. The login was compared with the provided capture and the dashboard with the local prototype. Figma returned HTTP 403. The dashboard uses Chart.js, includes graduates in the status totals, shows the real staff actions and gives the chart data a text alternative.

## Delivery

Local branch `feature/aeesgs-backoffice-redesign` from `origin/main` at `86e672f`. Publication, PR, merge and production deployment await separate authorization.

## Staff landing page follow-up

The supplied landing reference now drives the staff entry page. It uses the existing AEESGS logo, the original public-home and registration destinations, the real login form and the shared theme preference. The laptop illustration uses existing labels. The recovery views continue to use their shared layout.

Chrome checks cover both themes at 390, 768, 1024 and 1440 px, the original menu and footer destinations, and keyboard focus on the username field from the header shortcut. The disposable split-app HTTPS suite and the PHP 8.4 regression suite passed. Full landing screenshots are in `/private/tmp/amea-landing-screenshots`.
