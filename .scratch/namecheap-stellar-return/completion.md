# Stellar return progress

- Plan accepted by the operator. The plan keeps the public website and Administration separate, preserves the Compose files and selects GitHub Actions on main for production publication.
- Prepared a CLI account preflight ([stellar-preflight.php](stellar-preflight.php)). PHP syntax and local execution passed, including anonymous R2 TLS and Brevo TCP connectivity.
- Implemented native split runtime path selection and packaging while retaining Docker defaults and all Compose files:
  - Shared canonical route allowlist in `config/routes.php` (reused by `docker/apps/routes.php`) and shared split web dispatcher in `src/Core/WebEntry.php` (reused by `docker/apps/index.php`).
  - Added `src/Core/Runtime.php` for external private configuration (`AMEA_CONFIG_FILE`, enforced `0600` non-symlink) and private shared state (`AMEA_STATE_DIR`) with isolated per-entry session directories (`sessions/public`, `sessions/staff`), separate per-entry Twig caches (`cache/twig/public`, `cache/twig/staff`), and shared durable write gate, mail queue, logs, and jobs lock.
  - Added validated `DB_PORT` support in `src/Config/Database.php` and updated `src/bootstrap.php`, `config/database.php`, `src/Core/Session.php`, `src/Core/View.php`, `src/Core/FileWriteGate.php`, `src/Core/Container.php`, `src/Service/EmailService.php`, `functions/utility-functions.php`, and CLI job scripts (`scripts/run-shared-jobs.php`, `scripts/process-mail-queue.php`, `scripts/purge-pending-uploads.php`, `scripts/purge-trashed-students.php`) to use `Runtime`.
  - Extended `scripts/storage-target.php` via `src/Storage/Migration/TargetAttestation.php` to attest native split `public`, `staff`, and `jobs` targets (including packaged document-root wiring verification) while keeping Docker defaults intact.
- Implemented native packaging, atomic release promotion/rollback, and the serialized `main` deployment job in `.github/workflows/ci.yml`:
  - `scripts/stellar/package.php` builds secret-free release archives excluding `.env*`, VCS, tests, Dockerfiles, Compose files, and mutable runtime directories.
  - `scripts/stellar/render-entry.php` and `scripts/stellar/htaccess.template` generate thin, secret-free `public`/`staff` document roots with fixed identities, LiteSpeed/Apache static asset allowlisting, HTTPS enforcement, and AutoSSL `.well-known/acme-challenge/` preservation.
  - `scripts/stellar/verify-release.php` validates PHP 8.5 platform requirements, `0600` private config loading in isolated `public`, `staff`, and `jobs` probes, docroot hygiene, route boundaries, cutover pause state, and blocks `amea-sandbox` in production.
  - `scripts/stellar/deploy.sh` stages private runtime config atomically, runs `verify-release.php` and guarded Doctrine migrations (`scripts/migrate.php migrate`) before touching live config or document roots, blocks deploys during cutover pause, installs `bin/run-job.php`, retains the previous compatible release, and supports `--rollback`.
  - Added `deploy-stellar` to `.github/workflows/ci.yml` gated on tested pushes to `main` and `vars.STELLAR_DEPLOY_ENABLED == 'true'` with non-cancelling `main` concurrency, pinned SSH host verification, separate secret transfer, and live split route smoke checks.
- Local validation passed:
  - PHP syntax lint (`src/`, `config/`, `scripts/`, `tests/storage/`, `registration-preview.php`) and `bash -n scripts/stellar/deploy.sh`.
  - Standalone suites: `tests/js/theme.test.mjs` (5/5), `tests/test_permissions_unit.php` (48/48), `tests/test_oop_unit.php`, `tests/test_oop_integration.php`, `tests/test_password_reset_security.php` (26/26).
  - PHPUnit (`tests/Unit/Core/RuntimeTest.php`, `tests/Integration/Storage/NativeTargetAttestationTest.php`, `tests/Integration/Storage/`, `tests/Integration/Controller/MediaStorageTest.php`, `tests/Integration/Controller/MemberStorageTest.php`, and full suite).
  - Isolated HTTP suites: `baseline.py`, `tokens.py`, `internals.py`, `write-gate.py`, and `tests/http/stellar-split.py` (packaging, deploy, sandbox-bucket refusal, cutover-pause refusal, rollback, `bin/run-job.php`, and real split `public`/`staff` HTTP isolation).

## 01: Assigned account qualification

Account qualification completed on November 22, 2026. SSH authenticated to the Namecheap cPanel account whose primary domain is `aeesgs.org`. Checks ran against that account. The operator selected `demo.aeesgs.org` for the public test site and `bo.aeesgs.org` for the Administration test site.

