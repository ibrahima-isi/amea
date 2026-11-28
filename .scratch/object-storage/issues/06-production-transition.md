# 06 — Execute the approved production storage transition

Status: ready-for-human

Blocked by: None. Ticket 05 is complete and the operator approved the live transition. Post-cutover verification remains open.

## Scope

Execute [plan Task 6](../plan.md#task-6--approved-production-transition) under the [approved design](../spec.md). An operator selects and approves the actual production database, source storage, R2 bucket, backup/recovery point and measured maintenance window.

## Acceptance criteria

- [x] Merged code, latest-commit CI, actual deployed revision and Ticket 05 qualification are verified.
- [x] Actual production source/reference inventory is reconciled, and a coordinated backup restores successfully.
- [x] Backup capacity/cadence, all writers, session persistence and the measured maintenance window are recorded.
- [x] Explicit approval identifies the live resources and final transition procedure.
- [x] Final frozen inventory, read-back hashes, guarded corrections and target configuration all agree before reopening.
- [x] Authorized application access, public media, privacy denials and pre-existing registration staging pass.
- [ ] Post-reopening behavior and ongoing object backup coverage are verified, including the first scheduled backup and its purge ordering. Record results below.
- [x] Local source copies and the associated backup remain available until validation and a separate deletion decision.

## Comments

2026-11-12 — Human-owned because selecting/approving the live resources and executing a production change require explicit operator involvement under AGENTS.md. Design approval and ticket readiness do not constitute that approval.

2026-11-26 — The Stellar qualification, transfer rehearsal, and GitHub Actions staging publication path are complete; the operator approval package now lives at [stellar-production-transition.md](../../../docs/operations/stellar-production-transition.md).

2026-11-27 — The coordinated pre-pause production backup restored successfully. The isolated database and file rehearsal reconciled the captured source with the operator-approved missing references. See the [legacy import plan and rehearsal results](../legacy-production-import-plan.md).

2026-11-27 — PR #64 merged as `9211d993da1330db73ccaf6c4a509897a9c6757c`. Its main CI passed and the cutover deployed `prod-9211d99` to separate public and staff roots. The operator approved the dedicated Stellar MariaDB database, `amea-production`, the final disposition of the missing legacy references and the write reopening after DNS verification.

- The successful maintenance interval ran from 03:15:08 to 04:01:40 UTC, a total of 46 minutes and 32 seconds. The final coordinated point restored 207 objects, with no public or staff sessions eligible for transfer. The split PHP `php` session stores were attested separately before reopening. The VPS application remains paused and its source files and recovery copies are retained.
- A manual invocation of the recurring backup procedure restored 16 MariaDB tables and 207 R2 objects in isolated targets. It checked 68 database references, including the approved existing exceptions. `point-20261127T042936Z` was verified at 04:29:50 UTC. It is in the private `~/amea-prod/backups` directory and uses approximately 98 MiB of the 20 GiB account quota.
- The operator approved daily backups at 03:00 Dakar time and rotation of seven verified points. A single Stellar cron entry dispatches mail every five minutes and staged upload cleanup hourly. The once-daily 30-day trash job is eligible only after the day's backup is restored and its checksums are verified. The public and staff PHP entry points and this cron own the live writes.
- The first cron cycle ran at 04:35:09 UTC. The mail and staged upload workers both recorded success. The daily purge gate was validated in an isolated scheduler fixture. The first scheduled backup and its production purge ordering await observation. Public and staff HTTPS checks passed for the homepage, journal, registration form, staff sign-in page and denial of anonymous private document access. The authenticated production download check remains outstanding.
- `prod-572f9d8` includes the carousel correction from PR #65. Chrome observed automatic rotation from `1 / 2` to `2 / 2` on the live homepage. The cPanel cron and current verified backup were still present after deployment, and the legacy VPS was confirmed paused. [Production record](../../../docs/operations/stellar-production-transition.md) · [PR #65](https://github.com/ibrahima-isi/amea/pull/65)
- Zero-mutation live production verification on `prod-572f9d8` (11/11 checks passed at 16:12 UTC, zero PII printed or stored): unauthenticated `admin.aeesgs.org/download.php` (`identite` & `cv`) and forged/revoked `AMEA_STAFF` cookies redirected `302` to `login.php`; `aeesgs.org/download.php` returned local `404` without `Location`; anonymous `aeesgs.org/registration-preview.php` returned `404`; published public slider media returned `200` with byte-identical R2 SHA-256 (`575354` B); authenticated `admin.aeesgs.org/dashboard.php` returned `200`; authenticated `download.php` for both `identite` (`1988148` B, `image/jpeg`) and `cv` (`655767` B, `application/pdf`) returned `200` with `X-Content-Type-Options: nosniff`, `Cache-Control: private, no-store`, `Content-Disposition: attachment`, and exact `amea-production` R2 SHA-256 read-back match; ephemeral verification session was removed immediately and re-verified denied (`302`).
- GitHub `production` environment synced with all 25 Stellar production secrets and `STELLAR_DEPLOY_ENABLED=true`. Continuous cPanel cron execution verified (`last-stellar-cron=2026-11-27T15:30:20Z`, `last-mail=2026-11-27T15:30:20Z`, `last-pending=2026-11-27T15:00:37Z`). Only the first scheduled daily 03:00 Dakar/UTC backup (`2026-11-28T03:00Z`) and its production trash-purge ordering remain before closing Ticket 06.
