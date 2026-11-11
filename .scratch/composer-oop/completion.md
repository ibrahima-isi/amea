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