| Check | Result |
| --- | --- |
| SSH | Passed over the configured cPanel alias on port 21098. |
| CLI PHP | Default `/usr/local/bin/php` is PHP 8.2.36. Scheduled execution verified with `/opt/alt/php85/usr/bin/php` (PHP 8.5.13). |
| CLI extensions | The full PHP 8.5 preflight passed from SSH with `PHP_INI_SCAN_DIR=/opt/alt/php85/link/conf:/etc/cl.php.d/alt-php85`. This lets the CLI load the PHP Selector profile, including `fileinfo`, `gd`, and `mbstring`. The configured five-minute cron entry ran the full preflight from a private directory with the same scan path. All 12 checks passed and the probe was removed. |
| CLI files and process | `flock` and `proc_open` are available. Creating, locking, writing, renaming, linking, and reading back synthetic content passed in a temporary private directory. PHP reported a writable CLI temporary path. |
| Database | MariaDB 11.4.13 loaded the AMEA schema into a disposable database. A transaction-consistent `mariadb-dump` restored into a second disposable database. The table count, two foreign keys and a synthetic record with accented UTF-8 text and a relative document key matched. The databases, scoped user and temporary files were removed. |
| Account quota | cPanel reports a 20 GiB disk quota with 20,477.25 MiB remaining and an inode limit of 300,000. Both LiteSpeed probes reported `upload_max_filesize=1024M`, `post_max_size=1024M`, and a writable upload temporary directory. A 10 MiB synthetic multipart upload passed via Cloudflare HTTPS on both hosts and was processed in PHP temporary storage. |
| Document roots | Created and verified separate, writable cPanel document roots at `~/demo.aeesgs.org` and `~/bo.aeesgs.org`. Both responded over HTTP when addressed directly at Stellar with their own hostnames. |
| Web PHP | The PHP Selector lists the required extensions for PHP 8.5. The per-site LiteSpeed handler `application/x-httpd-alt-php85___lsphp` runs PHP 8.5.13 with all eight required extensions on both test hosts, confirmed over HTTPS. The account default is PHP 8.2. The current deploy script rewrites `.htaccess`, so release promotion must preserve the working handler and recheck the web runtime. |
| Public DNS and TLS | Both test names resolve through Cloudflare. HTTPS with normal certificate validation reached a temporary PHP probe in each Stellar document root. Stellar presents a Cloudflare Origin CA certificate covering both names. |
| Cloudflare R2 | The CLI preflight passed anonymous HTTPS and TLS connectivity from Stellar to the S3 endpoint used by `amea-sandbox`. Authenticated bucket access belongs to staging. |
| Brevo | Outbound TCP to `smtp-relay.brevo.com:587` passed. Test email delivery belongs to staging. |

Ticket 01 is complete. For staging, preserve the per-site PHP handler during release promotion and recheck PHP 8.5 on both hosts. The complete application, authenticated R2 operations, actual job entry point, and test email still need staging verification.

## 02: Private staging preparation

- The `feature/stellar-staging` worktree starts from `origin/main` at `86e672f`. It ports the native release tooling to the current `Amea\Core\Router` and command classes. The original signed qualification record is on `docs/namecheap-stellar-return` at `2dc7642`.
- The deployment renders the qualified LiteSpeed PHP handler and can carry cPanel staging authentication across promotion and rollback. The installer preserves the CLI PHP scan directory when it launches child processes.
- cPanel Directory Privacy blocked anonymous requests and accepted authorized HTTPS requests to temporary probes on both test domains. A dedicated MariaDB database and its scoped user were created on Stellar. Their credentials are stored privately on Stellar.

- The signed release `154c084` was deployed over SSH to the two test hosts with `APP_ENV=test`, isolated MariaDB and temporary local storage. Doctrine initialized the disposable staging database. Both hosts returned 401 without staging credentials and served the expected pages over HTTPS with credentials.
- Verified local 404 responses without redirects for public GET and POST requests to staff URLs, a synthetic staff login, invalid CSRF rejection, access to the signed-in dashboard and both application CLI jobs (`mail` and `pending`). The test email queue was empty.
- A deliberately unreachable database prevented release promotion on Stellar and preserved the active configuration. A compatible second release was promoted and rolled back to `154c084-local`. Both test hosts retained authentication and working HTTPS after rollback.
- The full PHPUnit run completed without failures or errors (378 tests, 976 assertions, 41 skipped). Standalone checks and the local Stellar split HTTP rehearsal passed.
- A synthetic registration staged two PDFs, exposed a byte-identical CV preview only in the original session, and confirmed one database record with both documents. The synthetic record and documents were removed.
- The original macOS archive extracted successfully with extended-attribute warnings. The packager now produces an archive without those attributes on macOS, verified locally.
- Authenticated `amea-sandbox` and Brevo verification await explicit authorization to transfer those existing credentials to the Stellar test account.
