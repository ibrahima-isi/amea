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
