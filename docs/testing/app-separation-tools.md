# Ticket 07 — staff operational tools

Verified locally on 2026-10-24, with synthetic fixtures only.

The staff target exposes the existing carousel, communications/count, export/preview, settings, users/create/edit, profile and member progression controllers. Existing templates and permission checks are retained. The public target denies these routes. Ticket 06 owns the shared route/front-controller change (`7f76cf2`).

Run `bash tests/apps/tools-run.sh` for an isolated MySQL 8 + two HTTPS applications on loopback ports 18445/19445. The runner refuses an existing project or its volumes and removes only its newly created resources. `APP_ENV=test` invokes the existing EmailService substitute on direct communications and queued worker sends: no SMTP connection is made. Fixtures never enter production images. Optional `AMEA_SKIP_BROWSER=1` omits Chrome; screenshots default to `/private/tmp/amea-tools-browser`.

Executed **84 HTTP assertions, 9 Chrome assertions and one competing-worker proof**. HTTP scenarios cover anonymous, ordinary user, restricted admin and authorized admin, wrong-app denial, CSRF rejection, a real authorized CSV with synthetic member bytes, safe communication submission, persisted settings/profile edits, account creation/authentication, member progression, and multipart carousel publication whose identical image bytes appear on the public app. Real Chrome opens the eight unchanged staff tools and renders the published image on the public HTTPS host. Captures: [staff carousel](screenshots/tools/staff-carousel.png), [public publication](screenshots/tools/public-staff-publication.png).

## Shared jobs owner

`docker/Dockerfile.apps` adds a jobs target with no web process; web targets omit scripts. The `jobs` Compose profile is disabled by default. Once an approved cutover has stopped the previous scheduler, this single service may own mail each minute, abandoned registration uploads hourly, and trash daily only after verified backup plus `AMEA_TRASH_BACKUP_CONFIRMED=1`. Do not enable this profile alongside the former scheduler. Legacy cron entry points remain intact and usable until that transition.

`run-shared-jobs.php` holds one storage-volume lock across the child process, rejects web image identities, and requires explicit backup confirmation for trash. Existing individual worker locks remain in place. The CLI proof runs two overlapping owners against three synthetic mail queue messages: three deliveries, three removals, counter +3, and no output/work from the competing owner. The real scheduler/purge loop is never run by the rehearsal.

PHPUnit 141 tests / 241 assertions passed (10 existing deprecations); standalone regressions 186/78/47/24 passed; legacy isolated HTTP 35 and token HTTP 28 passed. PHP/shell syntax and diff whitespace checks pass. These are local results; final integrated CI/review and production backup/restore/cutover are separate. No production database, storage, DNS or real email was touched.
