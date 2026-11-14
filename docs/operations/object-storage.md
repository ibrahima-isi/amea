# Portable object storage

The shared `Amea\Storage\Storage` boundary provides reads, conditional writes, metadata, idempotent deletion, retry-safe copies and paginated listing. `Container::storage()` caches the environment-selected backend. `Container::setStorage()` supports isolated fixtures and `Container::reset()` clears the storage and PDO references.

Ticket 01 supplies this foundation. Application integration, inventory and migration follow the feature's implementation tickets. Production cutover requires its own explicit approval and verified migration evidence.

## Configuration

Configure the same untracked environment for public, staff and jobs. Both canonical Compose files inherit these values from their shared environment block.

| Variable | Value |
| --- | --- |
| `STORAGE_DRIVER` | `local` by default, or `s3` |
| `S3_ENDPOINT` | Explicit HTTP(S) S3 endpoint |
| `S3_REGION` | Provider region, `auto` for Cloudflare R2 |
| `S3_BUCKET` | Existing private bucket |
| `S3_ACCESS_KEY_ID` | Bucket-scoped access key |
| `S3_SECRET_ACCESS_KEY` | Matching secret |
| `S3_PATH_STYLE` | `true`/`1` or `false`/`0`, defaults to `false` |

For R2, use `https://<account-id>.r2.cloudflarestorage.com`, region `auto`, the selected bucket and bucket-scoped credentials. Configure path-style addressing according to the provider. Local test endpoints use `true`. Environment loaded into `$_ENV` takes priority over process variables. Invalid selected S3 configuration raises `StorageException`.

Grant object read/write/delete and bucket list/existence-check permissions. The factory disables optional automatic SDK request/response checksum extensions with `when_required`. The implementation computes content SHA-256 from a disk-spooled input stream and stores it in object metadata. R2's supported operations and checksum features are documented in its compatibility matrix. [Cloudflare S3 API compatibility](https://developers.cloudflare.com/r2/api/s3/api/)

The locked client is `aws/aws-sdk-php` 3.403.5, used through `Aws\S3\S3Client`. The production image uses PHP 8.5.12. Run `composer check-platform-reqs --no-dev` in the intended runtime when changing dependency versions or PHP targets.

## Keys, streams and metadata

Keys retain complete relative paths, such as `uploads/students/cvs/example.pdf`. They must be valid UTF-8, at most 1024 bytes, and begin with `uploads/`. Empty segments, dot segments, backslashes and control characters are rejected. Listing accepts an empty prefix for the full managed namespace or a valid namespace prefix. Limits range from 1 to 1000. Treat cursors as opaque values associated with their backend and prefix. Listing is a live traversal during concurrent mutations.

Callers supply readable PHP stream resources at their current position, verified MIME and the original creation timestamp. Callers close their input and returned read streams. Spooling uses temporary disk space proportional to the object size, with bounded memory. Provide enough temporary disk space for concurrent writes and copies. S3 writes use a single `PutObject` and inherit the provider's single-request object-size limit.

