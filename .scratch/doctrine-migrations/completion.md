# Doctrine Migrations implementation evidence

## Implementation

The user authorized the full four-ticket implementation through implement-spec. Integration started from the approved specification commit 9dc2ec8 on top of dev 25cf44b.

Ticket 01 was implemented in signed commit 5c19ffa and integrated with signed merge 40f17ef. The migration command initializes a fresh database with separate schema and settings versions and rejects an existing unrecorded database. The schema version contains a frozen copy of the adoption baseline. Compose cutover is pending.

Ticket 03 was implemented in signed commit 310ff75 and integrated at eeb365e. A database advisory lock serializes migration execution. Nontransactional schema versions persist recovery markers before execution, require a one-invocation backup acknowledgement on an existing database, and block retries after interruption pending verification.

Ticket 02 was implemented in signed commit be4b068 and integrated at 5ca423c. Explicit enrollment verifies the selected database, operator evidence, supported schema variants and data invariants before recording both baseline versions transactionally. Ticket 04 owns the final shared lock and recovery guard wiring in the enrollment entry point.

Ticket 04 was implemented in signed commit a1b55a7 and integrated with signed merge 5a64cae. Both shipped Compose stacks use the Doctrine command and keep public, staff and optional jobs behind successful migration completion. Enrollment and migration share the same database lock; enrollment checks interrupted-schema recovery before recording versions.

The final review correction was implemented in signed commit 000ade3 and integrated with signed merge b272d0. Automatic fresh initialization now also checks schema routines and events. Procedure-only, function-only and event-only databases are refused for explicit verification/enrollment before any application, ledger or recovery table is created. Tables and views were already covered by SHOW FULL TABLES; triggers require an owning table.

## Validation

Ticket 01 implementer reported the following on the signed implementation tree.

- MariaDB 10.11.19 initialized the baseline and recorded exactly two versions in an InnoDB ledger. Table definitions matched the maintained SQL baseline after normalizing generated AUTO_INCREMENT next values. The three settings defaults matched the legacy installer.
- A second migration command applied zero versions and preserved an edited setting. An unrecorded schema import was refused before ledger creation.
- PHPUnit passed 171 tests and 338 assertions with 10 existing deprecations. The four standalone PHP checks and Node theme checks passed.
- The Docker and recovery rehearsal passed with browser checks disabled. HTTP baseline, token and internal journeys and CI static checks passed.

The root copied the stopped demo database volume read-only into an isolated archive, restored it into a separate MariaDB volume and verified 18 member records, three settings and four journal entries. Its column, index and table catalog matched a fresh SQL baseline import. Ticket 02 then enrolled that restored copy and repeated enrollment. All twelve application tables retained identical sorted-row JSON hashes. The copy retained 18 students, three settings and four journal entries; normal migration reported no pending versions. The operator evidence is the root's earlier observation in this conversation of a successful complete legacy runner on Compose project amea before demo seeding, followed by the offline restore and targeted checks. This is local operator evidence, without a checked-in deployment log.

Ticket 02's MariaDB regression verified canonical and historical settings shapes, legacy enum order/default/collation/comment variants, rejection of unknown objects and partial states, and rollback when the second version insert fails. PHPUnit, the four standalone PHP checks, Node theme checks and the three HTTP journeys passed.

Ticket 03's disposable MariaDB rehearsal passed 22 assertions, covering two concurrent runners, data-and-ledger rollback, backup refusal before metadata mutation, interruption before schema version recording, marker-write failure, cleanup failure after version recording, and verified reconciliation. The fresh-database test and shared PHP regressions also passed.

## Review

Full integration review at 5a64cae against fixed base 25cf44b reported no actionable Standards or Spec findings apart from one P2 correctness finding: a routine-only or event-only unversioned database could bypass explicit enrollment. The fresh implementer reproduced the issue and corrected it using the existing migration CLI regression seam. A fresh independent read-only review of integrated correction b272d0 reported zero actionable Standards findings and zero actionable Spec findings, confirming the P2 addressed. It inspected the red/green and PHPUnit logs. MariaDB sequences are also covered by SHOW FULL TABLES; triggers require an owning table.

## CI and Delivery

No remote branch, pull request, hosted CI run, remote merge or production deployment has been performed for this feature.

## Final local qualification (2026-11-08)

Ticket 04's frozen-source qualification log `/private/tmp/amea-doctrine04-qualified-compose.log` verifies both actual shipped Compose configurations on disposable MariaDB 10.11: fresh initialization, second-run no-op with edited settings preserved, unrecorded database refusal, explicit enrollment followed by successful startup, injected version-write failure and interrupted-schema refusal. Public, staff and jobs remain blocked when migration fails. The same qualification includes canonical fresh-schema parity, supported enrollment variants, enrollment rollback, shared enrollment/migration locking and recovery command regressions. The actual migrate image also reported zero pending migrations against the enrolled restored demo copy without a source mount.

The existing MySQL Docker/recovery harness, HTTP baseline/token/internal journeys, PHP syntax/static checks, four standalone PHP regressions and five Node theme tests passed. Local log pointers are `/private/tmp/amea-doctrine04-mysql.log`, `/private/tmp/amea-doctrine04-http-{baseline,tokens,internals}.log`, `/private/tmp/amea-doctrine04-syntax.log`, `/private/tmp/amea-doctrine04-phpunit.log` and `/private/tmp/amea-doctrine04-js.log`. These logs are local evidence, not repository artifacts or hosted CI results.

The final correction's dedicated task-labelled disposable MariaDB 10.11.19 harness reproduced the original error: a procedure-only database incorrectly received both baseline versions. After the fix, procedure-only, function-only and disabled-event-only databases each returned nonzero with the enrollment diagnostic, retained their original object and contained no tables, including migration ledger/recovery tables. The same run then passed fresh initialization, schema parity, settings, two-version ledger/status, zero-change rerun and SQL-import refusal. Evidence: `/private/tmp/amea-doctrine-empty-objects-red.log` and `/private/tmp/amea-doctrine-empty-objects-green.log`.

On signed correction 000ade3, PHPUnit passed 171 tests and 338 assertions with ten existing deprecations; changed-file PHP syntax and diff whitespace checks passed. Evidence: `/private/tmp/amea-doctrine-empty-objects-phpunit.log`. Integration b272d0 merges that exact tested source without modification. The earlier full Compose/HTTP qualification was not repeated for the focused object-catalog check; the changed migration CLI path was rerun directly on the Compose MariaDB version. Unchanged Twig dependency audit findings and Composer metadata warnings remain outside this scope.

## Local tracker closure and cleanup

All four implementation tickets' acceptance criteria are checked and their Comments point to this record; their allowed triage Status values remain unchanged. Every implementation and integration commit is cryptographically signed. The five prior ticket/spec worktrees and branches were removed only after verifying clean status and ancestry into integration. The clean correction worktree and branch were also removed after verifying ancestry into integration. The integration delivery worktree and unrelated `amea-close-ci` worktree remain preserved.

The three earlier task-owned disposable MariaDB containers and their inspected copy/anonymous volumes and task networks were removed. The dedicated final-correction container and its anonymous volume were also removed after regression and independent review. Original demo containers and volumes were preserved; its stopped database was never migrated in place. The private local restore archive and evidence files remain outside version control. No production changes or hosted operations were made.
