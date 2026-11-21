# Reconcile the OOP delivery with Namecheap Stellar

Status: documentation reconciled against merged dev. Native hosting implementation and Stellar account qualification remain pending.

## Approved direction and source revisions

Production is to return from the Dokploy VPS to Namecheap Stellar. Keep the tracked Compose files, two independent web roots with isolated sessions, shared private PHP code, one member database, and one private production R2 bucket. Publish tested main revisions through GitHub Actions, with Secrets supplying private deployment and runtime configuration.

The approved hosting plan and CLI preflight are versioned at local signed commit 5a369164da80cc34fc5dac40d335bbc01b408ed0, under .scratch/namecheap-stellar-return/. They were written against dev at d767113, before PR #39 merged as 1534565 on November 21, 2026. This reconciliation applies that plan to the merged application. Account measurements and actual production configuration remain operational evidence to collect.

PRs #44 and #45 delivered portable storage, SHA-256 verification, coordinated backup/restore and legacy single-app topology support. R2 qualification used synthetic objects in amea-sandbox. The production bucket is selected separately. Production R2 transition and the complete legacy-to-Stellar rehearsal with an in-progress registration remain pending.

The earlier OOP spec selected Compose as its supported web runtime. The subsequently approved Stellar return extends that hosting scope. Keep the delivered OOP design and add a native host adapter. The ADRs for independent entry points, Doctrine, portable object storage and one Composer package remain the design constraints.

## Changes from the pre-OOP hosting plan

| Earlier assumption | Merged source at 1534565 | Effect on Stellar work |
| --- | --- | --- |
| Entry and routes live in docker/apps/index.php and docker/apps/routes.php. | Those files are removed. Entries are apps/web/public/index.php and apps/backoffice/public/index.php. src/Core/Router.php owns app, method and session selection. | Reuse those entries and Router. Native hosting supplies rewrite and runtime setup. |
| Web identity comes from a Docker image marker. | Each front controller passes a literal public or staff identity. | Web route isolation works without a marker. Storage target reports still read one. |
| PHP selects the Docker session path in application code. | Session selects host-only AMEA_PUBLIC and AMEA_STAFF cookies. docker/apps/apache.conf selects /var/lib/amea/sessions. | Configure distinct canonical private session directories in both web PHP runtimes and the operator tools. |
| A combined image can be built from the current root Dockerfile. | PR #39 removes the root Dockerfile, root PHP shims and legacy bootstrap. | Retain the pre-OOP revision/image for source-side single-app rehearsal. Build the destination from the merged split layout. |
| Private runtime directories follow Docker mount conventions. | Queues, shared job lock, Twig cache and several log/storage paths still derive from the checkout root. | Select stable private runtime paths for versioned native releases and qualify every caller. |
| Actions can publish the native layout. | The workflow contains validation jobs only. | Implement and rehearse packaging and serialized publication in the existing workflow. |

Public .php URLs are virtual routes. Recreating physical shims or the removed combined bootstrap would bypass the intended entry design.

## Required native adaptations

### Two document roots and one private release

Use one private Composer release, with one allowlisted public entry and static asset tree per hostname. Each current entry resolves Composer by walking three directories up. Either retain that relative layout through permitted cPanel roots or introduce an explicit private release locator in the host adapter. Verify primary-domain root restrictions, addon/subdomain settings, symlink policy and open_basedir before selecting the promotion mechanism.

Keep source, vendor, templates, scripts, database material, private configuration, recovery evidence, spools, sessions, queues and logs outside both roots. Both roots must select the same immutable release. Account for an interrupted promotion between the two roots and recover a consistent pair before reopening writers.

Implement LiteSpeed rewrite rules equivalent to the shipped Apache configuration. Serve only approved asset files directly. Route all other URLs through the fixed front controller, including /uploads/slider/... even if a local file exists. Journal covers, staged previews and private downloads stay on their handlers. Preserve the /index.php alias, staff-root redirect, wrong-app 404 before method/session handling, 405 Allow headers and session-free media reads. Qualify TLS, inherited PHP handlers, settings and headers on both roots.

### Private configuration and durable runtime

Environment::load() reads the release-root .env into $_ENV. Web PDO reads $_ENV only. Storage accepts $_ENV before process variables. Doctrine and migration/recovery commands read process variables, while scheduled job classes load .env. A single uploaded secret file alone does not configure every caller.

Provide one private configuration source with explicit web and CLI loading. Verify origins, role-specific APP_URL, database selection and identical R2 destination through both web entries and the real cron PHP binary. Preserve existing variable families and precedence. Web PDO omits DB_PORT, while Doctrine supports it. Confirm the assigned database connection settings and add port support only if required by that endpoint.

Configure distinct durable file-session paths, preserving handler and serializer. Select an app- and release-specific Twig cache. Both apps and jobs need the same durable FILE_WRITE_GATE_PATH, queue, job lock and lifecycle state. The gate already accepts an absolute configured path, but queues and the job lock remain checkout-relative. A release switch must preserve the paused gate and existing queue.

Qualify permissions, temporary disk spooling, locks, atomic rename and file/directory synchronization on the account. LocalStorage additionally rejects symlinked object paths and uses hard-link publication. An uploads symlink alone does not qualify local fallback or source recovery.

### Storage attestation and recovery

