# Consolidate the deployment Docker Compose configuration

## Confirmed scope — 31 October 2026

The four choices below and the overall scope were confirmed through interactive input on 31 October 2026. The user authorized their application in the repository and opening a PR into `dev`. Execution is tracked in [completion.md](completion.md).

1. **One canonical file:** `compose.dokploy.yml` describes the public/staff stack. Remove `compose.apps.dokploy.yml` and update its references; Git retains the previous configuration.
2. **Data ownership preserved:** retain the `db` service with MariaDB 10.11, volume keys `amea_db`, `amea_uploads`, `amea_storage`, `amea_logs`, and the existing Compose data network. Replace the single application service with `public` and `staff`. Do not externalize the database or rename its volumes during this consolidation.
3. **Mail behavior preserved:** `MAIL_ASYNC=0` by default. The `jobs` service remains behind an optional profile, disabled by default; enabling it and designating a single scheduler will be a separate operation.
4. **Build from the repository:** Dokploy builds the `public`, `staff` and, if requested later, `jobs` targets from `docker/Dockerfile.apps`. The three `AMEA_*_IMAGE` variables do not become deployment prerequisites.

## Separation invariants

Preserve distinct public/staff origins, each application's allowed routes, separate cookies/session volumes and each container's own tmpfs Twig cache. Existing data volumes mount at the paths expected by the new images under `/opt/amea`. Staff and public temporarily share data and files in accordance with [ADR 0001](../../docs/adr/0001-independent-public-and-staff-entry.md).

## Approved scope

Modify `compose.dokploy.yml` and the `docs/operations/public-staff-rollout.md` guide, remove `compose.apps.dokploy.yml`, then repair affected maintained references. Keep local/test configurations, including `tests/apps/compose.yaml`: their role is distinct from deployment. The root monolithic Dockerfile remains outside this consolidation.

Validate Compose resolution with synthetic values, including the `jobs` profile, build targets, volume names/paths and session isolation. Check references and existing checks relevant to the scope, then follow the repository's signed-commit and PR workflow into `dev`.

No production start/reconfiguration, data migration, DNS change, real email delivery or change of ownership for a running resource is included. Before a future deployment, the actual Dokploy project/volume inventory remains necessary: retaining keys in the repository does not prove production resource identities.