A new destination uses atomic no-overwrite publication. Repeating a write or copy with identical bytes succeeds while preserving its original metadata. Conflicting bytes raise `StorageException`. The S3 backend reads a conflicting destination back and requires the response to match its advertised size and available stored digest, then compares content size and SHA-256 with the attempted write before accepting a retry. Copy reads metadata and bytes from one source response, streams through conditional `PutObject` and retains source data. It verifies the complete spool against the source response size and any available SHA-256 before publishing the destination. It preserves original age and MIME. Conditional destination writes avoid cross-provider differences in `CopyObject` destination preconditions. [AWS conditional PutObject](https://docs.aws.amazon.com/AmazonS3/latest/API/API_PutObject.html), [R2 conditional operations](https://developers.cloudflare.com/r2/api/s3/conditional-operations/)

`stat()` returns `null` for a confirmed missing object. Ambiguous S3 HEAD 404 responses trigger a bucket-existence check. Access, transport, bucket and metadata failures raise `StorageException`. Reading a missing object raises an exception. Deleting an already absent object succeeds.

## Local backend and recovery

`LocalStorage` receives the project root and confines content operations to its `uploads/` tree. It rejects symlinks and nonregular object paths. Upload directories and `storage/object-storage/` must be controlled by the application user and trusted operators. Backend mutation operations share an advisory lock, and a hard-link publication prevents a competing destination overwrite. Upload spools share the destination filesystem, supporting separate uploads and storage volumes.

Sidecar JSON in `storage/object-storage/` retains accepted MIME, original `createdAt` and SHA-256. Keep this directory in coordinated backups with uploaded contents. Temporary spools live beside their destination. The exclusive backend lock keeps concurrent backend listings from observing in-progress spools. A failed write removes its unpublished spool and rolls back a newly published object when metadata persistence fails. An interrupted process can leave a complete, unreferenced object or temporary spool for later reconciliation. Original file mtime is set before publication, retaining lifecycle age when sidecar metadata is absent.

Existing regular files without sidecars remain readable. Their `stat()` uses MIME detection and file mtime, and reports SHA-256 as `null` until content is explicitly verified. Preserve source timestamps during restores and copies. Inventory and migration tools must compute content digests for these files. Keep all source and backup retention decisions in the approved migration procedure.

## Disposable backend tests

Install locked development dependencies, inventory occupied loopback ports, then run the isolated test harness. Override the two ports when needed.

```sh
composer install --prefer-dist --no-interaction
STORAGE_TEST_PORT=62941 STORAGE_TEST_SECONDARY_PORT=62942 bash tests/storage/run.sh
```

The runner creates its own Compose project, disposable MinIO and SeaweedFS services, and random test buckets. It runs the shared local/S3 contract, 96 MiB stream/copy memory checks and failure tests. Its exit trap removes only its project resources. The fixed credentials in `tests/storage/` are disposable test credentials. S3 tests skip during ordinary PHPUnit runs unless `STORAGE_TEST_ENDPOINT` selects a disposable service.

The first endpoint uses MinIO with `us-east-1`. The independent SeaweedFS endpoint exercises `auto` region and the same application configuration contract. The suite also injects explicit external SDK boundary errors and performs real denied-credential, unavailable-endpoint and missing-bucket requests. These local services provide repeatable compatibility evidence. R2 test-bucket qualification belongs to the later migration qualification ticket.

## Public media and scheduled cleanup

Journal uploads and carousel uploads use the selected backend. Journal publication
and draft-preview routes check the current publication state and existing staff
permissions on every request. Withdrawal denies public cover access immediately.
Replacement and removal retain historical journal objects. Carousel images keep
their `/uploads/slider/<filename>` URLs in both applications and resolve only
current `slider_images` records. Apache forwards these paths to PHP, including
when a matching local file exists. Both media readers verify actual image MIME,
content length and available digest before streaming, with `nosniff` and
`no-store` response headers. Storage outages return HTTP 503.

Reconciliation uses the configured backend, traverses every listing page and
compares complete relative keys. Multiple possible basename corrections remain
unresolved. Storage failures propagate to the caller so operators can distinguish
a confirmed missing object from an unavailable backend.

Keep exactly one scheduled jobs owner enabled and disable former host crons before
enabling `jobs`. The hourly `pending` command recursively expires staged objects
using original `createdAt` metadata and `PENDING_UPLOAD_TTL_HOURS` (default 24).
Copy time does not restart retention. It commits durable deletion intents and
retries ordinary cleanup, including carousel removals. Reference publication and
cleanup share `member_file_lock`. Referenced member/media objects and historical
journal objects are retained. Failed listing or remaining retry work exits with a
failure status, and the scheduler keeps running for subsequent attempts.

The daily `trash` command also resumes interrupted permanent purges. Every
scheduled purge continuation requires a verified coordinated backup and
`AMEA_TRASH_BACKUP_CONFIRMED=1`. The hourly command explicitly excludes purge
intents. Direct invocation of `scripts/purge-trashed-students.php` requires the same
acknowledgement. Keep `file_operations` and original storage metadata with the
matching database/files backup for recovery.

For disposable S3 media qualification, `tests/apps/compose.yaml` accepts the same
storage variables. Use a private synthetic bucket and a container-reachable
endpoint. `tests/apps/journal.py`, `journal-retention.php`, `routes.py`,
`tools-browser.mjs` and `jobs.php` exercise the real HTTP/browser and CLI paths.
`STORAGE_TEST_ENDPOINT` enables isolated S3 buckets for the media/reconciliation
and lifecycle PHPUnit cases, including paginated full-key listings and injected
external-storage failures.

## Private migration commands

Ticket 04 provides `scripts/storage-migrate.php`. Its commands operate on explicitly
selected configuration and private evidence. Initial inventory reads the source,
database and existing PHP session files. It creates its manifest and writer-lock
file in the selected evidence directory. Local inventory bypasses the backend's
listing and metadata locks so symlinks, FIFOs, invalid names and unknown directories
remain visible without creating files in the source tree.

Prepare a mode `0700` evidence directory outside the application web root and both
selected local project roots. Every evidence file must be a regular, mode `0600`
file. Keep the original JSON Lines manifest, `.checkpoint` and `.lock` together.
Never delete a checkpoint to bypass a conflict. A corrupt or mismatched checkpoint
requires recovery from retained private evidence. The immutable JSONL inventory's
SHA-256 is the review identity used throughout that migration. A checkpoint holds
all current file/reference states, reconciliation history and correction intents.
Publication uses a private temporary file, file synchronization, atomic rename and
directory synchronization. One nonblocking advisory lock excludes concurrent
writers. Interrupted temporary files can be retained for inspection.

### Explicit selection

The CLI reads process variables directly. Supply a protected operator environment
with these values. It leaves the web application's environment file untouched.

| Variable | Selection |
| --- | --- |
| `MIGRATION_ENVIRONMENT` | Explicit rehearsal or approved operational environment name |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` | Explicit shared database selection, as used by `scripts/migrate.php` |
| `MIGRATION_SOURCE_DRIVER`, `MIGRATION_DESTINATION_DRIVER` | Each is explicitly `local` or `s3` |
| `MIGRATION_SOURCE_ROOT`, `MIGRATION_DESTINATION_ROOT` | Canonical absolute project root for each local backend |
| `MIGRATION_SOURCE_S3_*`, `MIGRATION_DESTINATION_S3_*` | Corresponding `S3_ENDPOINT`, `S3_REGION`, `S3_BUCKET`, `S3_ACCESS_KEY_ID`, `S3_SECRET_ACCESS_KEY`, `S3_PATH_STYLE` settings with the role prefix |
| `MIGRATION_SESSION_PATH` | Existing canonical session directory for the selected environment, including nested PHP session save directories |
| `MIGRATION_SESSION_SERIALIZER` | Actual PHP `session.serialize_handler`, one of `php`, `php_binary`, `php_serialize` |

For example, the destination endpoint variable is
`MIGRATION_DESTINATION_S3_ENDPOINT`. The S3 identity includes endpoint, region,
bucket and addressing style. Credential rotation preserves the backend identity.
Local identities bind the canonical project root. The database identity binds the
reached server, database and PDO connection endpoint. These migration identities
are separate from the application cleanup ledger's existing backend identifier.
Overlapping local upload trees and the same S3 endpoint/bucket are refused,
including S3 selections whose region or addressing style differ.

The API exposes `migrationIdentity()` on the concrete local/S3 adapters and
`ReferenceAudit::databaseIdentity($pdo)`. For an initial local-to-S3 transfer,
inspect the selections with this read-only command before reviewing the hashes.

```sh
php <<'PHP'
<?php
require 'vendor/autoload.php';
$runtime = Amea\Migration\MigrationRuntime::fromEnvironment();
$source = new Amea\Storage\LocalStorage(getenv('MIGRATION_SOURCE_ROOT'));
$_ENV['STORAGE_DRIVER'] = getenv('MIGRATION_DESTINATION_DRIVER');
foreach (['S3_ENDPOINT', 'S3_REGION', 'S3_BUCKET', 'S3_ACCESS_KEY_ID', 'S3_SECRET_ACCESS_KEY', 'S3_PATH_STYLE'] as $name) {
    $_ENV[$name] = getenv('MIGRATION_DESTINATION_' . $name);
}
$destination = Amea\Storage\StorageFactory::fromEnvironment(getcwd());
echo 'database ', Amea\Storage\Migration\ReferenceAudit::databaseIdentity($runtime->connection()->getNativeConnection()), PHP_EOL;
echo 'source ', $source->migrationIdentity(), PHP_EOL;
echo 'destination ', $destination->migrationIdentity(), PHP_EOL;
PHP
```

Set `S3_PATH_STYLE` explicitly in that inspection environment. Review the reached
server/database, roots, bucket, session path and serializer alongside the hashes.
Use the same selections for every subsequent command. Fixture-only CLI tests may
select an existing canonical SQLite file with `MIGRATION_DB_DSN=sqlite:/absolute/path`.
Reference corrections use the MariaDB migration runtime.

### Inventory, transfer and verification

Every command requires these five options. Options use `--name=value` syntax.
Omitting the command selects `inventory`.

```sh
php scripts/storage-migrate.php inventory \
  --environment=rehearsal \
  --database=REVIEWED_DATABASE_SHA256 \
  --source=REVIEWED_SOURCE_SHA256 \
  --destination=REVIEWED_DESTINATION_SHA256 \
  --manifest=/private/operator-evidence/migration.jsonl
```

`inventory` recursively hashes original bytes, records complete keys, size,
original creation time, source mtime, MIME and copy/verification state, then audits
all member, carousel, journal and operation-ledger file-reference fields. It
includes soft-deleted members and every historical/unreferenced regular object.
Invalid UTF-8 filesystem names use lossless base64 with an explicit encoding
field. Invalid keys and special files require reviewed disposition. Traversal,
read or pagination failures abort the inventory. An existing manifest is preserved.
Inventory exit `0` means discovery completed. Its issue/reference counts still
require review before a later transfer is approved.

`audit-references` refreshes the current database and private staged-session
references in the checkpoint. It reports aliases, ambiguous basename matches,
unsafe paths, confirmed missing sources and objects added after the snapshot.
The default `--reference-backend=source` checks current source presence.
`--reference-backend=destination` audits the mapped destination keys, including
post-correction values. `verify` selects destination reference checks automatically.
`file_operations.keys_json`, `expected_identity` and `expected_cv` are included.
Session ownership is represented by a hashed owner identity. Session names,
tokens and unrelated fields are omitted. The parser reads existing files under
shared locks and creates no PHP sessions. Malformed values, objects, serialized
references, busy sessions and records exceeding 16 MiB or 64 nesting levels fail
explicitly and require operator resolution. Configure the actual serializer.

`copy` requires `--review-manifest=INVENTORY_SHA256`. It rescans and hashes the
source before transfer, streams through a disk spool and independently reads every
destination back to compare SHA-256 and size. Existing destinations undergo the
same byte comparison on every retry. Original lifecycle timestamps must match.
The tool keeps all source files and existing destinations. A crash after upload
and before checkpoint publication is recovered by verifying the existing object.
Temporary disk capacity must accommodate the largest object and backend spools.

`verify` rescans current source bytes, refreshes current database/session references
against destination keys and compares every required destination's bytes and
original age. It stores full-key differences in the private checkpoint. Standard
output contains hashes and counts. Source changes with equal size and mtime are
still detected. Added objects become transfer work. Changed and removed entries
retain their previous states in history. Removed entries remain in the inventory
until their retained destination and reviewed disposition have been verified.

When changed bytes encounter an existing full destination key, supply a reviewed
new key through `--mappings=/private/operator-evidence/mappings.json` and
`--review-mappings=FILE_SHA256`. The JSON array contains one entry per source key.
The expected digest guards against source drift after review.

```json
[
  {
    "source_key": "uploads/students/cvs/legacy.pdf",
    "destination_key": "uploads/students/cvs/reconciled-unique.pdf",
    "expected_sha256": "REVIEWED_CURRENT_SOURCE_SHA256"
  }
]
```

Mappings are accepted by `inventory`, `copy` and `reverse-copy`. Conflicting source
or destination mappings fail. A remapped live reference also requires its reviewed
database correction before successful final verification. Existing staged-session
keys retain their current values. Resolve any staged-key conflict while preserving
the session-owned key and retained originals before cutover approval.

### Guarded reference corrections

Use `audit-references` with `--review-manifest=INVENTORY_SHA256`,
`--corrections=/private/operator-evidence/corrections.json` and
`--review-corrections=FILE_SHA256`. Review the exact expected-old values and new
full keys first. This runs under `MigrationRuntime::withLock()` on its native PDO
connection, checks schema-recovery markers, takes `member_file_lock` and applies
all corrections in one transaction. The destination bytes must still match their
unique verified manifest entry. A changed owner/value or ambiguous mapping aborts
the transaction. Preserve a coordinated restorable backup and follow the existing
migration recovery procedure. The final write pause and admission/draining gate
belong to the cutover ticket.

```json
[
  {
    "table": "students",
    "record": "42",
    "column": "cv_path",
    "expected_old": "legacy.pdf",
    "new_value": "uploads/students/cvs/legacy.pdf"
  }
]
```

Allowed columns are the audited file-reference columns. Records use their primary
key as a string. A `keys_json` correction supplies the entire original/replacement
JSON column as strings. Every replacement key is verified. Duplicate column
mappings fail. Session files are audited and preserved. This transactional command
operates on database references.

A durable `prepared` intent precedes the database transaction. After a process
interruption between commit and checkpoint, rerunning the identical reviewed plan
accepts an entirely completed state. Mixed old/new states require operator
reconciliation. Expected-old values remain guarded on ordinary runs. Applied
corrections and current post-correction references remain in the evidence.

### Reviewed exceptions

`--dispositions=/private/operator-evidence/dispositions.json` and
`--review-dispositions=FILE_SHA256` attach reviewed decisions to an existing
manifest. Also pass `--review-manifest=INVENTORY_SHA256`. They are accepted by
`audit-references`, `copy`, `verify` and `reverse-copy`. Each entry has a nonempty
`decision` describing the operator's selected retention/recovery disposition.

| `type` | Required matching fields |
| --- | --- |
| `issue` | `key`, `issue_type` |
| `file` | `source_key`, `expected_sha256`, for removed or invalid-key files |
| `reference` | `owner_type`, `table`, `record`, `column`, `index`, `expected_old`, for confirmed missing references |

Reference decisions remain attached only while the owner and original missing
value remain unchanged. Ambiguous or invalid references must be resolved. A
reviewed removed file still requires its historical destination bytes to verify.
The tool retains every source, backup and prior object. A disposition records an
operator decision and its hash in history.

### Reverse transfer

Select the current active backend as `MIGRATION_SOURCE_*`, the local recovery
root as `MIGRATION_DESTINATION_*` and the current database/session store. Create
and review a fresh inventory at a separate absolute manifest path, then run
`reverse-copy` with its review hash. The command uses current objects and current
references, including new submissions and corrected keys. It leaves the current
database and sessions intact. Conflicting local bytes require an explicit reviewed
mapping. Unresolved current references make `reverse-copy` exit with code `3`.
Run `verify` and the application recovery checks against that current
recovery point before reopening mutations. Keep writers paused when unique active
objects cannot be read or recovered. The cutover ticket owns the persistent gate,
coordinated backup, switch and reopening procedure.

| Exit code | Meaning |
| --- | --- |
| `0` | Selected command completed, with inventory/copy counts requiring the subsequent review/verification steps |
| `1` | Backend, database, session, manifest, checkpoint, lock, content or filesystem failure |
| `2` | Invalid/missing options, unreviewed mutation or selected configuration/identity mismatch |
| `3` | Completed audit or verification has unresolved required references or objects |

Synthetic qualification covers local and MinIO transfers, actual CLI selection,
resumability, current-reference reverse transfer and guarded MariaDB corrections.
R2 compatibility, restored production-data rehearsal, backup restorability,
measured write pause and an approved production transition require their own
recorded operational evidence.
