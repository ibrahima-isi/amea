# Public/staff rollout and rollback

This is an unexecuted production procedure. Reproduce the isolated rehearsal using the [testing guide](../testing/README.md). Production, DNS, real SMTP, actual backups and external restoration require explicit approval; no such actions were performed.

## Canonical deployment configuration

`compose.dokploy.yml` is the single Dokploy deployment file. It builds `public`, `staff`, the one-shot `migrate` step and the optional `jobs` target from `docker/Dockerfile.apps`, and retains the existing `db` service on MariaDB 10.11. No pre-published `AMEA_*_IMAGE` values, external database host, external data network or renamed data volumes are required.

Keep the existing Dokploy project name and the volume keys `amea_db`, `amea_uploads`, `amea_storage` and `amea_logs`. Compose derives their actual names from that project; using a new project name would select different volumes. The applications mount the same uploads/storage/logs at their new image paths under `/opt/amea`. Public and staff have separate session volumes, while compiled Twig caches remain container-local tmpfs.

Retain the existing `DB_NAME`, `DB_USER`, `DB_PASS`, `MARIADB_ROOT_PASSWORD`, `MAIL_USER` and `MAIL_PASS` configuration. Set `PUBLIC_APP_URL` and `STAFF_APP_URL` to the two origins; each service derives its own `APP_URL`, and `DB_HOST` stays `db`. Keep these values untracked. Validate without starting services:

```sh
docker compose -f compose.dokploy.yml config --quiet
docker compose -f compose.dokploy.yml --profile jobs config --quiet
docker compose -f compose.dokploy.yml build public staff
```

`MAIL_ASYNC` defaults to `0`, preserving synchronous delivery. The `jobs` profile is disabled by default. Enabling an async queue without its designated worker would leave queued messages pending; coordinate any future change to `MAIL_ASYNC=1` with the single-worker handover below. Local and synthetic test Compose files retain their separate purposes.

## Inventory and recovery prerequisites

Record the existing application image digest, proxy/domain rules, MariaDB version, database endpoint/name/user, database network and actual uploads/storage/logs volume names. Verify volume ownership/access for UID 33 and private-file denial. Preserve the running DB service and its volume. The existing Dokploy configuration uses MariaDB 10.11; the isolated rehearsal uses MySQL 8. This does not qualify the actual MariaDB dataset or production storage.

Before an approved change, obtain a verified database export and a consistent uploads/storage backup; restore those into a separately isolated environment and check member records plus identity/CV and public image bytes. Inventory scheduled tasks and mail queue/lock paths. Capture the current public text, photos and logo review; the user supplied the official PNG and approved current public content.

Never run `down -v` against the existing application/database stack. The canonical configuration retains ownership of its database and existing data volumes; it does not move them to external resources. Compare the resolved project, service and volume identities with the running stack before an approved redeploy. Keeping logical keys in the repository alone does not prove the live resource identities or authorize creating replacement empty volumes.

## Ordered application transition

1. Retain the current application image, configuration and routing. Keep its administration available while qualifying the replacement.
2. Review the unapplied migrations against the explicitly selected backed-up database. The `migrate` service runs every migration in numeric order on each deploy; `public`, `staff` and `jobs` start only after it exits successfully, and a failed migration blocks the start. Migration `06_expand_member_enum_compatibility.php` appends enum values and preserves legacy values/order, defaults, collation and comments; `07_create_association_journal.php` adds the journal table. Do not alter applied migrations or narrow enums during rollback.
3. Configure Dokploy to build the `public` and `staff` targets from the reviewed Git revision through `compose.dokploy.yml`; retain each resulting image ID/digest for rollback. Set `PUBLIC_APP_URL=https://aeesgs.org` and `STAFF_APP_URL=https://admin.aeesgs.org` alongside the existing credential variables. Preserve the same project, `db`, default data network and four data-volume keys. Validate the resolved configuration before any approved start; build `jobs` only for a separately approved worker activation. No production values or cutover are applied by this guide.
4. Rehearse each image independently. Configure Dokploy domain routing to internal port 80 only after verifying the target application. Public and staff receive distinct session volumes and host-only cookie names; existing public member paths and token query strings remain unchanged. As decided on 2026-10-28, every staff path requested on the public host returns HTTP404, without a Location header, staff link or form-recovery bridge. Public does not require STAFF_APP_URL; staff alone receives its own configured origin for account recovery. See [independent entry decision](../adr/0001-independent-public-and-staff-entry.md).
5. Verify registration/own staged previews/review/confirmation, token correction/consent, staff login/recovery, roles, document downloads, exports, carousel publication and settings against the shared data. Test invalid/expired/reset links, CSRF and wrong-app/private/static route rejection. Do not remove the former administration endpoint until this passes.
6. Obtain explicit production/domain cutover approval. Apply only the reviewed routing changes. Monitor both applications and the queue; retain the former image/configuration and data volumes.
7. Leave synchronous mail and the disabled `jobs` profile unchanged until the scheduler transition is approved. Disable the previous scheduler/cron owner **before** enabling the new `jobs` profile. Both web images omit scripts; only the jobs image owns shared scheduled work and locks. Mail runs each minute, abandoned staging hourly, and trash daily only with a verified current backup plus `AMEA_TRASH_BACKUP_CONFIRMED=1`. Do not run two schedulers. Switch the web services to `MAIL_ASYNC=1` only when the designated worker is ready.

## Rollback procedure

Stop the new jobs owner first if it was enabled; restore `MAIL_ASYNC=0` for synchronous delivery and account for any already queued messages. Keep `db`, its volume and the shared application volumes intact. Restore the recorded previous app image/configuration/proxy routes using the same existing project and data; keep additive migrations06/07 in place. Do not delete data, narrow enum values or restore an older database over new submissions as an automatic rollback step.

Keep the per-container `tmpfs` mount at `/opt/amea/storage/cache` for public, staff and jobs, including retained images. Twig regenerates compiled templates from each image on first use; exclude `storage/cache` from backups and restores so compiled templates cannot be reintroduced from another version. Uploads, mail queue, job locks and other persistent storage remain shared.

Recheck authorized private documents, public registration and staff login. Re-enable exactly one previously recorded scheduler only after the new owner is stopped. If data restoration is required, stop writes and separately approve the selected backup and recovery point; that is a data recovery operation, not an image rollback.

The historical local test retained the real pre-08 public image from `ff3a8abac51eda2bec3e115b603c7e788de472f8`, observed its old HTTP404 staff-link behavior, then restored that candidate's HTTP302 behavior while DB/private/public file hashes remained identical. The 2026-10-28 candidate deliberately restores HTTP404 isolation after the same rollback/forward and separate restore rehearsal; its current behavior supersedes the earlier redirect expectation. This does not qualify an arbitrary historical image or the actual production cutover/restore; repeat the rehearsal with the exact production image and dataset before approval. A rollback to an image containing the former bridge would reintroduce it and is incompatible with the new isolation requirement unless separately approved.
