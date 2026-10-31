# Testing and rehearsal

Install locked dependencies with `composer install`. CI runs PHP 8.4 with SQLite:

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
