# Doctrine Migrations implementation evidence

## Implementation

The user authorized the full four-ticket implementation through implement-spec. Integration started from the approved specification commit 9dc2ec8 on top of dev 25cf44b.

Ticket 01 was implemented in signed commit 5c19ffa and integrated with signed merge 40f17ef. The migration command initializes a fresh database with separate schema and settings versions and rejects an existing unrecorded database. The schema version contains a frozen copy of the adoption baseline. Compose cutover is pending.

Ticket 03 was implemented in signed commit 310ff75 and integrated at eeb365e. A database advisory lock serializes migration execution. Nontransactional schema versions persist recovery markers before execution, require a one-invocation backup acknowledgement on an existing database, and block retries after interruption pending verification.

Ticket 02 was implemented in signed commit be4b068 and integrated at 5ca423c. Explicit enrollment verifies the selected database, operator evidence, supported schema variants and data invariants before recording both baseline versions transactionally. Ticket 04 owns the final shared lock and recovery guard wiring in the enrollment entry point.

Ticket 04 is now unblocked and in progress.

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

Final Standards and Spec reviews are pending.

## CI and Delivery

No remote branch, pull request, hosted CI run, remote merge or production deployment has been performed for this feature.
