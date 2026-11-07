# Doctrine Migrations implementation evidence

## Implementation

The user authorized the full four-ticket implementation through implement-spec. Integration started from the approved specification commit 9dc2ec8 on top of dev 25cf44b.

Ticket 01 was implemented in signed commit 5c19ffa and integrated with signed merge 40f17ef. The migration command initializes a fresh database with separate schema and settings versions and rejects an existing unrecorded database. The schema version contains a frozen copy of the adoption baseline. Compose cutover is pending.

Tickets 02, 03 and 04 remain in progress or blocked by their declared dependencies.

## Validation

Ticket 01 implementer reported the following on the signed implementation tree.

- MariaDB 10.11.19 initialized the baseline and recorded exactly two versions in an InnoDB ledger. Table definitions matched the maintained SQL baseline after normalizing generated AUTO_INCREMENT next values. The three settings defaults matched the legacy installer.
- A second migration command applied zero versions and preserved an edited setting. An unrecorded schema import was refused before ledger creation.
- PHPUnit passed 171 tests and 338 assertions with 10 existing deprecations. The four standalone PHP checks and Node theme checks passed.
- The Docker and recovery rehearsal passed with browser checks disabled. HTTP baseline, token and internal journeys and CI static checks passed.

The root copied the stopped demo database volume read-only into an isolated archive, restored it into a separate MariaDB volume and verified 18 member records, three settings and four journal entries. Its column, index and table catalog matched a fresh SQL baseline import. Enrollment qualification is pending.

## Review

Final Standards and Spec reviews are pending.

## CI and Delivery

No remote branch, pull request, hosted CI run, remote merge or production deployment has been performed for this feature.
