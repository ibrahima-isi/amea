# Public/staff rollout and rollback

This is an unexecuted production procedure. Reproduce the isolated rehearsal using the [testing guide](../testing/README.md). Production, DNS, real SMTP, actual backups and external restoration require explicit approval; no such actions were performed.

## Canonical deployment configuration

`compose.dokploy.yml` is the single Dokploy deployment file. It builds `web`, `backoffice`, the one-shot `migrate` step and the optional `jobs` target from `docker/Dockerfile.apps`, and retains the existing `db` service on MariaDB 10.11. No pre-published `AMEA_*_IMAGE` values, external database host, external data network or renamed data volumes are required.

Keep the existing Dokploy project name and the volume keys `amea_db`, `amea_uploads`, `amea_storage` and `amea_logs`. Compose derives their actual names from that project; using a new project name would select different volumes. The applications mount the same uploads/storage/logs at their new image paths under `/opt/amea`. The applications keep their separate `public_sessions` and `staff_sessions` volumes, while compiled Twig caches remain container-local tmpfs.

Retain the existing `DB_NAME`, `DB_USER`, `DB_PASS`, `MARIADB_ROOT_PASSWORD`, `MAIL_USER` and `MAIL_PASS` configuration. Set `WEB_APP_URL=https://app.aeesgs.org` and `BACKOFFICE_APP_URL=https://admin.aeesgs.org` in the protected Dokploy environment. Compose accepts the previous `PUBLIC_APP_URL` and `STAFF_APP_URL` keys as fallbacks. Each application receives its own `APP_URL` and the back office also receives the web origin for links to the public site. Set the two service-specific origins in Dokploy because the Compose file sets `APP_URL` inside the containers. The database host remains `db`. Validate without starting services:

```sh
docker compose -f compose.dokploy.yml config --quiet
docker compose -f compose.dokploy.yml --profile jobs config --quiet
docker compose -f compose.dokploy.yml build web backoffice
```

`MAIL_ASYNC` defaults to `0`, preserving synchronous delivery. The `jobs` profile is disabled by default. Enabling an async queue without its designated worker would leave queued messages pending; coordinate any future change to `MAIL_ASYNC=1` with the single-worker handover below. Local and synthetic test Compose files retain their separate purposes.

## Inventory and recovery prerequisites

Record the existing application image digest, proxy/domain rules, MariaDB version, database endpoint/name/user, database network and actual uploads/storage/logs volume names. Verify volume ownership/access for UID 33 and private-file denial. Preserve the running DB service and its volume. The Doctrine Compose rehearsal uses MariaDB 10.11; the historical application rehearsal retains MySQL 8 coverage. These disposable tests do not qualify the actual dataset or production storage.

Before an approved change, obtain a verified database export and a consistent uploads/storage backup; restore those into a separately isolated environment and check member records plus identity/CV and public image bytes. Inventory scheduled tasks and mail queue/lock paths. Capture the current public text, photos and logo review; the user supplied the official PNG and approved current public content.

Never run `down -v` against the existing application/database stack. The canonical configuration retains ownership of its database and existing data volumes; it does not move them to external resources. Compare the resolved project, service and volume identities with the running stack before an approved redeploy. Keeping logical keys in the repository alone does not prove the live resource identities or authorize creating replacement empty volumes.

## Ordered application transition

