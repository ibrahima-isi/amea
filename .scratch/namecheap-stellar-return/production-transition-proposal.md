# Production Transition Approval Proposal: Dokploy VPS to Namecheap Stellar + Cloudflare R2

Status: **Awaiting explicit operator approval and pre-approval live VPS inventory**

This document prepares the concrete move of AMEA from its legacy Dokploy VPS deployment to the split Namecheap Stellar installation (`aeesgs.org` and `admin.aeesgs.org`) backed by a private Cloudflare R2 production bucket. It executes **zero** production changes. Real production file copy, database migration, DNS cutover, and activation are tracked separately under [object-storage ticket 06](../object-storage/issues/06-production-transition.md) and require explicit operator authorization after all pre-approval gates below are closed.

---

## 1. Source Inventory (Legacy Dokploy VPS)

### Known architecture from repository records
- **Deployment files:** `compose.dokploy.yml`, [public-staff rollout guide](../../docs/operations/public-staff-rollout.md), [object-storage operations](../../docs/operations/object-storage.md), and [OOP Stellar reconciliation plan](../composer-oop/plan.md).
- **Expected database engine:** MariaDB 10.11 (`db` service in Dokploy Compose).
- **Expected file storage:** Local filesystem `uploads/` volume (`uploads/students/`, `uploads/students/cvs/`, `uploads/students/pending/`, `uploads/students/pending/cvs/`, `uploads/slider/`, `uploads/journal/`).
- **Expected session topology:** Legacy single-app `PHPSESSID` file-session store (combined public registrations + staff logins) or split `AMEA_PUBLIC` / `AMEA_STAFF` if already upgraded on Dokploy.
- **Expected writers:** Web PHP application(s), scheduled mail/pending/trash cleanup jobs, and any host-level cron or manual maintenance scripts.

### Pending pre-approval gate: Approved read-only live VPS inventory
Per operator direction, live Dokploy VPS inspection is deferred to a dedicated read-only pre-approval step before Ticket 06 begins. Record the following non-secret facts from the live VPS (excluding all credentials, tokens, and member PII):

| Inventory item | Required read-only evidence (non-secret) | Status |
| --- | --- | --- |
| Source code revision & container image | Deployed Git SHA / image digest, PHP version (`php -v`), extensions | **Pending** |
| Active roles & all writers | Running Compose services (`docker compose ps`), host/container crons, any external DB/file writers | **Pending** |
| Database state | MariaDB version, DB size, table list (verify zero views/triggers/routines/events), row counts (`students`, `users`, `slider_images`, `journal_posts`, `file_operations`), Doctrine enrollment/version status | **Pending** |
| File storage inventory | `uploads/` total byte volume, regular file count, max single file size, symlinks/nonregular/unknown dirs, and `scripts/storage-migrate.php inventory` + `audit-references` counts | **Pending** |
| Session topology | Active `session.save_handler`, `session.serialize_handler` (`php`/`php_serialize`/`php_binary`), `session.save_path`, cookie name (`PHPSESSID` vs `AMEA_PUBLIC`/`AMEA_STAFF`), and active session count | **Pending** |
| Existing backup baseline | Current VPS backup mechanism, last verified restore date, available temporary spool/backup disk space | **Pending** |

---

## 2. Selected Private Destinations (Namecheap Stellar + Cloudflare R2)

All account capabilities below were verified on the assigned Stellar account in Tickets 01–02 ([completion.md](completion.md)), with all credentials excluded:

