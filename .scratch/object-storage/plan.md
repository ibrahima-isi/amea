# Portable Object Storage Implementation Plan

> **For agentic workers:** Use `superpowers:subagent-driven-development` or `superpowers:executing-plans` to implement this plan task by task once execution is authorized. Steps use checkboxes for tracking.

**Goal:** Replace uploaded-file storage with private R2 storage and migrate existing production content with complete-copy verification and recoverable cutover.

**Architecture:** Local and S3 backends share one application storage interface. Current relative paths identify object keys. PHP mediates media reads, while manifests coordinate resumable copying, verification and recovery.

**Tech Stack:** PHP, PDO, PHPUnit 11, Twig, the existing Compose applications and the S3 API through a supported PHP client.

**Spec:** [Approved storage design](spec.md).

## Global constraints

- All existing upload flows are included.
- The user selected current relative paths as object keys.
- Public, staff and jobs select the same backend and bucket through configuration.
- The user selected private storage with PHP-mediated KYC/CV downloads.
- The source copy and associated backup remain retained until migration validation.
- Obtain explicit production approval before executing the live transition.
- Preserve French UI content, existing public/staff boundaries, MIME limits, CSRF and session ownership checks.
- Perform implementation and tests in isolated worktrees. Sign every commit, including integration merges, and target ordinary PRs at `dev`.
- Every ticket verifies its blockers through implementation evidence. Readiness alone grants no execution permission.

## Delivery sequence and file responsibilities

| Ticket | Deliverable | Dependencies |
| --- | --- | --- |
| [01](issues/01-storage-backends.md) | Local/S3 contract, backend configuration and contract tests | None |
| [02](issues/02-member-documents.md) | Consistent member upload, staging, download and deletion flows | 01 |
| [03](issues/03-media-and-jobs.md) | Journal/slider reads, reconciliation and storage-aware jobs | 02 |
| [04](issues/04-migration-tooling.md) | Inventory, manifests, resumable verified copy and recovery CLI | 03 |
| [05](issues/05-rehearsal-and-r2.md) | Coordinated write gate, backups, R2 and rollback qualification | 04 |
| [06](issues/06-production-transition.md) | Approved live inventory, recovery qualification and production cutover | 05 and explicit production approval |

Suggested new implementation files are listed below. Recheck their names against the selected implementation base before editing. The approved design permits class and library choices during implementation.

- `src/Storage/Storage.php`, `LocalStorage.php`, `S3Storage.php`, `StorageFactory.php` own backend operations and configuration.
- `src/Storage/StorageException.php` distinguishes a failed storage request from an absent object.
- `src/Service/FileLifecycleService.php` coordinates file finalization and durable cleanup.
- `src/Repository/FileOperationRepository.php` stores retryable deletion intents.
- `src/Migration/Version/Version20261112000000.php` adds that auxiliary operation ledger, subject to a fresh version-number check. Keep `schema.sql` aligned.
- `src/Controller/SliderMediaController.php` serves active slider images through existing public paths.
- `src/Storage/Migration/Inventory.php`, `Manifest.php`, `Copier.php`, `ReferenceAudit.php` own migration discovery, durable evidence, streaming verification and DB reference comparison.
- `src/Core/FileWriteGate.php` provides shared admission/draining for all file mutations.
- `scripts/storage-migrate.php` exposes migration operations through the shared services.
- `docs/operations/object-storage.md` owns operator procedures, backup/restore and provider configuration.

The ledger above records unfinished operations. It is an implementation choice for reliable retry and complements the selected path-key references. Its rows contain the operation identity, backend identity, affected keys, entity identity, expected reference, state, attempts and last error. Production data values remain private operational evidence.

## Task 1 — Storage contract and backends

**Files**

