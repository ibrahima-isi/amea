# Portable object storage and production migration

Design approved by the user on 2026-11-12 against local `dev` at `eaca561`. An independent builder review found no important unanswered planning questions. The confirmed scope and choices are recorded in the [discussion](interview.md).

## Outcome and selected approach

AMEA will store its uploaded files in a private Cloudflare R2 bucket through the S3-compatible API. The production migration must preserve file contents and application access across an application that has been operating for over a year. Connection configuration and provider-independent object keys will support subsequent moves to Hetzner Object Storage, AWS S3 or another compatible provider.

The user selected current relative paths as object keys. For example, `uploads/students/cvs/example.pdf` identifies the destination object corresponding to that exact source path. Public, staff and jobs select the same backend and bucket through configuration. The database fields continue to identify the corresponding files through these keys. Inventory determines which legacy references require correction.

The alternative was a file catalogue with new identifiers and relationships in the database. Using current paths limits reference transformations during this migration. See [ADR 0003](../../docs/adr/0003-use-portable-object-storage.md).

All existing upload flows are included. Storage streams will also accommodate future video and attachment content. User-facing upload policies and screens for these additional types belong to a subsequent feature.

## Application integration

The following details are engineering proposals for review.

Provide one shared storage boundary for local and S3 backends. It covers streaming writes and reads, metadata/existence lookup, deletion, paginated prefix listing, and staged-file finalization. Backend implementations must report failures distinctly from missing objects. Keep application authorization and per-flow upload validation in their existing application layers.

Use an established S3 client behind that boundary. Library selection and class names are implementation choices. Configuration includes backend, endpoint, region, bucket, credentials and addressing style as required by the selected service. R2 qualification uses the S3 endpoint and `auto` region. Secrets follow the existing untracked environment configuration. Credentials grant access to the selected bucket.

Durable references use validated relative keys within the managed upload namespace. New keys are collision-resistant. Storage reads and migration comparisons use complete keys, including directories. Resolve legacy normalization issues through an explicit inventory and mapping. Preserve a mapping from the original reference to every corrected key. Ambiguous matches require resolution before cutover.

Update all consumers together.

| Consumer | Required integration |
| --- | --- |
| `FileUploader` and procedural wrappers | Upload validation, storage writes, staging/finalization and deletion |
| Registration | Session-owned previews, review, editing and confirmation |
| Staff editing and public correction | Existing-file retention and replacement consistency |
| Downloads | Authorization, MIME policy, filenames, inline/attachment behavior and private headers |
| Slider | Storage writes and public/staff image delivery |
| Journal | Cover validation, draft previews, publication access and retained historical covers |
| Reconciliation tools | Backend existence/listing and complete-key comparison |
| Pending and trash jobs | Storage-aware retention, retry behavior and backup prerequisites |
| Compose and operations | Coordinated backend settings for public, staff and jobs, recovery and backups |

## Access and media delivery

The user selected private storage with PHP-mediated KYC/CV downloads. Retain existing permission checks and endpoint parameters. Stream objects with the existing MIME restrictions, personalized download filenames, `nosniff` and private/no-store behavior. Validate content at ingestion and bind stored metadata to the verified object. Legacy-media qualification must exercise the existing content checks. Keep buffering bounded for large streams.

The engineering proposal extends application-mediated reads to journal and slider media within the same private bucket. Journal access checks the current publication state on each request. Draft and withdrawn covers remain available only through their authorized preview flows. Slider delivery resolves the active slider record and preserves existing public `/uploads/slider/<filename>` URLs through application routing. Adapt the Apache local-file alias and routing coherently for the two applications.

Existing registration sessions continue to refer to their staged keys. Preserve session volumes, cookie scope and session data during deployment. Migrate pending files as part of the complete inventory, retain the session ownership checks, and record original staging age for cleanup decisions. Copy time must not reset the staging lifetime or expire a valid pending file early. Rehearse a registration opened before the pause and confirmed afterward.

## Write consistency and deletion

Stage writes under unique keys. Verify that storage has accepted the complete content before committing a database reference. A storage failure must preserve the previous valid document and expose a retryable application error. A database failure leaves the old reference valid and records or retains the unreferenced new object for reconciliation.

For finalization, a remote copy and source deletion span separate operations. Keep staged source data until the final object is verified and its database reference commits. A retry must recover a partially completed finalization. Treat a registration's documents as one coordinated save so a partial copy failure cannot persist an incomplete submission.

Replacement publishes the verified new reference before any eligible old-file cleanup. Preserve the current journal policy of retaining historical covers. Preserve soft-deleted member documents for restoration. For permanent purge, a failed deletion must remain recoverable with its keys still recorded. Retry must distinguish an already absent object from a transport or permission failure. Choose the durable retry mechanism during implementation and demonstrate recovery after interruption at each boundary.

Cleanup jobs use original lifecycle timestamps. One scheduler owns these jobs. All file-mutating HTTP requests, CLI tools, purges and external scheduled invocations participate in the cutover write pause. Storage outages produce explicit failures against the selected backend.

## Inventory, copy and verification