1. Retain the current application image, configuration and routing. Keep its administration available while qualifying the replacement.
2. Stop all application writers and jobs, retaining MariaDB and its volumes. Complete [verified enrollment](doctrine-enrollment.md) on the restored copy first, then the separately approved selected database. Build the `migrate` target and run enrollment with a private evidence file mounted read-only, for example `docker compose -f compose.dokploy.yml run --rm -v /private/path/enrollment.json:/run/enrollment.json:ro migrate php scripts/enroll-migrations.php --database="$DB_NAME" --writers-stopped --evidence=/run/enrollment.json`. Review pending Doctrine versions and follow [backup and recovery](migration-recovery.md) for schema changes. The one-shot service applies pending versions only; historical scripts are retained as reference. `web`, `backoffice` and `jobs` start only after success. A failure blocks new starts; running writers must remain stopped until verification succeeds. Preserve applied versions and compatible schema during image rollback.
3. Configure Dokploy to build the `web` and `backoffice` targets from the reviewed Git revision through `compose.dokploy.yml`. Apply the origin settings above and record the built image digests. Keep the same Compose project, `db`, default data network, data volumes and session volumes. Validate the resolved configuration before starting it. Build `jobs` for a separately approved worker activation.
4. Rehearse each image independently. In the Dokploy Compose Domains tab, map `app.aeesgs.org` to `web` and `admin.aeesgs.org` to `backoffice`, both on internal port 80. Use Preview Compose to verify the labels land on the renamed services. The applications use distinct host-only session cookies. Preserve public member paths and token query strings. Verify that staff paths on the public host return HTTP 404 with no Location header, staff link or form-recovery bridge. See the [Dokploy domain guide](https://docs.dokploy.com/docs/core/docker-compose/domains) and the [independent entry decision](../adr/0001-independent-public-and-staff-entry.md).
5. Verify registration/own staged previews/review/confirmation, token correction/consent, staff login/recovery, roles, document downloads, exports, carousel publication and settings against the shared data. Test invalid/expired/reset links, CSRF and wrong-app/private/static route rejection. Do not remove the former administration endpoint until this passes.
6. After production cutover approval, redeploy the reviewed Compose configuration so Traefik picks up the new domain labels. Keep any replaced `public`, `staff` or `app` containers stopped so they cannot handle traffic or writes. If removing obsolete containers, first verify their Compose project and service labels and remove only those containers, with their volumes retained. Monitor both applications and the queue.
7. Leave synchronous mail and the disabled `jobs` profile unchanged until the scheduler transition is approved. Disable the previous scheduler/cron owner **before** enabling the new `jobs` profile. Both web images omit scripts; only the jobs image owns shared scheduled work and locks. Mail runs each minute, abandoned staging hourly, and trash daily only with a verified current backup plus `AMEA_TRASH_BACKUP_CONFIRMED=1`. Do not run two schedulers. Switch the web services to `MAIL_ASYNC=1` only when the designated worker is ready.

## Rollback procedure

If the new jobs owner is running, stop it first. Restore `MAIL_ASYNC=0` for synchronous delivery and account for any already queued messages. Keep `db`, its volume and the shared application volumes intact. Stop the replacement HTTP services. Restore the recorded previous app images, Compose configuration, origin variables and Dokploy domain mappings in the same project with the same volumes. Redeploy with additive migrations 06 and 07 in place. Do not delete data, narrow enum values or restore an older database over new submissions as an automatic rollback step.

Keep the per-container `tmpfs` mount at `/opt/amea/storage/cache` for `web`, `backoffice` and `jobs`, including retained images. Twig regenerates compiled templates from each image on first use. Exclude `storage/cache` from backups and restores to avoid reusing compiled templates from another image. Uploads, mail queue, job locks and other persistent storage remain shared.

Recheck authorized private documents, public registration and staff login. Re-enable exactly one previously recorded scheduler only after the new owner is stopped. If data restoration is required, stop writes and separately approve the selected backup and recovery point; that is a data recovery operation, not an image rollback.

The historical local test retained the real pre-08 public image from `ff3a8abac51eda2bec3e115b603c7e788de472f8`, observed its old HTTP404 staff-link behavior, then restored that candidate's HTTP302 behavior while DB/private/public file hashes remained identical. The 2026-10-28 candidate deliberately restores HTTP404 isolation after the same rollback/forward and separate restore rehearsal; its current behavior supersedes the earlier redirect expectation. This does not qualify an arbitrary historical image or the actual production cutover/restore; repeat the rehearsal with the exact production image and dataset before approval. A rollback to an image containing the former bridge would reintroduce it and is incompatible with the new isolation requirement unless separately approved.