Create the five storage classes and exception above. Modify `composer.json`, `composer.lock`, `src/Core/Container.php`, `compose.yml` and `compose.dokploy.yml`. Create `tests/Unit/Storage/StorageContractTest.php`, `tests/Integration/Storage/S3StorageTest.php` and a task-owned disposable S3 test Compose configuration under `tests/storage/`. Add operational configuration documentation to `docs/operations/object-storage.md`.

**Interfaces**

Consumes the existing project root and untracked environment configuration. Produces this application contract. Streams are resources and are validated at runtime because PHP has no resource parameter type.

```php
interface Storage
{
    /** @return resource */
    public function read(string $key): mixed;
    /** @param resource $source
     * @return array{size: int, sha256: string} */
    public function write(string $key, mixed $source, string $mime, int $createdAt): array;
    /** @return array{size: int, mime: string, createdAt: int, sha256: ?string}|null */
    public function stat(string $key): ?array;
    public function delete(string $key): void;
    public function copy(string $sourceKey, string $destinationKey): void;
    /** @return array{objects: list<array{key: string, size: int, createdAt: int}>, cursor: ?string} */
    public function listPage(string $prefix, ?string $cursor, int $limit): array;
}
```

`StorageFactory::fromEnvironment(string $projectRoot): Storage` selects the active backend. `Container::storage(): Storage` caches it, and `Container::reset()` clears both PDO and storage. Add `Container::setStorage(Storage $storage): void` for fixture injection. `STORAGE_DRIVER=local|s3`, `S3_ENDPOINT`, `S3_REGION`, `S3_BUCKET`, `S3_ACCESS_KEY_ID`, `S3_SECRET_ACCESS_KEY` and `S3_PATH_STYLE` supply provider configuration. R2 uses `S3_REGION=auto`. Selected S3 mode with invalid configuration fails explicitly.

**Steps**

- [ ] Add a parameterized contract test using a temporary local root and a disposable S3 bucket. Include the test below for each backend, plus traversal/symlink rejection locally, key validation, double deletion, full pagination, empty streams, streams exceeding the read buffer, duplicate destination protection, unknown-key reads, original-age retention and injected access/transport failures. `read()` throws on a missing object, `stat()` returns null only on a confirmed missing object, and repeated deletion of a missing key succeeds.

```php
$key = 'uploads/students/cvs/example.pdf';
$input = fopen('php://temp', 'w+b');
fwrite($input, "%PDF-1.7\nexample\n");
rewind($input);
$written = $storage->write($key, $input, 'application/pdf', 1762862400);
$output = $storage->read($key);
self::assertSame($written['sha256'], hash('sha256', stream_get_contents($output)));
self::assertSame(1762862400, $storage->stat($key)['createdAt']);
```

