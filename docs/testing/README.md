# Testing and rehearsal

Install locked dependencies with `composer install`. CI runs PHP 8.5 with SQLite:

```sh
./vendor/bin/phpunit --colors=never
php tests/test_permissions_unit.php
php tests/test_oop_unit.php
php tests/test_oop_integration.php
php tests/test_password_reset_security.php
python3 tests/http/baseline.py
python3 tests/http/tokens.py
```

The HTTP fixtures use disposable synthetic data and loopback; see [HTTP harness](../../tests/http/README.md) for isolation and limits. Optional fresh-profile Chrome checks require Node 22+ and Chrome (`CHROME_BIN` can override its location):

```sh
AMEA_BROWSER_CHECK=1 AMEA_REGISTRATION_BROWSER=1 python3 tests/http/baseline.py
AMEA_BROWSER_CHECK=1 python3 tests/http/tokens.py
```

For the synthetic two-host HTTPS/MySQL, jobs, Twig cache isolation, replacement, restore and rollback rehearsal, use Docker Compose and available ports; the harness owns and cleans its fresh project and restore project:

```sh
AMEA_REHEARSAL_PROJECT=amea-ui-test-example AMEA_PUBLIC_PORT=28443 AMEA_STAFF_PORT=29443 AMEA_SCREENSHOT_DIR=/tmp/amea-rehearsal-screenshots bash tests/apps/run.sh
```

Check source ports 28443/29443 and restore ports 18448/19448 are free first. The rehearsal uses synthetic accounts, storage and mail; it does not qualify production, DNS or real SMTP. The [rollout guide](../operations/public-staff-rollout.md) describes deployment and rollback separately. Screenshots are generated artifacts and ignored.

## Local Docker stack

For the Doctrine cutover, run `bash tests/apps/doctrine-compose.sh`. It uses both shipped Compose files with disposable project volumes and no host ports or Dokploy network. It checks MariaDB 10.11 fresh initialization, zero-change retries, enrollment, shared enrollment/migration locking, transactional insert rollback, recovery, and failed-migration gates for public, staff and jobs. It removes only its own projects. The existing MySQL rehearsal above retains historical migration and application coverage.

`compose.yml` mirrors `compose.dokploy.yml` locally (PHP 8.5.12 targets, MariaDB 10.11, the `migrate` step) and adds Caddy for HTTPS, which the `Secure` session cookies and the `https`-only origin checks need:

```sh
docker compose up -d --build --remove-orphans
docker compose logs migrate   # migration output
```

Open `https://public.localhost:8000` for `web` and `https://staff.localhost:8090` for `backoffice`. If your local `.env` sets `PUBLIC_APP_URL` or `STAFF_APP_URL`, update those values to match. Caddy signs both with its own local CA; to stop the browser warning, trust it once on macOS:

```sh
docker compose cp caddy:/data/caddy/pki/authorities/local/root.crt ./caddy-root.crt
sudo security add-trusted-cert -d -r trustRoot -k /Library/Keychains/System.keychain ./caddy-root.crt
rm ./caddy-root.crt
```

`APP_ENV` defaults to `local`, which skips real email delivery. MariaDB listens on `127.0.0.1:3306`.

The one-shot service initializes an empty database and applies pending Doctrine versions. Before starting against an existing volume, stop writers and complete [verified enrollment](../operations/doctrine-enrollment.md). Use `docker compose run --rm migrate php scripts/migrate.php status` to inspect its ledger. A migration failure blocks new application containers; it does not stop already running writers. Follow [recovery](../operations/migration-recovery.md) before retrying interrupted schema changes.