The OOP merge changes no Storage method, R2/S3 configuration family, manifest or snapshot format, or migration/backup command path. Compared with d767113, the only diff inside src/Storage is Cutover::codeIdentity(), which hashes PHP in src and config after removal of functions. The new command classes contribute to that identity. Regenerate target reports against the deployed merged code.

scripts/storage-target.php still reads /opt/amea-apps/identity, assumes Docker session paths for split roles, and requires a completed persistent pause. It cannot attest a native Stellar target as shipped. Add native reports bound to actual public, staff and jobs configuration, code, database, backend, gate and effective session stores. Compare web runtime evidence with CLI evidence. Keep report roles public, staff and jobs even when hosting labels are web and backoffice.

Cutover::codeIdentity() excludes front controllers and CLI launchers. Bind their bytes and static/runtime packaging separately to the release artifact receipt. Verify retained reports and recovery evidence against their recorded code identity.

Migration and backup support either one app session store or distinct public/staff stores. They do not convert between topologies. Recovery also binds snapshots to database, backend, code and session identities. A new cPanel database endpoint, relocated local roots and new session stores need an explicit transfer and verification procedure that preserves original source evidence.

Prove which pending-member state can enter the public store and how the browser retains access when its cookie name changes. Keep legacy staff authentication out of the public store. Rehearse pending registration, private previews, restore and confirmation before choosing the production sequence.

### Jobs and automatic main publication

Retain the scripts/*.php interfaces and exit behavior. Reuse class-owned mail and cleanup commands, one job lock and the write gate. RunSharedJobsCommand requires proc_open. Child commands need the same private configuration and durable paths. Replace the Docker scheduler loop with account-permitted cron invocations. Availability checks alone do not prove concurrent locking or a complete cron run.

Namecheap documents a minimum five-minute interval and at most five simultaneous cron jobs. Qualify the assigned limits and selected mail mode, then measure reset-email arrival against the five-minute token lifetime. Preserve the purge backup acknowledgement and one active scheduler during transfer. [Cron restrictions](https://www.namecheap.com/support/knowledgebase/article.aspx/9453/29/how-to-run-scripts-via-cron-jobs/)

Add publication in .github/workflows/ci.yml after native implementation and staging qualification. Build locked production dependencies, verify platform requirements, record the exact artifact, transfer over SSH with a pinned host key, and generate private configuration atomically from Secrets. PR/dev runs remain validation. Tested main artifacts publish automatically.

Serialize publication and prevent cancellation of live promotion/migration. The workflow-wide cancel-in-progress=true needs adjustment for a production stage. Job-only concurrency does not prevent cancellation of the parent workflow. Interlock deployment with the storage cutover. Run enrollment/migration before promoting entries and prove failure blocks promotion. Retain a compatible release and current-state recovery evidence for rollback after new writes.

## Dependency order and acceptance evidence

1. **Qualify Stellar and source inventory.** Run the existing private CLI preflight with the intended cron binary. Check both web PHP runtimes, SSH, allowed roots, database restore, filesystem operations, R2 sandbox round-trip, Brevo delivery, limits and release promotion. Identify the actual VPS image/configuration, writers and session store. Account qualification remains pending.
2. **Implement native packaging and configuration.** Reuse the merged entries, add LiteSpeed rules and durable runtime selection, then exercise normal HTTP and CLI interfaces on staging with synthetic data. Retain Compose support. This gates native reports.
3. **Implement native reporting and topology transfer.** Bind web/jobs to code/configuration, sessions and the shared gate. Rehearse source single-app capture, migration, isolated DB restore and pending-registration continuity into the split destination, including interrupted transfer and recovery. This gates a single-window hosting/R2 switch.
4. **Prove main publication.** Implement Secrets/SSH publication in the single workflow. Test validation/migration failure, concurrent pushes, cutover interlock, rollback and successful paired-root promotion on staging.
5. **Select and measure production resources.** Configure the private production bucket, finish inventory/SHA-256 checks and an isolated restore, measure the pause, then present resources and rollback triggers for approval. If a combined move is unqualified, evaluate storage-first Dokploy using its retained exact legacy image and single-app session procedure.
6. **Execute the approved move and observe.** Pause/drain source writers, reconcile objects/intents, capture/import the final database, verify the target, switch only the relevant web DNS records and keep former writers paused during propagation. Reopen the verified target and record upload/download, mail, cron and recovery results. VPS retirement and local source deletion have separate retention decisions.

Native stages are planned work. Completed OOP tickets remain split-application delivery evidence. The object-storage operational ticket remains pending until actual transition verification.

## Account preflight limits

Namecheap publishes LiteSpeed, MariaDB 11.4 and PHP through 8.5 for shared hosting. Per-domain PHP selection can need separate extension configuration. These facts support qualification and do not establish the assigned account's capabilities. [Shared software](https://www.namecheap.com/support/knowledgebase/article.aspx/129/22/what-version-of-the-software-is-used-on-your-servers/), [per-domain PHP](https://www.namecheap.com/support/knowledgebase/article.aspx/9417/2219/how-to-change-php-version-and-update-php-extensions-on-shared-servers/)

The preflight at 5a36916 probes CLI PHP/extensions, flock/proc_open, anonymous R2 TLS and Brevo TCP. Preserve it as the initial probe. Actual web settings, authenticated synthetic R2 access, database restore, test mail, release promotion and interrupted recovery need additional evidence.