- [ ] Run `./vendor/bin/phpunit --filter StorageContractTest` and the S3 test configuration against the unimplemented contract. Record the failing behavior.
- [ ] Run `composer require 'aws/aws-sdk-php:^3'` in the implementation worktree and commit the resolved lockfile with the code. This is the planning choice of client, supported by [Cloudflare's PHP example](https://developers.cloudflare.com/r2/examples/aws/aws-sdk-php/) and [AWS installation guidance](https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/getting-started_installation.html). Verify the selected production PHP version satisfies the resolved dependency requirements. Implement the storage interface using `Aws\S3\S3Client`. Generate unique keys at callers and refuse conflicting destination writes. Metadata records the original creation time and SHA-256 of accepted content. `copy()` is retry-safe when destination content matches, and reports conflicting content. Local path checks constrain operations to the uploads root, including directory-separator and symlink boundaries. Paginated listing preserves full relative keys.
- [ ] Run both backend suites and exercise a second provider configuration using an independent test endpoint. Verify client behavior against the R2 API compatibility matrix, especially checksums, endpoint addressing and copy metadata. Explain the actual client selection in the evidence.
- [ ] Validate Compose configuration with explicit test environment values. Run relevant existing regression checks, review the changes and create a signed commit using `git commit -S -m 'feat: add portable local and S3 file storage'`.

## Task 2 — Member documents and retryable lifecycle

**Files**

Create `src/Service/FileLifecycleService.php`, `src/Repository/FileOperationRepository.php`, the checked next Doctrine schema version, `tests/Integration/Service/FileLifecycleServiceTest.php` and `tests/Integration/Controller/MemberStorageTest.php`. Modify `schema.sql`, `src/Core/FileUploader.php`, `src/Service/StudentService.php`, `src/Controller/RegistrationController.php`, `DownloadController.php`, `StudentController.php`, `CorrectionController.php`, `TrashController.php` and `functions/utility-functions.php`. Extend `tests/http/registration-browser.mjs` and the existing registration fixture/harness where appropriate.

**Interfaces**

Consumes `Storage` and `Container::storage()` from Task 1. Keep the existing `FileUploader::handle()`, `safeDelete()` and `finalize()` signatures available to callers. New lifecycle service methods are `prepareFinalization(array $stagedToFinal): array`, `scheduleDeletion(string $operationId, array $keys, string $reason): void` and `retryDeletions(int $limit): int`. Map staged source keys to final destination keys. Return the verified mapping and retain each source until DB commit. `scheduleDeletion()` participates in the caller's PDO transaction. `retryDeletions()` records individual failures and is safe to repeat.

**Steps**

- [ ] Add failure-injected tests around the actual registration and editing entry points. Reproduce a second-document copy failure, a DB save failure after successful copies, two concurrent replacements, interruption between DB commit and cleanup, repeated finalization, a failed purge and a valid pre-existing staged session. The test below states the required save-failure behavior using a real fixture student and the backend's failure injector.

```php
$before = $repo->findById($studentId)->getCvPath();
$faults->failNextWrite();
$response = $http->replaceCv($studentId, $validPdfFixture);
self::assertGreaterThanOrEqual(400, $response->status());
self::assertSame($before, $repo->findById($studentId)->getCvPath());
self::assertNotNull($storage->stat($before));
```

The HTTP driver and fault injector in this example belong to the new test fixture, exposing exactly `replaceCv(int $studentId, string $fixturePath)` and `failNextWrite()`. The driver follows the actual authenticated/CSRF-protected form flow.

- [ ] Run the targeted PHPUnit tests and registration browser case to capture the failures before changing callers.
- [ ] Implement stream-backed upload/read behavior while retaining existing per-flow MIME/size rules. Stage verified remote copies before the DB transaction. Within the transaction, lock the relevant existing row, apply all dossier changes and enqueue eligible source/old-file deletion intents. A rolled-back transaction retains the original references and files. Existing session serialization and owner checks still select staged keys.
- [ ] Make hard purge retryable. A durable deletion intent retains all keys and the target identity until every required object deletion succeeds. Serialize purge against editing/restoration and recheck trash/retention eligibility before starting irreversible work. Retrying missing keys succeeds, while permission/transport failures retain retry state. Remove the target row only after required deletion is complete. Soft deletion retains files for restoration.
- [ ] Stream authorized downloads/previews with existing filenames, MIME checks and response headers. Run the PHP regression set and actual HTTP/browser registration, correction, private-download and failure-retry flows on both backends.
- [ ] Rehearse the new schema version and interruption behavior on disposable MariaDB according to `docs/operations/migration-recovery.md`. Review and sign `feat: preserve member documents across remote storage failures`.

## Task 3 — Public media, reconciliation and scheduled jobs

**Files**

Create `src/Controller/SliderMediaController.php` and `tests/Integration/Controller/MediaStorageTest.php`. Modify `JournalMediaService.php`, `JournalService.php`, `JournalAdminController.php`, `JournalPublicController.php`, `SliderController.php`, `SliderRepository.php`, `DocumentReconcileService.php`, `src/bootstrap.php`, `docker/apps/apache.conf`, `scripts/purge-pending-uploads.php`, `purge-trashed-students.php`, `run-shared-jobs.php` and `docker/apps/jobs.sh`. Extend `tests/apps/journal.py`, `tests/apps/jobs.php`, `tests/test_document_reconcile.php` and `tests/apps/routes.py` for the backend-independent cases.

**Interfaces**

Consumes `Storage`, lifecycle methods and the operation ledger. `SliderMediaController::serve(): void` resolves only the requested active slider record by its validated filename. Keep the old `/uploads/slider/<filename>` URLs working in both applications. Journal serving keeps its slug/id request contracts. Preserve compatibility for the existing `JournalMediaService::store()` return keys while replacing its absolute-path read coupling with storage streams.

**Steps**

- [ ] Add regression cases for a draft cover, a published cover withdrawn after a successful read, old slider URLs, equal basenames under different directories and pending-file expiry copied to S3. Include this externally visible journal case in the actual HTTP driver.

```python
published = journal.publish_with_cover()
assert public.get(published.cover_url).status_code == 200
journal.withdraw(published.id)
assert public.get(published.cover_url).status_code == 404
assert staff.preview_cover(published.id).status_code == 200
```

Use the existing `tests/apps/journal.py` fixture to perform publish/withdraw through CSRF-protected staff forms and GET the resulting endpoints. Retain earlier cover objects after replacement/removal.

- [ ] Run the new media and reconciliation cases and record their failures on the previous implementation.
- [ ] Serve journal and slider streams from the selected backend. Route slider paths through the application, validate against active DB records, enforce supported image types, and keep staff/public route boundaries. Remove the Apache local-file bypass only as part of this working route replacement.
- [ ] Adapt reconciliation to paginated full-key listing and explicit missing/error distinctions. Adapt pending cleanup to original staging timestamps, preserve the existing 24-hour default and configurable retention, and use lifecycle retries for eligible deletion. Wire trash/deletion retry handling to the single designated jobs owner, retaining the backup acknowledgement requirement.
- [ ] Run both backend media cases, journal privacy/browser journeys, reconciliation, role/route checks and job failure/retry cases. Review and sign `feat: serve media and run cleanup through shared file storage`.

## Task 4 — Migration inventory, manifests and verified transfer

**Files**

Create the four `src/Storage/Migration/` classes mapped above, `scripts/storage-migrate.php`, `tests/Integration/Storage/MigrationTest.php` and `tests/storage/migration-fixture.php`. Extend `docs/operations/object-storage.md` with exact command behavior and exit codes.

**Interfaces**

Consumes explicit source/destination `Storage` instances, PDO and the operation ledger. The migration CLI exposes `inventory`, `audit-references`, `copy`, `verify` and `reverse-copy` subcommands. Every command requires an explicitly selected environment/database identity, source/destination backend identities and an absolute manifest path outside the web root. Read-only inventory is the default. Mutation subcommands require explicit destination selection and a reviewed manifest identity. The selected manifest format is versioned JSON Lines with an atomic checkpoint file and a single-writer lock.

Each file entry records `source_key`, `destination_key`, `size`, `sha256`, `created_at`, `source_mtime`, `copy_state` and `verify_state`. A separate reference section records table/record/column or private staged-session ownership, original value, resolved key and correction state. Preserve exact full keys. Reject conflicting duplicate mappings and distinguish an inaccessible source from a confirmed missing source.

**Steps**

- [ ] Add a migration fixture with two same-named files under different directories, a retained old journal cover, an unreferenced attachment, an old pending file, a DB reference to a missing file, a symlink and more objects than one listing page. Use synthetic PDF/image bytes and private temporary paths. Prove the fixture remains unchanged after read-only inventory.
- [ ] Run tests for inventory, interruption and source mutation before implementing the commands. Required transfer invariant follows.

```php
$copier->copy($manifest);
$destinationBytes = stream_get_contents($destination->read($key));
self::assertSame(hash('sha256', $sourceBytes), hash('sha256', $destinationBytes));
self::assertNotNull($source->stat($key));
```

`Copier::copy(Manifest $manifest): void` consumes the manifest's explicit source/destination instances provided at construction. `Copier::verify(Manifest $manifest): array` returns full-key differences and fails the CLI when any required object is unverified. `Inventory::scan(): Manifest` and `ReferenceAudit::audit(Manifest $manifest): array` create inventory and reference differences respectively.

- [ ] Implement full recursive source discovery and DB/staging reference audits. Use original bytes to compute SHA-256. Record special files, unknown directories and existing missing content for explicit disposition. Read session records through the existing PHP serialization/session configuration without exposing tokens or creating new sessions.
- [ ] Implement copy with durable checkpoints, streaming read-back verification and full-key conflict checks. On rerun, verify existing destination content before skipping it. Reconcile source changes between initial and final inventories. Retain objects removed from the active source in the migration evidence until explicitly classified.
- [ ] Implement guarded reference corrections as transactional data operations under the existing migration lock. Require reviewed before/after mappings, compare expected old values and abort on ambiguous matches. Data/schema changes follow the established Doctrine recovery procedure. Include their post-cutover values in reverse-copy audits.
- [ ] Test interruption after upload/before checkpoint, content mutation with equal size/mtime, denied reads, listing pagination failure, corrected references and reverse-copy after new submissions. Review and sign `feat: add resumable verified object storage migration tools`.

## Task 5 — Write pause, backup and R2 qualification

**Files**

Create `src/Core/FileWriteGate.php`, `tests/Integration/Storage/WriteGateTest.php`, `tests/storage/rehearse.php` and `tests/storage/r2.php`. Modify every HTTP/CLI file-mutating entry point identified in Tasks 2–3, `compose.dokploy.yml`, `compose.yml`, `tests/apps/compose.yaml`, `tests/apps/release.py`, `.github/workflows/ci.yml` and `docs/operations/object-storage.md`.

**Interfaces**

Consumes all prior tasks. `FileWriteGate::mutate(callable $operation): mixed` holds a shared advisory file lock over the complete logical mutation and rejects new operations if the durable paused marker exists. `pause` persists the marker before taking the exclusive lock to drain in-flight operations. `resume` requires the verified migration state and explicit operator invocation. Lock files and the marker live on the existing shared persistent storage, with a restrictive owner/mode. A deployment with writers that cannot share this gate must stop and drain those processes through the operator procedure.

The CLI gains `pause`, `final-check`, `resume` and `status`. Define documented exit codes with success as `0` and unresolved conflicts, active writers, failed verification or incorrect target identity as nonzero. Hold the exclusive lock while draining writers and during each final-check, correction or resume transition. The durable paused marker keeps admission closed across separate operator commands and application restarts. Record the completed drain and selected target identities in the migration state before permitting final checks. Resume verifies that public, staff and jobs select the verified destination and that final checks still match their manifest/configuration identity.

**Steps**

- [ ] Write a concurrency test that starts a blocked mutation, persists the pause, proves that a new mutation is rejected, waits for the first mutation to finish and confirms the final inventory includes its committed file/reference. Terminate the pause process and prove the durable marker still blocks a new write after restart.
- [ ] Run the concurrency and backup-restore tests to capture the failures.
- [ ] Implement admission/draining across registration, confirmation, correction, staff edits, journal writes, slider writes, permanent deletion and all jobs/CLIs. Gate complete transactions and session-file transitions, including retries. Quiesce other DB writers while taking a consistent backup. Reads remain available where application routing permits them.
- [ ] Extend backup/restore to private object bytes, DB data, operation ledger, lifecycle timestamps, session volumes and manifest/configuration identity. Coordinate a consistent snapshot with writers drained. The same process supports source and post-cutover R2 backups and reports its measured recovery point. Restore into isolated targets and verify all manifest hashes/references before exercising browser journeys.
- [ ] Run `tests/storage/rehearse.php` against the disposable S3 service through online copy, changed-source reconciliation, pause, final-check, backend switch and reopening. Create new files after reopening and perform a separately paused reverse-copy rollback against the current DB/session state. Verify the new files remain accessible. Inject outage during rollback and prove the gate stays paused when required bytes cannot be recovered.
- [ ] Run `tests/storage/r2.php` using a dedicated test bucket, scoped credentials and synthetic data after that environment is available and authorized. Verify read/write/copy/delete/listing, original-age metadata, stream digests, media headers and interrupted-transfer recovery against actual R2. Require an explicit test environment marker and test bucket identity, and reject production source configuration. Record exact revision and sanitized evidence.
- [ ] Integrate deterministic local S3 coverage into the single `.github/workflows/ci.yml`. Run the existing CI command set below plus the real browser journeys. Record R2 checks separately from CI if credentials are unavailable to CI. Review and sign `feat: qualify guarded R2 cutover and verified recovery`.

```sh
node --test tests/js/theme.test.mjs
php tests/test_permissions_unit.php
php tests/test_oop_unit.php
php tests/test_oop_integration.php
php tests/test_password_reset_security.php
./vendor/bin/phpunit --colors=never
python3 tests/http/baseline.py
python3 tests/http/tokens.py
python3 tests/http/internals.py
bash tests/apps/run.sh
bash tests/apps/doctrine-compose.sh
```

Use `tests/apps/run.sh` with browser checks enabled for storage UI qualification. Match the current CI environment when running its browser-disabled variant. Reproduce the PHP syntax/access checks from `.github/workflows/ci.yml` and lint all new PHP entry points. Select test Compose project names/ports explicitly and preserve the existing local stack.

## Task 6 — Approved production transition

**Files**

Maintain `docs/operations/object-storage.md` and this feature's `completion.md`. Put production manifests, DB exports, filenames and credentials in private operational storage. Link their sanitized identities from the evidence record.

**Consumes and produces**

Consumes the merged, qualified implementation, actual production inventory, verified backups, R2 configuration and explicit live-change approval. Produces a verified production transition with the retained source recovery point and an operator handoff.

**Steps**

- [ ] Verify Tasks 1–5 completion, merged revision, latest-commit CI, actual served application revision, production layout, all scheduled writers, backup capacity and session persistence. Record missing-reference recovery/disposition and the agreed ongoing backup cadence. Validate an isolated restoration of the selected source backup.
- [ ] Complete online copy and full read-back verification. Report the measured final pause and exact selected resources for production approval. The approved design and task readiness alone do not authorize a live mutation.
- [ ] With explicit approval, pause/drain writers, capture the coordinated backup, run final inventory/copy/hash/reference checks and apply reviewed corrections. Coordinate public/staff/jobs configuration together. Retain the source and recovery evidence.
- [ ] Verify production authorized reads, draft/privacy denials, old slider URLs and pre-existing registration staging while writes are paused. Account for approved synthetic write checks in evidence and recovery state. Resume writes only after the checks pass, then resume one eligible scheduler.
- [ ] Verify reopened registration/correction/uploads/downloads, object backup coverage and served runtime. Record actual outcome and any qualification limits. Demonstrate recovery with new post-cutover files in the prior isolated rehearsal, and retain rollback instructions with current references.
- [ ] Update the feature completion record with actual timestamps, manifest/backup identities, final counts/digests, application revision and validation. Decide source-copy removal separately after migration validation.

## Planning self-review

Every approved design section maps to a ticket above. Both backends and streams map to 01, document consistency and durable retries to 02, media/privacy/jobs to 03, full inventory/hash/mapping/reverse-copy to 04, coordination/backup/provider/browser checks to 05 and actual approved transition to 06. Steps include observable failure and success conditions. Interface names and return shapes are shared explicitly. The local issue files carry acceptance criteria and blockers independently of execution evidence.