| Destination component | Selection | Verified status |
| --- | --- | --- |
| Hosting account | Namecheap Stellar cPanel account (`aeesgs.org` primary domain, SSH alias `cpanel` port `21098`, 20 GiB quota / 300,000 inodes, `upload_max_filesize=1024M`, `post_max_size=1024M`) | Qualified (Ticket 01) |
| Public web host | `https://aeesgs.org` → document root `~/public_html`, fixed `AMEA_APP='public'`, host-only `AMEA_PUBLIC` cookie, LiteSpeed handler `application/x-httpd-alt-php85___lsphp` (PHP 8.5.13 + all 8 required extensions) | Root exists; production release wiring pending Ticket 06 |
| Administration web host | `https://admin.aeesgs.org` → separate cPanel subdomain document root (e.g. `~/admin.aeesgs.org`, outside `~/public_html`), fixed `AMEA_APP='staff'`, host-only `AMEA_STAFF` cookie, same PHP 8.5 LiteSpeed handler | **Pending** cPanel subdomain/docroot & Cloudflare Origin CA/DNS setup |
| Private release & shared state | Outside both docroots (e.g. `~/amea-prod/`): immutable `releases/<id>`, atomic `current`/`previous` symlinks, `shared/config/runtime.env` (`0600`), `shared/state/{sessions/public,sessions/staff,cache/twig/public,cache/twig/staff,write-gate,mail-queue,logs,.shared-jobs.lock}` (`0700`), `bin/run-job.php` (`0700`) | Layout & `deploy.sh` verified on staging (`demo.aeesgs.org` / `bo.aeesgs.org`) |
| Scheduled CLI runtime | `/opt/alt/php85/usr/bin/php` with `PHP_INI_SCAN_DIR=/opt/alt/php85/link/conf:/etc/cl.php.d/alt-php85` invoking `~/amea-prod/bin/run-job.php` via cPanel cron (minimum 5-minute interval, max 5 simultaneous jobs) | Verified via 5-minute cPanel cron & `bin/run-job.php` (Tickets 01–02) |
| Production database | Dedicated MariaDB 11.4.13 database & scoped user on Stellar (isolated from the staging MariaDB database) | MariaDB 11.4.13 restore verified (Ticket 01); **production DB pending creation** |
| Production object storage | New dedicated private Cloudflare R2 bucket (distinct from `amea-sandbox`; `scripts/stellar/verify-release.php` refuses `amea-sandbox` when `APP_ENV=production`), `STORAGE_DRIVER=s3`, `S3_REGION=auto`, `S3_PATH_STYLE=false`, bucket-scoped S3 key/secret | Provider compatibility verified on `amea-sandbox` (Tickets 02–03); **production bucket & credentials pending creation** |
| Transactional email | Brevo SMTP (`smtp-relay.brevo.com:587`, `STARTTLS`, sender `noreply@aeesgs.org`) with Stellar outbound IPv4 `198.54.114.79` allowlisted in Brevo Authorized IPs (no outbound IPv6) | Authenticated delivery to `contact@aeesgs.org` verified (Ticket 02) |

---

## 3. Required Verification, Continuity & Backup Controls

### 3.1 Restorable backup evidence
1. **Pre-pause baseline backup (before modifying production):** capture a transaction-consistent `mariadb-dump` (following [migration-recovery.md](../../docs/operations/migration-recovery.md)) and verify isolated MariaDB 11.4.13 import (confirming table count, foreign keys, and UTF-8 accented member rows).
2. **Frozen cutover recovery point (under the paused write gate, after `drain-intents` and final `verify`):** capture a coordinated `RecoverySnapshot` (`scripts/storage-backup.php capture`) binding application tables, `file_operations`, all managed upload bytes with original `createdAt`/MIME/SHA-256, session trees, and the finalized migration manifest/checkpoint, and verify restorability via isolated `scripts/storage-backup.php restore` into a separate empty database, separate empty object namespace, and fresh session stores.
3. Record non-secret `snapshot.json` SHA-256, `manifest` SHA-256, `checkpoint` SHA-256, and dump checksum in the Ticket 06 completion log.

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
  4. If an in-progress registration exists at cutover, confirm read-only paused preview (`allowPausedRead`) succeeds before reopening and normal confirmation succeeds once afterward (per [object-storage ticket 06](../object-storage/issues/06-production-transition.md)).
