# AMEA production transition from Dokploy to Stellar and Cloudflare R2

Status: **Stellar is live. The legacy VPS remains frozen.**

## Production record

- On 2026-11-27, the successful cutover ran from 03:15:08 to 04:01:40 UTC. The public and administration domains now serve from separate Stellar document roots. The cutover deployed `prod-9211d99`, restored 207 objects to the private `amea-production` bucket, and verified the restored MariaDB schema, object contents, references and split session stores before reopening writes. No active registration qualified for session transfer. [Ticket 06](../../.scratch/object-storage/issues/06-production-transition.md)
- The carousel fix in `572f9d875234eb0d503535f5144cf2dfe9a50b12` was deployed as `prod-572f9d8`. The live homepage served the versioned JavaScript from this release, and Chrome observed automatic rotation from `1 / 2` to `2 / 2`. The public and administration HTTPS checks passed. [PR #65](https://github.com/ibrahima-isi/amea/pull/65) · [Main CI](https://github.com/ibrahima-isi/amea/actions/runs/45946981209)
- The recurring backup procedure passed a manual capture and isolated restoration at 04:29:50 UTC on 2026-11-27. Point `point-20261127T042936Z` verified 16 MariaDB tables, 207 R2 objects and 68 references, including the approved pre-existing exceptions. Stellar stores the points in the private `~/amea-prod/backups` directory. The approved schedule starts daily at 03:00 Dakar time and retains up to seven verified points. The first scheduled capture is due on 2026-11-28.
- The single Stellar cPanel cron entry ran its first cycle at 04:35:09 UTC on 2026-11-27. The mail worker succeeded and runs every five minutes. The staged upload cleanup succeeded and runs hourly. The daily 30-day trash purge requires a restore-tested backup from that day with verified checksums. An isolated scheduler test verified this condition. Brevo authenticated from Stellar, and transactional mail uses synchronous delivery.
- The legacy VPS application remains paused. Its source files and verified recovery copies are retained for rollback. Any future source reopening or retirement requires separate operator authorization.

The sections below retain the qualification and recovery procedure used for the cutover, including the backup, verification and rollback requirements for future operations.

---

## 1. Source Inventory (Legacy Dokploy VPS)

### Known architecture from repository records
- **Deployment files:** `compose.dokploy.yml`, [public-staff rollout guide](public-staff-rollout.md), [object-storage operations](object-storage.md), and [OOP Stellar reconciliation plan](https://github.com/ibrahima-isi/amea/blob/6861587753a4fc6fd2551d7f8b9c766ad3135ee6/.scratch/composer-oop/plan.md).
- **Expected database engine:** MariaDB 10.11 (`db` service in Dokploy Compose).
- **Expected file storage:** Local filesystem `uploads/` volume (`uploads/students/`, `uploads/students/cvs/`, `uploads/students/pending/`, `uploads/students/pending/cvs/`, `uploads/slider/`, `uploads/journal/`).
- **Expected session topology:** Legacy single-app `PHPSESSID` file-session store (combined public registrations + staff logins) or split `AMEA_PUBLIC` / `AMEA_STAFF` if already upgraded on Dokploy.
- **Expected writers:** Web PHP application(s), scheduled mail/pending/trash cleanup jobs, and any host-level cron or manual maintenance scripts.

### Read-only source inventory requirements

Before a cutover, gather the following evidence without collecting credentials, tokens, or member personal data.

| Inventory item | Required read-only evidence |
| --- | --- |
| Source code revision & container image | Deployed Git SHA, image digest, PHP version and extensions |
| Active roles & writers | Running Compose services, host and container crons, and external DB or file writers |
| Database | MariaDB version and size, table and database object inventory, row counts and Doctrine enrollment |
| File storage | Byte and file counts, largest file, special entries and reference differences |
| Sessions | Save handler, serializer, save path, cookie name and active session count |
| Backup baseline | Backup mechanism, date of the latest verified restore and spool capacity |

---

## 2. Selected Private Destinations (Namecheap Stellar + Cloudflare R2)

All account capabilities below were verified on the assigned Stellar account in the completed Stellar qualification effort ([archived record at `688a6b2`](https://github.com/ibrahima-isi/amea/blob/688a6b2a7af7d814c0d30f0e4422f71944258231/.scratch/namecheap-stellar-return/completion.md)), with all credentials excluded:

| Destination component | Selection | Verified status |
| --- | --- | --- |
| Hosting account | Namecheap Stellar cPanel account (`aeesgs.org` primary domain, SSH port `21098`, 20 GiB quota / 300,000 inodes, `upload_max_filesize=1024M`, `post_max_size=1024M`) | Qualified |
| Public web host | `https://aeesgs.org` → `~/public_html`, fixed `AMEA_APP='public'`, host-only `AMEA_PUBLIC` cookie and PHP 8.5.13 LiteSpeed handler | Live over HTTPS via Cloudflare |
| Administration web host | `https://admin.aeesgs.org` → `~/admin.aeesgs.org`, fixed `AMEA_APP='staff'`, host-only `AMEA_STAFF` cookie and the same PHP handler | Live over HTTPS via Cloudflare with a separate document root |
| Private release & shared state | `~/amea-prod` contains immutable `releases/<id>`, `current` and `previous` symlinks, private runtime configuration, split session directories, the shared write gate and the job wrapper | Verified on Stellar |
| GitHub Actions publication | `.github/workflows/ci.yml` gates `deploy-stellar` on `main` through the `production` environment and `STELLAR_DEPLOY_ENABLED` | Workflow publication and rollback qualified on staging. Production uses the tested release package and pinned SSH with `STELLAR_DEPLOY_ENABLED=false`. |
| Scheduled CLI runtime | `~/amea-prod/bin/backup-ops/schedule.sh tick` runs from the single cPanel cron entry every five minutes. It dispatches `~/amea-prod/bin/run-job.php` with `/opt/alt/php85/usr/bin/php` and `PHP_INI_SCAN_DIR=/opt/alt/php85/link/conf:/etc/cl.php.d/alt-php85`. | Live cron and successful mail and staged cleanup invocations recorded |
| Production database | `aeesjwmz_amea_prod` on MariaDB 11.4.13, with a scoped user and an isolated restore-test database | Live and restore tested |
| Production object storage | Private `amea-production` R2 bucket with bucket-scoped credentials, `STORAGE_DRIVER=s3`, R2 configuration aliases and `S3_PATH_STYLE=false` | Production access and read-back verified |
| Transactional email | Brevo SMTP at `smtp-relay.brevo.com:587` with STARTTLS and sender `noreply@aeesgs.org` | Production authentication verified from Stellar |

---

## 3. Required Verification, Continuity & Backup Controls

### 3.1 Restorable backup evidence
1. **Pre-pause baseline backup (before modifying production):** capture a transaction-consistent `mariadb-dump` (following [migration-recovery.md](migration-recovery.md)) and verify isolated MariaDB 11.4.13 import (confirming table count, foreign keys, and UTF-8 accented member rows).
2. **Frozen cutover recovery point (under the paused write gate, after `drain-intents` and final `verify`):** capture a coordinated `RecoverySnapshot` (`scripts/storage-backup.php capture`) binding application tables, `file_operations`, all managed upload bytes with original `createdAt`/MIME/SHA-256, session trees, and the finalized migration manifest/checkpoint, and verify restorability via isolated `scripts/storage-backup.php restore` into a separate empty database, separate empty object namespace, and fresh session stores.
3. Record non-secret `snapshot.json` SHA-256, `manifest` SHA-256, `checkpoint` SHA-256, and dump checksum in Ticket 06 evidence.

### 3.2 File read-back and reference verification
- Run `scripts/storage-migrate.php inventory`, `audit-references`, `copy --review-manifest=<SHA256>`, and `verify`.
- Every selected object must pass **independent destination read-back** comparing full stream SHA-256, byte size, MIME type, and preserved original `createdAt` lifecycle timestamp (metadata/ETag checks alone are insufficient).
- Any pre-existing missing references, invalid keys, symlinks, unknown directories, or legacy basename aliases must be resolved via reviewed `--mappings`, `--corrections`, or `--dispositions` files (each pinned by its SHA-256) before final verification.
- `file_operations` must be completely drained (`storage-migrate.php drain-intents`, count `0`) on the owning source backend before cutover.

### 3.3 Session continuity proof
- Legacy single-app (`sessions/app`, `PHPSESSID`) to split Stellar (`sessions/public`, `sessions/staff`) transition uses `Amea\Storage\Migration\LegacySessionTransition` inside `RecoverySnapshot::restore()`:
  - Migrates **only** sessions carrying an active `pending_registration` into `sessions/public`, whitelisting only `pending_registration`, `csrf_token`, `last_activity`, and `flash`.
  - Strips all legacy staff authentication fields and leaves `sessions/staff` empty (staff sign in fresh on `https://admin.aeesgs.org` with host-only `AMEA_STAFF`).
- `Amea\Core\Session` on `AMEA_APP='public'` adopts a returning browser's `PHPSESSID` only when `AMEA_PUBLIC` is absent and a regular non-symlink `sess_<id>` exists in `sessions/public`, whitelists the same four public keys, immediately rotates to a fresh `AMEA_PUBLIC` session (`session_regenerate_id(true)`), and expires `PHPSESSID`. `AMEA_APP='staff'` ignores `PHPSESSID` unconditionally.
- **Live-transition session continuity proof required during Ticket 06:**
  1. Confirm matching `session.serialize_handler` (`php`) between source VPS and Stellar before restoring sessions.
  2. Confirm `RecoverySnapshot::resumeRestored()` verifies exact restored `sessions/public` file digests and confirms `sessions/staff` is empty before clearing the write gate.
  3. Confirm separate-browser requests to `/registration-preview.php` return `404` and any legacy staff `PHPSESSID` is denied on both `aeesgs.org` (`404`) and `admin.aeesgs.org` (`302` to `login.php`).
  4. If an in-progress registration exists at cutover, confirm read-only paused preview (`allowPausedRead`) succeeds before reopening and normal confirmation succeeds once afterward (per [object-storage ticket 06](../../.scratch/object-storage/issues/06-production-transition.md)).
- Rehearsed end-to-end in [archived Ticket 03](https://github.com/ibrahima-isi/amea/blob/688a6b2a7af7d814c0d30f0e4422f71944258231/.scratch/namecheap-stellar-return/issues/03-rehearse-transfer-and-registration-resume.md) (PR #59) using real headless Chrome across the same public origin against live Cloudflare R2 `amea-sandbox` (43/43 checks passed).

### 3.4 Maintenance window estimate
- **Rehearsed baseline (PR #59, live R2 `amea-sandbox`, 3 synthetic PDFs, local PHP 8.5.13 + SQLite):**
  - `copy_and_verify`: `4.73 s`
  - Coordinated `capture`: `0.07 s`
  - Isolated `clean_restore` (DB + R2 write/read-back + single→split sessions): `5.29 s`
  - Native `TargetAttestation` + `clean_reopen`: `4.05 s`
  - Combined `clean_cutover_pause`: **`14.14 s`** (`30.48 s` including deliberate fault-injection and recovery checks)
- **Production sizing procedure:** Run bulk `storage-migrate.php inventory` + `copy` **online** before the maintenance window so only delta objects transfer while paused. Finalize the concrete maintenance window duration (target: **15–30 minutes** once pre-synced, subject to live DB dump/import size and delta file count) immediately after completing the read-only live VPS inventory gate.

### 3.5 Reopening criteria (all mandatory before clearing the Stellar write gate)
1. Source writers, scheduled jobs, and Dokploy containers are stopped/paused and remain paused through DNS propagation.
2. `file_operations` ledger is empty (`0` pending intents).
3. `storage-migrate.php verify` reports `0` unresolved file or reference differences against the production R2 bucket.
4. Coordinated pre-cutover backup (`snapshot.json` + `mariadb-dump`) is captured, checksummed, and retained offline/outside both docroots.
5. Production MariaDB 11.4.13 has all data imported and Doctrine migrations verified clean (`scripts/migrate.php migrate` at latest reviewed version, enrolled per [doctrine-enrollment.md](doctrine-enrollment.md) if migrating from a pre-Doctrine schema).
6. Fresh (<5 minutes old) native `scripts/storage-target.php` attestation reports for `public` (`--docroot=~/public_html`), `staff` (`--docroot=~/admin.aeesgs.org`), and `jobs` (`--job-wrapper=~/amea-prod/bin/run-job.php`) pass `Cutover::targetIdentity()` / `resume`.
7. **Paused read-only HTTPS smoke checks (before reopening):** confirm public journal and static assets return `200`, staff paths on the public domain return local `404`, and the published carousel images load from R2. Confirm that session-dependent paths such as registration and staff sign-in show maintenance, that anonymous download is denied, and that PHP 8.5.13 with all eight required extensions is active on both hosts.
8. **Immediate post-reopening checks (after clearing the write gate):** sign in fresh on `https://admin.aeesgs.org/login.php` (`AMEA_STAFF`), verify authorized private downloads return `200` with `X-Content-Type-Options: nosniff` and `Cache-Control: private, no-store`, confirm separate-browser preview denial (`404`), arm the single Stellar cPanel cron scheduler (keeping VPS crons disabled), and verify mail delivery behavior (synchronous default vs queued cron vs 5-minute password-reset token lifetime).

### 3.6 Rollback conditions

#### Before target accepts writes (write gate still paused)
- **Triggers:** Doctrine migration failure, unresolved inventory/reference/SHA-256 mismatch, failed `TargetAttestation`, broken LiteSpeed/PHP 8.5/TLS/origin check, or operator abort.
- **Action:** Keep the Stellar write gate paused (so target accepts zero mutations), point Cloudflare DNS back to the Dokploy VPS if already switched, verify untouched VPS DB/files/sessions against the pre-pause marker, and reopen VPS writers. Zero data loss.

#### After target accepts writes (post-reopening)
- **Triggers:** Critical production defect, sustained R2 or Brevo outage, or unrecoverable host error after reopening.
- **Action:**
  1. Immediately pause and drain Stellar writers (`FileWriteGate` pause) and disable Stellar cron.
  2. Capture fresh post-cutover MariaDB dump, split sessions, and fresh R2 inventory manifest.
  3. Run `scripts/storage-migrate.php reverse-copy` from production R2 back to local storage so every post-cutover registration, replacement, and corrected reference is preserved, then run `verify` (`0` differences).
  4. Restore current post-cutover DB/sessions on the fallback host and reopen only after complete read and reference verification passes.
  5. **Hard rule:** Never overwrite an active post-cutover database/storage state with the pre-cutover snapshot. If R2 is unavailable and unique post-cutover objects cannot be recovered from backup, keep affected writes paused until explicitly resolved by the operator.
- **Retention:** Keep the Dokploy VPS frozen. Retain the local source `uploads/` and the pre-cutover coordinated backup until post-cutover validation is accepted and the operator explicitly approves deletion or retirement.

---

## 4. Publication & Domain Cutover Prerequisites

### 4.1 Publishing the tested `main` revision
1. Populate the remaining production-specific secrets in the GitHub `production` environment (`STELLAR_BASE_DIR`, `STELLAR_PUBLIC_DOCROOT`, `STELLAR_STAFF_DOCROOT`, `DB_*`, `PUBLIC_APP_URL`, `STAFF_APP_URL`, `MAIL_*`, `STORAGE_DRIVER`, `S3_PATH_STYLE`, `R2_*`). Shared SSH/PHP secrets and `AmeaProdEnvKey` are already configured.
2. Confirm `APP_ENV=production` is enforced by `deploy-stellar` (`scripts/stellar/render-runtime-env.php` and `verify-release.php` block `amea-sandbox`).
3. Enable `STELLAR_DEPLOY_ENABLED=true` only after explicit production cutover approval so `deploy-stellar` publishes tested `main` commits with non-cancelling concurrency, pinned SSH, guarded Doctrine migrations, cutover pause interlock, and remote HTTPS verification (`publish.sh --mode=verify-http`).

### 4.2 Switching the two web domains (`aeesgs.org` and `admin.aeesgs.org`)
1. Lower Cloudflare DNS TTL (or confirm proxied orange-cloud records) for `aeesgs.org` and `admin.aeesgs.org` ahead of the window.
2. Confirm Cloudflare Origin CA certificate installed on Stellar covers both `aeesgs.org` and `admin.aeesgs.org` (plus `www.aeesgs.org` if redirected).
3. Verify `PUBLIC_APP_URL=https://aeesgs.org` and `STAFF_APP_URL=https://admin.aeesgs.org` in production `runtime.env`, and confirm AutoSSL `.well-known/acme-challenge/` + LiteSpeed PHP 8.5 handler + static asset allowlist in both `.htaccess` files.
4. Switch DNS/origin targets only after Stellar pre-cutover checks pass and while source writers remain paused; keep legacy VPS writers paused throughout DNS propagation.

---

## 5. Remaining verification and operator approval

- Observe the next scheduled 03:00 Dakar run and verify that its restore proof precedes the trash worker. The manual backup and isolated restore qualified the procedure. The first scheduled run and its production purge gate remain unobserved.
- Complete the authenticated production download check recorded in [Ticket 06](../../.scratch/object-storage/issues/06-production-transition.md). The public and administration routes already passed anonymous access checks.

For future production transitions, record operator approval before starting live file copy, database import, or DNS changes. Identify the source revision and inventory, the destination resources and release, the maintenance window, the backup cadence, and the rollback contact.

Keep the legacy VPS frozen until the operator authorizes a recovery or retirement action. For recovery after Stellar has accepted writes, follow the post-reopening rollback procedure above.
