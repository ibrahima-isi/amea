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

## Ticket 02 execution

On 2026-11-13 the user invoked `implement` for the next ticket, authorizing Ticket 02, "Integrate member documents and consistent file lifecycle". Ticket 01's blocker was rechecked through merged PR 40, remote `dev` containing `e3fb180`, and the successful post-merge CI run.

The isolated implementation worktree is `/private/tmp/amea-object-storage-02`, branch `feat/object-storage-02`, starting from local `main` at `0595a34bd9dfbc5db1b008ff1905edc1a2a33ccb`. That base contains the delivered storage foundation and its signed closeout. The original checkout and active Composer refactor PR/worktree remain preserved.

The approved test seams are the public file-lifecycle API and the actual registration, correction, editing, preview/download, trash and restore entry points. TDD will cover storage/DB failure consistency at those seams, then real local/S3 HTTP/browser journeys and disposable MariaDB migration/concurrency qualification. The existing per-flow MIME/size rules, French UI, permissions and session ownership remain compatibility contracts. Ticket 03's journal/slider integration and scheduled-job migration remain separate work.

Execution evidence and review results will be appended here. Private local logs and agent reports live under `/private/tmp/amea-storage02-artifacts/`.

### Ticket 02 implementation and validation

Signed commit `b1adb917efa9ace412cb3b983372a99299d629c8` integrates the configured storage into member registration, correction, editing, private reads and permanent deletion. The controller independently verified its cryptographic signature. The current-row and shared member-file locks preserve concurrent replacements. References and cleanup intents commit together, and finalization reads source/destination bytes before publishing references. Deletion checks references across members and trash. Interrupted purges retain their identity and keys.

Two new versions separate nontransactional operation-table DDL from transactional lock-row initialization. Historical migration sources remain frozen. Enrollment verifies the exact historical two-version adoption snapshot or maintained four-version snapshot, including the new tables and singleton data.

The full PHPUnit suite passed once with the actual disposable S3 endpoint selected, reporting 277 tests, 708 assertions, ten existing deprecations and two filesystem-only skips. Four standalone PHP regressions, five theme tests and HTTP baseline/token/internal journeys passed. Focused lifecycle/controller checks passed on MinIO. Real MariaDB concurrency checks passed with local and S3 objects, including different-field replacement, same-field replacement and restoration winning before purge starts.

Chrome registration, both staged-document previews and confirmation passed in local and S3 modes. Desktop/mobile, light/dark captures are retained under `browser-local/` and `browser-s3/` in the artifact directory. The controller inspected the S3 desktop-light and mobile-dark review screenshots. Actual form/HTTP tests also cover correction, private download, role denial, content-based MIME rejection, DB rollback, legacy staged sessions and purge failure/retry.

An isolated MariaDB recovery rehearsal exercised a failure after the first new CREATE, durable recovery-marker refusal, verified SQL/files restore, successful four-version upgrade and zero-change retry. Both canonical Compose stacks passed `tests/apps/doctrine-compose.sh`. The full `tests/apps/run.sh` public/staff/jobs, backup/restore and rollback chain passed. Legacy fixtures were updated to the exact new migration sources and four-version expectations after their initial failures. Source-level and fixture PHP syntax checks passed.

Implementation choices retain superseded session-only staging for the existing pending cleanup because DB and session persistence have no shared commit. Once purge durably begins, restoration is refused until it completes. The existing retention purge has a minimal lifecycle bridge to prevent bypassing the new guarantees. General scheduler and media migration remain Ticket 03 scope.

### Ticket 02 Standards review

The independent Standards review of `0595a34...b1adb91` found zero documented-rule violations. It recorded one optional possible Duplicated Code smell in the three controllers' post-commit best-effort cleanup blocks. No present functional defect was identified. The optional consolidation is deferred, preserving the reviewed working behavior.

### Ticket 02 Spec review

The separate Spec review of the same range found zero actionable findings. It checked current-row replacement semantics, retained sources and rollback, shared-reference protection, partial purge/restore behavior, session ownership, MIME and headers, the migration split and exact enrollment snapshots. Its result is independent of the Standards axis.

Evidence includes `task-2-report.md`, `standards-review.md`, `spec-review.md`, `review.diff`, `phpunit-full.txt`, `http-{local,s3}.txt`, `local-browser.txt`, `s3-browser.txt`, `concurrent-{local,s3}.txt`, `member-schema-recovery.txt`, `apps-green.txt` and `doctrine-compose-final.txt`. Dedicated MinIO/MariaDB and rehearsal resources were removed; the existing MeetStudent MinIO on port 62182 was preserved. The corresponding hosted CI, merge and local-main integration evidence follows.

### Ticket 02 delivery

