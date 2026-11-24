# 04: Prepare the production transition for approval

Status: ready-for-agent

Blocked by: [03](03-rehearse-transfer-and-registration-resume.md).

## What to build

Give the operator a reviewable proposal for the exact Stellar and R2 move, using the rehearsal results and the current production inventory.

## Acceptance criteria

- [x] Record the verified source revision, roles, writers, database, file storage, session topology, and selected private Stellar and R2 destinations, using approved read-only inventory and excluding secrets.
- [x] Specify the required restorable backup evidence, file read-back checks, session continuity proof, maintenance estimate, reopening criteria and rollback conditions before and after target writes.
- [x] Record the prerequisites for publishing the tested main revision and switching the two web domains. Identify any unresolved evidence and the approval required before the production transition.
- [x] Link the proposal to [object-storage ticket 06](../../object-storage/issues/06-production-transition.md), which tracks the real file copy and production activation under separate approval.

## Comments
