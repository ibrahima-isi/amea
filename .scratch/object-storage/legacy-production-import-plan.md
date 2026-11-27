# AMEA Legacy Production Import Implementation Plan

> Execute this plan in the isolated worktree. The production transition remains tracked by [Ticket 06](issues/06-production-transition.md).

**Goal**

Load the supported legacy data into a clean Doctrine-managed database while retaining the complete verified legacy backup privately.

**Approved design**

Initialize an empty MariaDB 11.4.13 schema with the release migration CLI, then import supported columns by explicit name. Preserve primary keys, relationships, existing password hashes, consent flags and KYC decisions. Leave retired fields and the three legacy tables in the complete private archive. The operator approved retaining the additional missing legacy photo reference in the archive and the 21 missing identity document references in the migrated member records.

**Approaches considered**

- Import supported columns into the clean baseline and archive the full original dataset. This is the approved approach and keeps the published runtime unchanged.
- Keep retired fields accessible in the application. That needs a separately scoped application feature and explicit access rules for historical data.

## Execution

- [x] Start a disposable MariaDB 11.4.13 container on `--network none`. Restore `database.sql.gz` into its original isolated schema and create an empty `amea_bridge_target` schema. Run `php scripts/migrate.php migrate` against `amea_bridge_target` using the pinned release archive and a shared Unix socket.
- [x] Build the country mapping by matching `countries.name_fr` to French labels in the host `iso-codes` package. Resolve the reviewed historical labels `Biélorussie` → `BY`, `Brunei` → `BN`, `Centrafrique` → `CF`, `Congo-Brazzaville` → `CG`, `Congo-Kinshasa` → `CD`, `Marshall` → `MH`, `Micronésie` → `FM`, `Palestine` → `PS`, `Russie` → `RU`, `Salomon` → `SB` and `Vatican` → `VA`. Preserve the old IDs and French labels. Use the English reference label for `name_en` and require one distinct ISO code per row.
- [x] In one InnoDB transaction import `countries`, lookup tables, `users`, `students`, `student_country`, `slider_images`, `password_resets` and `settings` in foreign-key order. Select every shared column explicitly. Use the target default `1` for `users.session_version`. Preserve settings by `setting_key`. Leave `journal_posts` and the operations ledger empty. Abort on SQL warnings and exact-value comparison failures.
- [x] Compare exact counts, primary key sets and null-safe binary values of all shared columns for each table, verify all legacy country associations, and rerun `php scripts/migrate.php status` and `migrate` against the isolated target. Record the matrix and archive hashes without personal values or secrets.
- [ ] Use the frozen source capture, qualify legacy session transition on that capture, and apply the recorded operational gates in [the transition procedure](../../docs/operations/stellar-production-transition.md) for the live run.

## Isolated rehearsal results

- The database import and exact value comparison passed on MariaDB 11.4.13. All 191 member rows, 5 staff users, 193 country IDs, 167 member-country relationships and 3 slider rows were preserved. Text from the legacy MyISAM tables was compared after conversion to UTF-8 without collation folding.
- The same snapshot passed the official file inventory, reviewed reference audit, clone and re-read verification with 207 files and 68 references. Twenty-one pre-existing missing identity references were dispositioned, leaving zero unresolved differences. This used a network-isolated private filesystem destination. The production R2 bucket separately passed synthetic write, read-back, deletion and anonymous access checks.
- The archived single-app session store decoded under the `php` serializer. No active registration was present at capture. The split rehearsal imported no staff sessions and used a private destination.

## Compatibility checkpoint

The first isolated import discovered one 147-character `housing_details` value and two empty legacy `gender` values. The operator approved a Doctrine version that restores the historical 255-character housing limit and allows a missing gender for imported records. The import maps only the two empty values to SQL `NULL`, verifies that mapping explicitly, and retains their original values in the private archive. New registration validation remains in the existing application service.

The baseline archive and any legacy member fields remain in the private recovery directory on the VPS. Keep the unmodified archive, isolate the rehearsal, and retain both the archive and the source through cutover verification.
