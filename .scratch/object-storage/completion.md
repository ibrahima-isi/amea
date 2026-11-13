# Object storage preparation evidence

## Planning

The user approved the storage design on 2026-11-12. Scope includes all upload flows, R2 as the first provider, S3 portability, complete-file migration, PHP-mediated private access, current paths as object keys, an online copy followed by a final write pause and retained local recovery copies.

An independent builder review of the design reported no important unanswered planning questions. The implementation plan and six local work items were then prepared in the isolated `docs/object-storage-design` worktree based on local `dev` at `eaca561`.

Planning choices include the AWS PHP SDK, a transactional auxiliary file-operation ledger for retries, JSON Lines manifests with atomic checkpoints and a shared persistent write gate. Their contracts appear in the plan.

## Implementation

Ticket 01 was authorized by the user on 2026-11-12 with dedicated agents and review between stages. Implementation starts from signed planning commit `fd9028d` in `/private/tmp/amea-object-storage-01`, branch `feat/object-storage-01`. Tickets 02–06 await separate execution authorization.

### Execution rulings

- Ruling: execute only Ticket 01 in this run, following the user's explicit execution choice. The skill's continuous-plan default is limited by this authorized scope. Extending it would start unapproved tickets.
- Ruling: use this per-feature `completion.md` as the durable execution ledger under AGENTS.md. The skill workspace holds temporary briefs/reports only. This keeps execution recovery in the project's required single record.
- The approved plan already defines the test seams for Ticket 01 as the public storage contract and environment/container configuration. TDD uses these approved seams, with individual red/green slices.

### Plan preflight

| Tasks | Shared file/interface check | Result |
| --- | --- | --- |
| 01 | Storage contract, factory/container, backend tests/config | Consistent within task. Live application callers are integrated by later tickets. |
| 02 | Lifecycle vs transaction/reference/cleanup order | Consistent. Staged sources survive until DB commit. |
| 03 | Journal/slider endpoint and retained-cover requirements | Consistent. Existing public route compatibility is explicit. |
| 04 | Inventory/copy/verification/reverse-copy | Consistent. Full keys and original timestamps are retained. |
| 05 | Admission gate, persisted pause, restart/reopen | Consistent. Paused marker spans commands, exclusive lock drains active mutations. |
| 06 | Live mutation and approval | Separate operational scope with explicit approval. |
| 01 / 02 | Storage streams and Container injection | Task 02 consumes the exact Task 01 contract. |
| 01 / 03 | Stream/list metadata and namespace | Task 03 consumes full-key listing and original-age metadata. |
| 01 / 04 | Separate explicit storage instances and original keys | Factory supports independent configured source/destination instances. |
| 01 / 05 | Compose settings, stream/client behavior | Test/provider qualification extends the contract without rewriting it. |
| 01 / 06 | Backend configuration and evidence | Production requires the separately qualified implementation. |
| 02 / 03 | Operation ledger and retryable deletion | Task 03 wires lifecycle retries into the designated scheduler. |
| 02 / 04 | Sessions, reference corrections and operation ledger | Manifest inventories DB and staged references without changing ownership. |
| 02 / 05 | Schema, session persistence and backup | Task 05 validates matching data/objects/retry state. |
| 02 / 06 | Accepted save/purge consistency | Covered by production access and recovery verification. |
| 03 / 04 | Reconciliation full keys and retained covers | Full recursive inventory includes retained objects. |
| 03 / 05 | HTTP/CLI writers and media reads | Task 05 gates all writers and tests original URLs/privacy. |
| 03 / 06 | Scheduler and public/staff routes | Live procedure retains one scheduler and access checks. |
| 04 / 05 | CLI state/manifest and explicit targets | Final-check and reopen require the verified manifest identity. |
| 04 / 06 | Guarded corrections and hash manifest | Live execution uses reviewed mappings and retained evidence. |
| 05 / 06 | R2/backups/draining and measured interruption | Live transition depends on completed rehearsal evidence. |

## Validation and review

Design and plan documentation receive link, whitespace, blocker and interface consistency checks. These are planning checks. Application/backend tests and provider qualification are acceptance work in the corresponding tickets.

### Ticket 01 initial implementation and review

Signed implementation `53b2d105211e7c07891f8b87dabb3dd89e8a8c22` adds the storage contract, local and S3 backends, factory/container wiring, shared Compose configuration, operator documentation and disposable provider tests. The signature was verified independently.

The implementer reported 62 focused unit tests with 179 assertions, and 25 tests with 120 assertions for each real disposable provider, MinIO and SeaweedFS. A 96 MiB round-trip/copy exercised bounded memory. Built public/staff PHP 8.5.12 images with production-only dependencies exercised both default-local and actual S3 operations through `Container::storage()` on separate uploads/storage mounts. Full PHPUnit reported 258 tests and 517 assertions, with 25 S3 tests skipped without an explicit endpoint and ten existing deprecations. Four PHP regression scripts, five theme tests and three HTTP journeys passed. Existing Twig advisories remained on the unchanged locked package. Full local application Docker/Doctrine journeys are delegated to hosted CI for this ticket.

