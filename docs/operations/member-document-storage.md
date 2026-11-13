# Member document lifecycle

Registration, public correction and staff editing use the configured local or S3
storage backend. Journal and carousel media use the same selected backend.
Member files retain their relative `uploads/students/` keys. Existing pending
session data and local files remain readable through the same routes.

Uploads use the existing per-flow extension, MIME and size policies. Replacement
files stage under unique keys. Every final copy is read back and compared by size
and SHA-256 before a reference transaction starts. Staged sources remain present
through the database commit. A failed save retains the previous reference and
its file, together with staged and unreferenced copied objects for recovery.
Superseded session-only staging retains its original age for pending cleanup.

Member reference transactions lock `member_file_lock` row `1`, followed by the
current member row. Concurrent requests resolve omitted replacement fields from
that locked row. Lookup and nationality changes share the reference transaction.
The same lock protects cleanup, permanent deletion and restoration. This also
serializes eligibility checks for a key shared by different members or columns,
including members in the trash.

## Retry deletion

`file_operations` records a stable operation ID, backend identity, complete key
list, reason, target member and expected references. Cleanup intents commit with
the reference change. Cleanup failures retain their per-key errors and attempt
count. A missing object completes successfully on retry.

Successful member saves attempt a bounded cleanup batch after commit. Cleanup
failure leaves the saved dossier usable. The public `retryDeletions(int $limit, bool $includePurge = true)`
method supports the dedicated scheduler integration. The hourly worker passes
`false` to exclude permanent purge continuations. The backup-acknowledged daily
trash worker includes them. It selects only intents
whose backend identity matches the configured endpoint/bucket or local root.
Keep those identities and retained intents available during migration inventory.

Soft deletion keeps documents available for restoration. Explicit manual permanent
deletion retains its immediate behavior. The existing retention purge command
calls this same lifecycle and rechecks the cutoff under the member lock.
Permanent deletion commits its durable `started` intent before deleting any
object. Restoration then remains unavailable until the purge completes. A failed
purge keeps the trashed row and every key, including objects already deleted.
Repeat the permanent-delete action to retry. The row is removed after every
eligible deletion succeeds. Keys referenced by another member remain intact.

## Schema and recovery

`Version20261113000000` creates the two InnoDB tables in a nontransactional schema
version. `Version20261113000001` initializes the lock in a separate transactional
version. Historical versions retain their original source. Existing databases
require the verified-backup acknowledgement described in
[migration recovery](migration-recovery.md). An interrupted version retains its
recovery marker. Verify every table, column, index and default before reconciling,
or restore the verified pre-change database and matching files.

Private downloads and pending previews authorize access through their existing
routes. They spool storage reads to a temporary file, verify length and checksum,
and check actual MIME before headers. Memory use remains bounded. Filenames,
inline/attachment behavior, `private, no-store` and `nosniff` headers are preserved.

## Disposable qualification

The ordinary PHPUnit suite includes actual loopback HTTP member flows. Set
`STORAGE_TEST_ENDPOINT` to a disposable loopback S3 service to run those flows and
`FileLifecycleServiceTest` against isolated private test buckets. Credentials for
that synthetic service are `storage-test` and `disposable-storage-secret`.

For real MariaDB contention, select the dedicated `amea_storage02` database,
initialize it with the normal migration command and run `tests/apps/member-storage.php`
with `APP_ENV=test` and `MEMBER_TEST_ROOT` pointing to an isolated directory whose
basename begins with `amea-storage02-`. It creates member `9001` and removes it
through the lifecycle. Keep the database and storage wholly disposable.
