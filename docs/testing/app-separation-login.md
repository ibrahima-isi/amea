# Ticket 05 — isolated application and identity rehearsal

Verified locally on 2026-10-24. This is an incomplete staff target, not a production cutover.

## Application boundary

`docker/Dockerfile.apps` has separately buildable `public` and `staff` targets. The sole physical document-root PHP file is the fixed-identity front controller; shared application code remains outside that root. Explicit route and method allowlists run before dispatch/legacy fallback. Only selected static assets and approved slider images are exposed; private student uploads and application internals are unavailable.

The original Dockerfile/deployment is preserved. Staff authentication currently ends on a temporary rehearsal landing: member administration and tools are tickets 06/07. Do not deploy this partial staff target over the existing administration.

## Executed evidence

- `bash tests/apps/run.sh`: **76 HTTP/schema checks and 8 actual Chrome checks passed**, using two local HTTPS hosts, PHP 8.4 Apache images, and an isolated MySQL 8 database.
- Initialization uses unchanged migrations 00–05. French registration fails on the legacy enum schema, then succeeds after migration 06. Repeating 06 is harmless. English fixture records, an existing default, collation and comment survive expansion. `schema.sql` also includes compatible enum values. No applied migration was edited.
- Actual HTTPS/browser cookies are distinct, Secure, HttpOnly, SameSite Strict and host-only; sessions use distinct volumes. Reusing public/staff session IDs under the other cookie name does not transfer authentication or staged-file ownership.
- Staff login/CSRF, valid/expired/invalid/single-use password-reset links, password changes, session revocation and 30-minute inactivity are exercised. Public registration includes CSRF rejection, owning/foreign staged previews, review and confirmation.
- A final HTTP tracer reproduced public access to an extensionless slider file. The slider directory now denies by default, granting only raster filenames without executable compound extensions; the tracer then passed.
- Wrong-app PHP routes, private paths, internals, unsupported methods and executable upload names are denied. Existing approved asset filenames containing spaces remain available.
- Legacy single-app HTTP checks: 35 passed; token journeys: 28 passed. PHPUnit: 141 tests / 241 assertions; 10 existing deprecations. Four standalone regressions: 47/186/78/24 passed. PHP/shell syntax checks pass.

## Reproduction and safety

Run `bash tests/apps/run.sh` with Docker, Python 3, Node 24 and Chrome. Set `CHROME_BIN` on non-macOS systems. `AMEA_SKIP_BROWSER=1` runs the HTTP/schema portion used in CI. Optional `AMEA_PUBLIC_PORT`/`AMEA_STAFF_PORT` override loopback ports; screenshot output defaults to an OS temporary directory.

The runner requires a fresh `amea-ui-test-*` project, refuses existing containers or volumes for that project, creates only synthetic credentials/data, binds TLS to loopback, and cleans only its own project on exit. PHP uses `APP_ENV=test`, disabling email delivery. Certificate-verification bypass is confined to the synthetic client/browser profile; no system DNS or trust configuration is changed.

## Limits and next work

No production database, DNS, SMTP, real storage or external backup/restore was touched. Container replacement/persistence, rollback, full route compatibility, shared worker ownership and completed staff tools remain tickets 06–09. Independent review of ticket 05 remains pending. The root agent completed the initial local checkpoint; the implementer resumed when capacity became available and finalized it. Exact-head CI is separate evidence.