[PR 41](https://github.com/ibrahima-isi/amea/pull/41) delivered Ticket 02 into remote `dev`. All four jobs passed on exact PR head `975ed2addc71ccfc16e5cefc32607d3ae4d3beca` in [CI run 43448858831](https://github.com/ibrahima-isi/amea/actions/runs/43448858831). Hosted PHPUnit reported 277 tests and 588 assertions, with 25 endpoint-dependent S3 skips and ten existing deprecations. The separately executed actual-S3 suite reported the 708 assertions and two skips recorded above.

The PR actually merged at `2026-11-13T15:10:54Z` as `3febc60033d58a872bb4c80e89a68a61f6975739`. GitHub reports its merge signature verified with reason `valid`. Ancestry checks confirmed the tested PR head is in remote `dev` and local `main`. Local `main` was fast-forwarded from `0595a34` in an isolated worktree, and production source/tests/operations guidance matched remote `dev` exactly before this documentation-only closeout.

[Post-merge CI run 43449988322](https://github.com/ibrahima-isi/amea/actions/runs/43449988322) passed all four jobs on the actual merge commit `3febc60033d58a872bb4c80e89a68a61f6975739`, including Docker/recovery and the MariaDB/Compose startup gates.

After clean-status, ancestry and ignored-file inventory checks, the task implementation worktree and branch were removed. Ignored contents were only installed dependencies, PHPUnit/Python caches and compiled Twig cache files. The original checkout and the unrelated Composer refactor worktree/branch were preserved. Git's configured remote URL was retained; publication used a per-invocation HTTPS credential helper while SSH authentication was unavailable. Every created commit was signed. Final private evidence remains under `/private/tmp/amea-storage02-artifacts/`.

The started-purge boundary is irreversible and prevents restoration of a partially deleted dossier. Superseded session-only staging retains its original age until pending cleanup. These implementation choices and the optional post-commit cleanup duplication remain documented for operators and subsequent work. Tickets 03–06 require separate execution authorization.

## Ticket 03 execution

On 2026-11-13 the user invoked `implement` for the next ticket, authorizing Ticket 03, "Integrate public media, reconciliation and jobs". Ticket 02 is recorded as delivered in merged PR 41 with successful exact-head and post-merge CI, and its signed closeout is part of the starting branch.

The isolated worktree is `/private/tmp/amea-object-storage-03`, branch `feat/object-storage-03`, based on local `main` at `57de6f74590ad51e103a0629801d6e146bc9d36f`. Preserve the original checkout and active Composer refactor worktree. The reviewed starting code matches the delivered storage/member foundation.

The pre-agreed test seams are the actual journal/slider HTTP and browser flows, backend-independent reconciliation, and scheduled cleanup/retry entry points. TDD covers published/withdrawn/draft access, retained historical covers, original slider URLs, full-key pagination and storage error distinctions, original staging age and single-owner job/backup behavior. Logs and reports are kept under `/private/tmp/amea-storage03-artifacts/`. The per-feature completion record remains the execution ledger.

### Ticket 03 initial implementation and validation

Signed commit `9b61c04901131fb961dbe2808a52b25939d7b3fd` adds storage-backed journal and active-record slider reads, original URL routing, full-key reconciliation, original-age pending cleanup and scheduled retry wiring. Its signature was verified independently. No schema change was needed.

Focused local and actual MinIO qualification passed 15 tests and 59 assertions, including a real 1,002-object reconciliation. Additional original-age recreation and slider readback HTTP cases passed. The single full PHPUnit run reported 285 tests and 613 assertions, with one sandbox loopback-bind error in the existing member HTTP test, 25 endpoint-dependent skips and ten deprecations. The affected member test was rerun escalated and passed. Existing PHP/JS/HTTP checks, both canonical Doctrine Compose stacks and the exact browser-disabled CI application harness passed.

Focused Chrome media journeys passed on local and S3, requiring present/loaded images and verifying publication, withdrawal denial, authorized preview and republishing. Post-logout private-route denial was observed directly. Broad pre-existing browser helpers encountered full-load synchronization timeouts; the focused media helper uses DOM readiness plus explicit image-loaded assertions. The unrelated general browser timeout remains a qualification, separately from the passing actual media journeys. The controller inspected S3 journal-publication and staff-carousel screenshots.

The final slider upload readback slice was added during self-review, after the broad harness run, and passed its targeted local/S3 HTTP checks. All exact commands and output pointers are in `task-3-report.md`. Root owns the active disposable MinIO service on port 62941 until review reproduction completes.

### Ticket 03 independent review findings

Standards review found two P2 responsibility violations: slider lifecycle transaction/cleanup decisions in its controller, and unconditional retained-cover policy in the SQL repository. One optional P3 primitive-identity smell was noted. Spec review found one P1: the default generic retry path still included purge intents, allowing an ordinary member save to continue a purge without backup acknowledgement. A synthetic omitted-argument reproduction confirmed deletion of a pending member/object.

The findings were sent together to the implementer for a focused fix wave and independent scoped re-reviews. Optional identity refactoring is deferred unless needed for correctness. The two axes remain separately reported in `standards-review.md` and `spec-review.md`.

### Ticket 03 reviewed correction

Signed correction `5c6165b7c4147e62db904f5865fd8dd99667b9ba` makes ordinary retries exclude purge intents by default. Explicit bulk purge opt-in additionally checks the backup acknowledgement inside the lifecycle API. The targeted manual purge retains its explicit action contract. A missing-argument regression and an actual ordinary staff-save regression first reproduced the bypass, then passed.

The new `SliderService` owns verified publication, locking/rollback and removal scheduling. The controller retains request, CSRF, input and response handling. Historical-cover retention now lives in `FileLifecycleService`, while its repository returns actual database reference information. The optional P3 representation of expiry identity in a reason string remains deferred and documented.

Affected local lifecycle checks passed 14 tests and 55 assertions. The actual S3 lifecycle/member/media selection passed 19 tests and 64 assertions. Both local and S3 HTTP/Chrome media and jobs proofs passed again after the correction, including backup-gated retries and explicit logout/private-route denial. The correction signature was verified independently. Exact commands and logs are in the report's `Independent review fixes` section.

Scoped Standards re-review confirmed both P2 findings resolved, with no new issue. Scoped Spec re-review confirmed the P1 resolved and found no new Spec problem. The independent axis reports are `standards-fix-review.md` and `spec-fix-review.md`. Source is ready for exact-head hosted CI and PR delivery; actual R2 and production remain later qualification.

## R2 and production

Actual bucket credentials, live file/reference inventory, backup restorability and measured maintenance duration require operational qualification. Production execution requires explicit approval of the selected resources and concrete transition. Source-copy deletion remains a separate decision after migration validation.
