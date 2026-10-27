# Public/staff rollout and rollback

This is an unexecuted production procedure. The isolated rehearsal is recorded in [release qualification](../testing/release-qualification.md). Production, DNS, real SMTP, actual backups and external restoration require explicit approval; no such actions were performed.

## Inventory and recovery prerequisites

Record the existing application image digest, proxy/domain rules, MariaDB version, database endpoint/name/user, database network and actual uploads/storage/logs volume names. Verify volume ownership/access for UID 33 and private-file denial. Preserve the running DB service and its volume. The existing Dokploy configuration uses MariaDB 10.11; the isolated rehearsal uses MySQL 8. This does not qualify the actual MariaDB dataset or production storage.

Before an approved change, obtain a verified database export and a consistent uploads/storage backup; restore those into a separately isolated environment and check member records plus identity/CV and public image bytes. Inventory scheduled tasks and mail queue/lock paths. Capture the current public text, photos and logo review; the user supplied the official PNG and approved current public content.

Never run `down -v` against the existing application/database stack. The new configuration intentionally has **no database service** and requires explicitly named external data volumes/network; an implicit fresh database or new empty uploads volume is not a migration.

## Ordered application transition

1. Retain the current application image, configuration and routing. Keep its administration available while qualifying the replacement.
2. Review and manually run migration `06_expand_member_enum_compatibility.php` against the explicitly selected backed-up database. It appends enum values and preserves legacy values/order, defaults, collation and comments; do not alter applied migrations or narrow enums during rollback.
3. Build immutable public, staff and jobs images from the reviewed revision using `docker/Dockerfile.apps`. `compose.apps.dokploy.yml` is a proposal for separate services on the existing host; set each image reference, `PUBLIC_APP_URL=https://aeesgs.org`, `STAFF_APP_URL=https://admin.aeesgs.org`, existing DB credentials/network and the inventoried external volume names. Keep credentials untracked. Validate the resolved configuration before any approved start; no production values are supplied here.
4. Rehearse each image independently. Configure Dokploy domain routing to internal port 80 only after verifying the target application. Public and staff receive distinct session volumes and host-only cookie names; existing member paths and token query strings remain unchanged. The public host returns fixed staff redirects for legacy GET pages and HTTP409 recovery for old POST forms, without replaying actions.
5. Verify registration/own staged previews/review/confirmation, token correction/consent, staff login/recovery, roles, document downloads, exports, carousel publication and settings against the shared data. Test invalid/expired/reset links, CSRF and wrong-app/private/static route rejection. Do not remove the former administration endpoint until this passes.
6. Obtain explicit production/domain cutover approval. Apply only the reviewed routing changes. Monitor both applications and the queue; retain the former image/configuration and data volumes.
7. Disable the previous scheduler/cron owner **before** enabling the new `jobs` profile. Both web images omit scripts; only the jobs image owns shared scheduled work and locks. Mail runs each minute, abandoned staging hourly, and trash daily only with a verified current backup plus `AMEA_TRASH_BACKUP_CONFIRMED=1`. Do not run two schedulers. Leave the jobs profile disabled until this transition is approved.

## Rollback procedure

Stop the new jobs owner first. Keep the database and shared volumes intact. Restore the recorded previous app image/proxy routes using the same existing data; keep expanded migration06 in place. Do not delete data, narrow enum values or restore an older database over new submissions as an automatic rollback step.

Keep the per-container `tmpfs` mount at `/opt/amea/storage/cache` for public, staff and jobs, including retained images. Twig regenerates compiled templates from each image on first use; exclude `storage/cache` from backups and restores so compiled templates cannot be reintroduced from another version. Uploads, mail queue, job locks and other persistent storage remain shared.

Recheck authorized private documents, public registration and staff login. Re-enable exactly one previously recorded scheduler only after the new owner is stopped. If data restoration is required, stop writes and separately approve the selected backup and recovery point; that is a data recovery operation, not an image rollback.

The local test retained the real pre-08 public image from `ff3a8abac51eda2bec3e115b603c7e788de472f8`, observed its old HTTP404 staff-link behavior, then restored the candidate's HTTP302 behavior while DB/private/public file hashes remained identical. This qualifies that local version rollback and forward transition. It does not qualify an arbitrary historical image or the actual production cutover/restore; repeat the rehearsal with the exact production image and dataset before approval.
