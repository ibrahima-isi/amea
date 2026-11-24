# 02: Validate the two-site Stellar installation

Status: ready-for-agent

Blocked by: [01](01-qualify-stellar-account.md).

## What to build

Run the public website and Administration on the qualified account from one private release, using an isolated MariaDB database and the private R2 test bucket.

## Acceptance criteria

- [x] Both test domains serve the same reviewed release through separate document roots, with approved static assets, distinct sessions and private code and configuration.
- [x] Verify registration, staff login, authorized private downloads, published media and a local 404 for staff routes on the public host.
- [x] Verify authenticated write and read-back of a synthetic R2 object, a test email and successful use of the intended CLI configuration for Doctrine and scheduled jobs.
- [x] Verify shared private write-gate and jobs state across release changes. Record actual target evidence for the public, staff and jobs runtimes.
- [ ] Exercise the approved publication path on staging with isolated settings, including refusal to promote after a failed migration and recovery to a compatible release. Use synthetic data and retain Compose support.

## Comments

- 2026-11-24: Added modular Stellar publication scripts (`scripts/stellar/render-runtime-env.php`, `scripts/stellar/publish.sh`, `scripts/stellar/verify-http.php`), integrated `deploy-stellar-staging` (`workflow_dispatch`, `environment: stellar-staging`) and `deploy-stellar` (`push` to `main`, `environment: production`) into `.github/workflows/ci.yml` alongside `tests/http/stellar-split.py` and `bash -n` checks, and verified Compose configs remain intact.
- 2026-11-24: Exercised the exact publication script chain live against Stellar staging (`demo.aeesgs.org` / `bo.aeesgs.org`, `~/amea-staging`):
  - Promoted `staging-pub-20261124090756` (SHA-256 `4666207cb6f2931f6dcae844aa7bb1a8a7792c39bc4ac4fa2891f3ad786a877b`) via `render-runtime-env.php` → `package.php` → `publish.sh --mode=deploy` → `verify-http.php` (9/9 live HTTPS checks passed, including unauthenticated `401` and local `404` without `Location` on public staff paths).
  - Refused promotion of `staging-badmig-20261124090848` (synthetic failing Doctrine migration `Version20991231235959` against live MariaDB `aeesjwmz_amea_staging`, SQLSTATE `42S02`), preserving `current=staging-pub-20261124090756`, cleaning up both the failed release dir and remote `tmp-deploy-*`, and keeping 9/9 live HTTPS checks green.
  - Recovered via `publish.sh --mode=rollback` to previous compatible release `staging-5bb4284` (9/9 live HTTPS checks passed), then restored `staging-pub-20261124090756` (9/9 live HTTPS checks + `bin/run-job.php mail` and `pending` passed).
- Remaining gate before checking Criterion 5 and Ticket 02 complete: push branch, configure `stellar-staging` GitHub Environment secrets + repository variable `STELLAR_STAGING_DEPLOY_ENABLED=true`, and trigger `deploy-stellar-staging` once via GitHub Actions `workflow_dispatch`.