1. Inventory the actual production deployment, selected database, upload roots/volumes, session persistence, all writers and existing backup procedure. Determine byte volume, object count and file-size distribution. Establish a restorable database-and-files backup in an isolated recovery environment.
2. Recursively inventory the entire uploads tree. Include retained unreferenced files, trashed-member documents, journal drafts and historical covers, pending registrations and unknown subdirectories. Record nonregular files and symlinks for explicit handling. Inventory every persistent database reference and reconcile aliases, invalid paths, missing sources and collisions. Existing missing content requires recovery or an explicit recorded disposition before cutover approval.
3. Build a private, durable migration manifest containing original path, destination key, size, SHA-256, original timestamps, reference mappings, copy state and verification state. Protect its filenames and identifiers as private operational evidence.
4. Copy into the selected private R2 bucket while retaining source files. The copy is resumable and avoids overwriting a different object without a recorded, verified mapping. Application reads and writes still use the active source backend during this initial phase.
5. Read destination content back and compare SHA-256 and size against the source inventory. Metadata, object counts and ETags alone are insufficient proof of content equality. Track source changes during online copy as candidates for final reconciliation.
6. Rehearse against an isolated restored dataset and the S3 backend, including an actual R2 test bucket. Measure copy, verification, final write-pause and rollback durations. Local S3-compatible tests provide repeatable failure cases. R2 tests establish compatibility with the selected provider.

## Cutover

1. Announce the measured maintenance window. Stop new mutations, drain in-flight mutations and suspend every file-mutating job. Verify the pause across public, staff and CLI entry points. Quiesce other database writers while capturing a consistent backup. Retain session data.
2. Capture the final source inventory and coordinated database backup. Reconcile added, changed and deleted paths against the online manifest. Verify all current source files at R2, recopying and hashing changed content. Retain historical copies and record their state. Confirm every database reference and pending-session key resolves correctly. Lifecycle decisions remain based on their original timestamps.
3. Apply reviewed reference corrections, if inventory requires them, with before/after evidence and repeat-safe guards. Use transactional data changes and the repository migration lock. Any schema changes follow the existing separate DDL and backup/recovery process. The resulting database and final object manifest form the cutover recovery point.
4. Switch public, staff and jobs together to R2 while mutations remain paused. Verify every referenced key against the final manifest and run application smoke checks for downloads, public images, journal privacy and a pre-existing registration preview. Exercise writes in the rehearsal. Approved production write checks must be accounted for in the recovery evidence.
5. Resume mutations only after the cutover checks succeed. Resume one scheduler after confirming the applicable backup prerequisite. Record the application revision, backend configuration identity, backup identity, manifest, checks and reopening time. Obtain explicit production approval before executing the live transition.

## Recovery and backups

The source copy and associated backup remain retained until migration validation. Their later removal is a separate decision accepted by the user.

Before reopening writes, a failed cutover can return to the verified local recovery point with the matching database references and session data. Rehearse this path.

After reopening, R2 may contain files created or replaced since cutover. A return to local storage must pause and drain writers again, reverse-copy the current required objects, verify their hashes, and reconcile them against the current database and sessions. Preserve post-cutover submissions and corrected references. Resume only after local reads and complete reference checks pass. If R2 is unavailable and unique new objects cannot be recovered from a backup, report the recovery limitation and keep the affected writes paused until the selected recovery point is explicitly resolved.

Extend the existing coordinated backup procedure to include object contents, the corresponding database, original lifecycle metadata and session recovery requirements. Rehearse restoration from that backup into an isolated environment. The local source snapshot represents the cutover recovery point. Record backup cadence and achievable recovery points for post-cutover writes in the operational handoff. Exact production backup volume, available capacity and cadence require live inventory and operator confirmation before deployment.

## Acceptance and delivery evidence

- Every regular source file in the final inventory has a destination key with verified size and content digest. Exceptions and pre-existing missing references have an explicit disposition before approval.
- Every database reference and staged-session reference resolves through the selected backend. Files with identical basenames in separate directories remain distinct.
- Tests cover both backends, provider settings, paginated listing, partial-copy retries, replacement failure, database-save failure, cleanup retry and original pending-file age.
- Interrupted migration runs resume safely. Injected source changes between initial and final copy reconcile correctly. Provider failures cannot produce a successful save with an unreadable reference.
- HTTP/browser journeys cover registration through confirmation, correction, staff KYC/CV access, role denials, slider images, published/draft/withdrawn journal covers, soft-delete/restore and purge failures.
- Existing PHP syntax checks, regression scripts, PHPUnit suites and applicable deployment/browser checks pass. R2 qualification records the exact code revision and target test bucket identity with secrets redacted.
- Rehearsals establish complete-copy verification, measured interruption, pre-reopening rollback, post-reopening rollback with newly created files and restorable coordinated backups.
- Execution evidence distinguishes repository implementation, tests/reviews, latest-commit CI, merge, R2 qualification and actual approved production migration. Keep it in this feature's `completion.md` when implementation begins.

## Builder choices and facts to establish

The storage boundary shape, application-mediated public media, unique staged keys and coordinated-save details above are engineering proposals. The builder selects class names, S3 client, test harness, manifest format and durable retry mechanism while satisfying the stated contracts. Any choice affecting access, file retention or recovery behavior must be reflected in the design review.

Live file inventory, existing missing content, deployment topology, R2 credentials and bucket, backup restorability, backup cadence and measured interruption remain to be established. The current inspection covers repository code and documentation.

## Primary references

- [Cloudflare R2 S3 API compatibility](https://developers.cloudflare.com/r2/api/s3/api/) documents supported operations and provider differences.
- [R2 object keys](https://developers.cloudflare.com/r2/objects/) documents keys and directory prefixes.
- [Doctrine migration recovery](../../docs/operations/migration-recovery.md) defines database backup, transaction and interruption requirements.
- [Existing rollout and recovery](../../docs/operations/public-staff-rollout.md) describes the shared deployment and backup topology.