Task review found two Important issues. A truncated S3 GET could be published as a successful smaller copy, demonstrated with 100 bytes declared and seven received. Key validation was duplicated between backends. Both were sent to the same implementer for fix round 1/5. The baseline PHPUnit deprecations were recorded as a Minor qualification.

Signed fix `45d6a35b76fea668100e9aa4f8205d2b3c626b49` verifies the source GET length and available SHA-256 after spooling and before publishing an S3 copy. Four regression cases reproduced the original defect and then passed with 16 assertions. A shared internal key-validation trait removes the duplication. Focused units passed 66 tests and 195 assertions. MinIO and SeaweedFS each passed 25 tests and 120 assertions, with two filesystem-only cases skipped per remote backend. Rebuilt public/staff production images passed the default-local and actual S3 runtime proof again. The fix signature was verified independently.

Scoped re-review verified both Important findings addressed and found no new breakage. Task 1: complete locally (commits `fd9028d..45d6a35`, task review clean). The existing PHPUnit deprecations remain a baseline qualification. Whole-branch review, exact-commit hosted CI, PR merge and local-main integration remain delivery work.

Local evidence is in `/private/tmp/amea-storage01-artifacts/`, including `task-1-report.md`, `task-1-review.md`, `task-1-fix1-review.md`, signed-range diffs, test logs and built-runtime proof. R2 test-bucket qualification belongs to Ticket 05. The backend uses single-request PutObject and therefore inherits the provider's single-PUT size limit; future larger-object upload behavior requires its corresponding feature design.

### Whole-branch review

The independent broad review of `eaca561..e80c1c3` found one Important issue in the conditional-write retry path. After a 412 response, a truncated destination GET could falsely confirm an identical retry because its advertised length and available digest were ignored. The reviewer reproduced a six-byte attempted write being accepted against an existing 24-byte object. The finding also applies to copy retries. It was sent to the implementer as the final review fix wave, with new regressions required before scoped re-review. Evidence is in `final-review.md` under the local artifact directory.

Signed correction `3fed1bac6a0dcab40aa36222f4eecda7e54f6b2b` retains and verifies the destination GET metadata in that retry path. Source-copy and retry verification share their size/digest check. Six new cases reproduced false-success behavior for write/copy, then passed with 18 assertions. The focused unit suite passed 72 tests and 213 assertions. Both actual disposable providers again passed 25 tests and 120 assertions. Rebuilt production-only public/staff images exercised real writes, copies and identical retries successfully. Signature verification passed. The final scoped re-review verified the finding addressed, found no new breakage and approved merge readiness subject to latest-head CI and repository protections. Its evidence is `final-fix-review.md` in the artifact directory.

## CI and merge

Ticket 01 was delivered through [PR 40](https://github.com/ibrahima-isi/amea/pull/40), targeting `dev`.

- Final PR head `7732f15e2091375a83a9c6ec121ed885b25c8a19` passed all four jobs in [CI run 43337676711](https://github.com/ibrahima-isi/amea/actions/runs/43337676711). Its regression output reports 268 tests and 551 assertions, with 25 endpoint-dependent S3 skips and the ten existing PHPUnit deprecations. The actual disposable-provider tests are separately evidenced above.
- Task review, its scoped re-review, independent whole-branch review and its scoped re-review completed before publication. All source and documentation commits report good cryptographic signatures.
- PR 40 actually merged at `2026-11-13T00:35:43Z` as `e3fb18002563f4ad1660c3d703d9e76dd7bf810e`. GitHub reports its merge signature verified with reason `valid`. An ancestry check verified the tested PR head is contained in remote `dev`.
- [Post-merge CI run 43338302280](https://github.com/ibrahima-isi/amea/actions/runs/43338302280) passed all four jobs on that exact merge commit, including Docker/recovery and Doctrine/Compose startup qualification.
- An isolated worktree fast-forwarded local `main` from `581244b` to the actual merged result. Storage source, dependencies and both Compose files matched remote `dev` exactly. This local closeout adds only the final execution evidence and ticket comment.
- The original `dev` checkout and the unrelated Composer refactor worktree/branch were preserved. The task's implementation/design worktrees and temporary branches were removed after clean-status and ancestry checks. Their only ignored implementation-worktree contents were generated dependency, PHPUnit, Python and Twig caches plus the task-owned skill ignore file. Local test reports remain under `/private/tmp/amea-storage01-artifacts/`.
- Disposable test providers and runtime containers were removed. The pre-existing `meetstudent-ticket02-minio` service remained running on its original port 62182. The original production configuration and live data were outside this repository delivery scope.

The signed local-main closeout is retained locally under the project's main-integration workflow. Tickets 02–06 continue to require their own execution authorization.

## R2 and production

Actual bucket credentials, live file/reference inventory, backup restorability and measured maintenance duration require operational qualification. Production execution requires explicit approval of the selected resources and concrete transition. Source-copy deletion remains a separate decision after migration validation.
