# Enroll a verified existing database

Enrollment records the two adoption baseline versions without running historical
PHP scripts or Doctrine baseline migrations. It never rewrites application rows.
This procedure applies to an isolated restored database first; production changes
require separate approval. Use MariaDB 10.11, the version used by Compose.

## Select and verify the database

1. Verify a restorable database and upload backup. Restore to a new isolated
   database/volume and keep the original demo database and uploads untouched.
2. Stop public, staff, jobs, migration services, manual commands and every other
   writer targeting the selected database. Keep MariaDB running. Maintain this
   state through verification and enrollment. `--writers-stopped` is an operator
   acknowledgement, not an automatic shutdown of other connections.
3. Export `DB_HOST`, `DB_PORT` (default `3306`), `DB_NAME`, `DB_USER` and `DB_PASS`
   into the CLI environment using a private, untracked secret file or secret
   manager. Use the database owner account with visibility of all tables,
   triggers, routines and events. Never save credentials or private member data
   in Git, command transcripts or the evidence document.
4. Inspect `SELECT DATABASE(), @@hostname, @@port, VERSION()` and compare the
   selected server with the restore/import record. `--database` must match both
   `DB_NAME` and the connected database. The evidence must match the configured
   connection host, port and database name.
5. Verify migration evidence and targeted data. Table/column presence alone does
   not establish that the KYC backfill ran. For a legacy database, review evidence
   of successful execution of the complete historical `00` through `07` runner,
   including migration `04`'s backfill. Cite the deployment/restore record and
   inspect dossier review states against that history. A locked dossier can
   legitimately still be `PENDING_CONFIRMATION` after later activity; enrollment
   must preserve it. Check the three initial settings exist, staff roles and
   session versions are valid, member enums are valid, and journal/document
   references correspond to the restored uploads. Do not infer or repair review
   status by replaying old scripts.

The command accepts the canonical adoption schema stored as readable MariaDB
`SHOW CREATE TABLE` definitions in
`src/Migration/enrollment-schema.sql`. It also accepts historical `settings`
with `setting_key` as its primary key, no `id`/`setting_group`, Unicode collation,
and the historical nullable timestamp. Migration `06` member enums may retain
any order of the same known values, valid defaults, comments and
`utf8mb4_general_ci`/`utf8mb4_unicode_ci` collation; gender and member status remain
non-nullable. Other table, column, index and foreign-key definitions must match.
Extra views/tables, triggers, routines and events are refused. Unsupported
variants require investigation; do not suppress diagnostics to force enrollment.

## SQL baseline imports

For a database created by importing maintained `schema.sql`, verify the import
went into an **empty** database and matches this adoption checksum:

```text
6b90bcf1c63c4751f64d8458624348b8aa2f8fd29723a099305563185bbead0d
```

`schema.sql` defines structure, not the initial settings. Before enrollment of
that new import, verify all three settings exist. If absent, provision the
following initial rows as part of that import, then record and review the result:

```sql
INSERT INTO settings (setting_key, setting_value) VALUES
    ('contact_email', 'admin@aeesgs.org'),
    ('contact_phone', '+221 XX XXX XX XX'),
    ('organization_name', 'AEESGS');
```

Do not overwrite customized settings. Do not treat an import into an existing
database as an empty-database import. Later changes to `schema.sql` need their
own migration and verification rather than silently changing the adoption hash.

## Evidence and execution

Create a private JSON evidence document after verification. For a legacy restore:

```json
{
  "database": "selected_restore",
  "host": "selected-db-host",
  "port": 3306,
  "source": "legacy-migrations",
  "reference": "Reviewed complete 00-07 migration success and restore record; targeted data checks completed",
  "kyc_backfill_verified": true
}
```

For a verified empty SQL import use `"source": "schema-sql"`,
`"imported_into_empty_database": true` and `"schema_sha256"` with the checksum
above instead of `kyc_backfill_verified`. Keep the reference specific to an
actual operator-reviewed record. The JSON is an attestation and context pointer;
it cannot independently prove the truth of that record.

```sh
php scripts/enroll-migrations.php --database="$DB_NAME" --writers-stopped --evidence=/private/path/enrollment.json
php scripts/migrate.php status
php scripts/migrate.php migrate
```

Before resuming writers, compare private before/after snapshots of settings,
member review states and document references, journal rows and cover references.
The ledger must contain both adoption versions. A second enrollment adds no rows
and preserves their timestamps. Ordinary migration then reports no pending
versions. Keep only counts and comparisons in shared evidence.

Verification failures exit non-zero and name the failed check. No version is
written until verification succeeds. Ledger provisioning is separate DDL; both
version inserts then run in one InnoDB transaction. An insert failure leaves zero
baseline rows, so a verified retry is safe. Partial or unknown ledgers are refused
for operator reconciliation. Do not delete versions or edit business data to
bypass a refusal. Advisory locking and interrupted-schema recovery are handled
by the shared runner integration; keep writers stopped during this procedure.

## Disposable command regression

`tests/apps/doctrine-enrollment.php` exercises command exits, preservation,
supported legacy variants, unknown schema objects, invalid ledgers and rollback
when a server constraint rejects the second baseline insert. It **drops tables**
and is guarded by `APP_ENV=test` and `DB_NAME=amea_doctrine_enrollment`. Run it only
on a dedicated disposable MariaDB 10.11 instance, never on a demo restore:

```sh
APP_ENV=test DB_NAME=amea_doctrine_enrollment php tests/apps/doctrine-enrollment.php
```

The database must already exist, the other `DB_*` variables must select that
isolated server, and the operator account must have DDL and metadata privileges.