- Rehearsed end-to-end in Ticket 03 ([completion.md](completion.md#03-legacy-to-stellar-transfer-and-registration-resume-rehearsal)) using real headless Chrome across the same public origin against live Cloudflare R2 `amea-sandbox` (43/43 checks passed).

### 3.4 Maintenance window estimate
- **Rehearsed baseline (Ticket 03, live R2 `amea-sandbox`, 3 synthetic PDFs, local PHP 8.5.13 + SQLite):**
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
5. Production MariaDB 11.4.13 has all data imported and Doctrine migrations verified clean (`scripts/migrate.php migrate` at latest reviewed version, enrolled per [doctrine-enrollment.md](../../docs/operations/doctrine-enrollment.md) if migrating from a pre-Doctrine schema).
6. Fresh (<5 minutes old) native `scripts/storage-target.php` attestation reports for `public` (`--docroot=~/public_html`), `staff` (`--docroot=~/admin.aeesgs.org`), and `jobs` (`--job-wrapper=~/amea-prod/bin/run-job.php`) pass `Cutover::targetIdentity()` / `resume`.
7. **Paused read-only HTTPS smoke checks (before reopening):** on `https://aeesgs.org` and `https://admin.aeesgs.org`, confirm public homepage/journal return `200`, staff paths on `aeesgs.org` return local `404` without redirects, published slider media serves from R2, unauthenticated `admin.aeesgs.org/download.php` is denied (`302`/`503` while paused; staff login stays `503` because `sessions/staff` is empty during pause), and PHP 8.5.13 with all 8 extensions is active on both web hosts.
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
- **Retention:** Retain the stopped Dokploy VPS, local source `uploads/`, and pre-cutover coordinated backup intact until post-cutover validation is accepted and a separate explicit deletion/retirement approval is granted.

---

## 4. Publication & Domain Cutover Prerequisites

### 4.1 Publishing the tested `main` revision
1. Merge the Ticket 03/04 changes into `main` with all CI checks passing on the exact merged commit SHA.
2. Port and stage-qualify the serialized GitHub Actions `deploy-stellar` job in `.github/workflows/ci.yml` before using production resources (required by [spec.md](spec.md) and [OOP Stellar plan](../composer-oop/plan.md)): non-cancelling `main` concurrency, pinned SSH host key, GitHub Secrets for private `0600` runtime config, guarded Doctrine migration before docroot promotion, cutover pause interlock, rollback check, and live split route smoke checks.
3. Verify `APP_ENV=production` in production `runtime.env` so `scripts/stellar/verify-release.php` enforces the non-sandbox bucket guard.

### 4.2 Switching the two web domains (`aeesgs.org` and `admin.aeesgs.org`)
1. Lower Cloudflare DNS TTL (or confirm proxied orange-cloud records) for `aeesgs.org` and `admin.aeesgs.org` ahead of the window.
2. Confirm Cloudflare Origin CA certificate installed on Stellar covers both `aeesgs.org` and `admin.aeesgs.org` (plus `www.aeesgs.org` if redirected).
3. Verify `PUBLIC_APP_URL=https://aeesgs.org` and `STAFF_APP_URL=https://admin.aeesgs.org` in production `runtime.env`, and confirm AutoSSL `.well-known/acme-challenge/` + LiteSpeed PHP 8.5 handler + static asset allowlist in both `.htaccess` files.
4. Switch DNS/origin targets only after Stellar staging/pre-cutover checks pass and while source writers remain paused; keep legacy VPS writers paused throughout DNS propagation.

---

## 5. Unresolved Evidence & Explicit Approval Gate

The following items remain open and **must be resolved before executing [object-storage ticket 06](../object-storage/issues/06-production-transition.md)**:

1. **Live Dokploy VPS read-only inventory:** Actual deployed SHA/image, writers/crons, MariaDB version/size/Doctrine enrollment state, `uploads/` volume/count/dispositions, and active session store/serializer.
2. **Production Cloudflare R2 bucket & credentials:** Create dedicated private production bucket (separate from `amea-sandbox`), configure bucket-scoped S3 credentials, and run synthetic write/read-back/delete verification before live copy.
3. **Production Stellar resources:** Provision `admin.aeesgs.org` cPanel subdomain/docroot, verify TLS on `aeesgs.org` and `admin.aeesgs.org`, and create the dedicated production MariaDB 11.4.13 database & scoped user.
4. **GitHub Actions `deploy-stellar` job:** Port `deploy-stellar` into `.github/workflows/ci.yml` and qualify it on staging before production publication.
5. **Post-cutover backup cadence & mail timing:** Confirm recurring R2 + MariaDB backup capacity/cadence on Stellar, confirm whether `MAIL_ASYNC` stays synchronous (recommended so 5-minute password-reset tokens do not wait on a 5-minute cron interval), and confirm Brevo Authorized IPs policy for shared hosting outbound IPv4 `198.54.114.79`.
6. **Optional MariaDB-backed Chrome transfer check:** Ticket 03 rehearsed the full Chrome cutover locally with SQLite + packaged Stellar runtime + live R2 `amea-sandbox` (while MariaDB 11.4.13 restore and LiteSpeed/HTTPS R2 flows were separately verified in Tickets 01–02); decide whether to run one final disposable MariaDB import + Chrome rehearsal before live cutover.

### Operator approval required to start Ticket 06
Approval of this proposal authorizes **only** resolving the pre-approval gates above. Starting live production file copy, database migration, and DNS cutover under [object-storage ticket 06](../object-storage/issues/06-production-transition.md) requires separate explicit operator sign-off naming:
- Verified source VPS revision, database, upload inventory SHA-256, and reviewed dispositions (if any).
- Exact production R2 bucket name, Stellar MariaDB database name, `aeesgs.org` / `admin.aeesgs.org` docroots, and target `main` commit SHA.
- Approved maintenance window, post-cutover backup cadence, and rollback trigger contact.
