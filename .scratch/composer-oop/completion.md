# Composer application migration evidence

## Implementation

| Ticket | Signed commit | Local evidence |
| --- | --- | --- |
| [01](issues/01-split-web-entry-points.md) | `3e286c1` | Four disposable SQLite HTTP suites and the two-host TLS rehearsal passed. All 59 route and method mappings match the previous split apps. Both images contain one front controller and public assets in their respective document roots, with CLI scripts outside the web images. |
| [02](issues/02-migrate-web-helpers.md) | `da1024a` | PHPUnit, the permission and reconciliation checks, four disposable SQLite HTTP suites, and the split-app rehearsal passed. Existing permission and CSRF classes now serve live controller callers. |
| [03](issues/03-migrate-cli-commands.md) | `fdc5dcd` | Six CLI entry scripts delegate to classes. The five command regression tests passed with 33 assertions. Both canonical MariaDB Compose rehearsals passed, including enrollment, retries, locking, recovery and startup refusal. The isolated jobs rehearsal verified mail contention, the trash backup gate, and selective row and file purge. |

## Runtime retirement and qualification on November 11, 2026

- Removed the combined runtime, root HTTP shims, and file-based dispatch. The disposable HTTP harness now uses the public and administration front controllers with separate session directories.
- Both canonical Compose rehearsals and the split-app TLS rehearsal exited successfully after the retirement changes. The rehearsals cleaned their disposable projects and volumes. Browser automation was disabled for the TLS run.
- Updated the setup and test guides for split Compose. The four CI HTTP suites passed after aligning redirect assertions with the real administration entry point. The migrated disposable permissions suite passed 50 checks and now runs in CI.
- The spec and standards reviews identified gaps in CLI diagnostics, purge ownership, session startup, and HTTP coverage. Doctrine argument and database selection diagnostics now work before dependencies are installed. Staff and scheduled purge share the same deletion service. Session startup uses the prior nonempty user ID check and drops the retired startup option. The TLS suite verifies the journal editor’s link to the configured public origin.
- The rendered theme suite passed in fresh Chrome, including origin-scoped preferences, keyboard switching, and one-use export previews. The seeded journal fixture started both apps, published synthetic articles, and cleaned up on termination.
- The reviewed PHP suite passed 177 tests and 377 assertions, with 10 existing PHPUnit deprecations. The five standalone PHP scripts, five JavaScript checks, PHP lint, and all five CI HTTP suites passed.
- The post-review split TLS and both canonical Compose rehearsals passed. Evidence includes the journal editor’s cross-host link, job contention and selective purge, session revocation, Doctrine enrollment and interrupted DDL recovery, rollback and forward replacement, and a separate SQL and file restore.

## Delivery

The [pull request](https://github.com/ibrahima-isi/amea/pull/39) targets `dev` from `refactor/composer-oop`. Work took place in one isolated Git worktree. The first draft head at `fdc5dcd` passed all four [GitHub Actions jobs](https://github.com/ibrahima-isi/amea/actions/runs/42893288530). The [PR checks](https://github.com/ibrahima-isi/amea/pull/39/checks) record the hosted result for the reviewed revision.

## Reconciliation with dev

- Merged PRs #44 and #45 from `dev` at `d767113`. The dedicated entry points now serve carousel objects through the configured backend. Stateful routes and jobs use the storage write gate. Manual and scheduled purges use durable deletion, and scheduled retries require backup confirmation.
- PHP lint, standalone PHP checks, JavaScript theme tests, the six HTTP suites and PHPUnit passed locally. PHPUnit retained 10 deprecations and skipped 41 cases requiring extra integration configuration.
- The disposable S3, canonical MariaDB Compose and split-app HTTPS rehearsals passed, including rollback and verified session, database and file restore. Browser automation was disabled for the Docker rehearsal.

## Merged delivery and Stellar reconciliation on November 21, 2026

PR #39 merged into remote dev as 1534565ba3d81bf3778b93f4172f5653d0d0deda at 01:12:08 UTC, after incorporation of PR #45 at d767113. Local dev points to the same merge and contains the OOP migration. The five post-merge CI jobs passed at that exact revision. [Merged PR](https://github.com/ibrahima-isi/amea/pull/39), [post-merge CI](https://github.com/ibrahima-isi/amea/actions/runs/44848728283)

The [Stellar reconciliation plan](plan.md) updates the earlier hosting plan at local signed commit 5a36916, based on pre-OOP d767113. Source comparison confirms the two native front controllers and class-owned Router are delivered. Native LiteSpeed rules, durable private runtime paths, effective web/CLI configuration, native role attestation and cross-topology session transfer remain implementation and account-qualification work.

Compared with d767113, storage methods, provider configuration, migration/backup command paths and snapshot formats are preserved by PR #39. Cutover::codeIdentity() drops the removed functions tree from its hash inputs. Existing migration evidence stays bound to its recorded code and target identities. The merged branch removes the combined root Dockerfile, so source-side single-app rehearsal requires its retained pre-OOP revision/image.

This reconciliation preserves the tracked Compose files and records dependencies for two web roots, isolated sessions, shared private code/database/R2 and automatic main publication. R2 qualification on amea-sandbox is synthetic-provider evidence. Production R2 migration, full hosting transfer with pending-registration recovery and the actual Stellar account remain to be qualified.

Validation for this update covers the d767113..1534565 source comparison, the approved 5a36916 plan/preflight, local Markdown targets and git diff --check. Changes are confined to the OOP spec, plan and completion record.
