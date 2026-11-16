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

### Ticket 03 delivery

[PR 42](https://github.com/ibrahima-isi/amea/pull/42) delivered Ticket 03 into remote `dev`. All four jobs passed on exact PR head `48dca0cf93841edb1fb605cc732926f228d6bda8` in [CI run 43474391582](https://github.com/ibrahima-isi/amea/actions/runs/43474391582). Hosted PHPUnit passed 287 tests and 615 assertions, with 25 endpoint-dependent skips and ten existing deprecations. This complete hosted run also verifies the local sandbox-bound HTTP case that had required an individual escalated retry.

The PR actually merged at `2026-11-13T17:56:28Z` as `f532cd4a653fc87d651c8c33d2de6b081f5c774e`. GitHub reports the merge signature verified with reason `valid`. Ancestry verified the tested head in remote `dev` and local `main`. An isolated worktree fast-forwarded local `main` from `57de6f7` to that actual merged result. Production source, tests, routing and operations guides matched remote `dev` exactly before this documentation-only closeout.

[Post-merge CI run 43477730651](https://github.com/ibrahima-isi/amea/actions/runs/43477730651) passed all four jobs on exact merge commit `f532cd4a653fc87d651c8c33d2de6b081f5c774e`, including Docker/recovery and MariaDB/Compose startup gates.

The task's source worktree and branch were removed after clean-status and ancestry checks. Ignored contents were generated dependencies, PHPUnit/Python/Twig caches and an empty task-created storage lock. The original checkout and Composer refactor worktree were preserved. The task-owned MinIO project and its synthetic buckets were removed with its own Compose configuration. A final Docker inventory confirmed only the pre-existing MeetStudent MinIO remained on port 62182. Remote branch inventory confirmed `dev`, `main` and the unrelated Composer branch, with the task branch removed.

The broad browser full-load check timed out in this environment. Focused actual media Chrome journeys passed on both backends, and the exact application CI harness passed with its configured browser-skip setting. The optional expiry-identity representation remark remains deferred. These are the qualification limits for this delivery, distinct from actual R2 and production qualification in later tickets. Tickets 04–06 require separate authorization. Detailed local evidence remains under `/private/tmp/amea-storage03-artifacts/`.

## Remaining-spec integration

On 2026-11-14 the user invoked `implement-spec` for all remaining tickets, superseding the earlier one-ticket execution boundary. The integration branch is `integration/object-storage-migration`, in `/private/tmp/amea-object-storage-integration`, starting from signed local-main closeout `b54d4613f311f54cb7c4adf7e1d5c271819165d9`.

The task graph is `04 -> 05 -> 06`, with Ticket 03 already delivered. Each implementation starts from the current integration tip in a fresh worktree. A merger agent integrates each completed ticket with a signed merge. The feature's existing completion record remains the single execution ledger. Local reports and detailed evidence are under `/private/tmp/amea-storage-integration-artifacts/`.

Ticket 04 is the current implementation frontier. R2 test-bucket configuration for Ticket 05 and the actual deployment/access details for Ticket 06 were requested while independent coding continues. The initial local environment check found no storage-provider configuration. Actual production transition requires the selected resources, verified recovery point and concrete operator approval described by the already approved specification and AGENTS.md.

Ticket 06 explicitly consumes merged, qualified implementation. Therefore code review, latest-head CI and the implementation PR merge must precede its live transition, even though the generic `implement-spec` sequence places final review after all work. The same integration branch will carry the implementation and subsequent operational evidence, advancing to the actual merged revision before production qualification. This follows the ticket dependency and keeps the live approval attached to a concrete reviewed revision.

The deferred closeout publication is [PR 43](https://github.com/ibrahima-isi/amea/pull/43). Its signed closeout commit is already included in this integration ancestry. Ticket 04 starts in `/private/tmp/amea-object-storage-04` from `0bb162e`; its detailed evidence is under `/private/tmp/amea-storage04-artifacts/`.

The user confirmed a production VPS managed through Dokploy, with SSH and GitOps/CI/CD, and is preparing the R2 test bucket. The concrete Dokploy address/application and private test credential-file path remain requested. Connected tools expose no Dokploy/Cloudflare connector. Only names of existing GitHub configuration were inspected, with no secret values read or printed.

The user then supplied a Dokploy project URL. The assistant browser reached its sign-in page, and the user declined the browser login. No authenticated deployment configuration or production state was accessed. The access purpose was clarified as the operational Ticket 06 transition, while Tickets 04/05 coding can continue independently. An operator-executed transition procedure was offered; actual execution and its evidence remain distinct from preparing that procedure. Private target pointers are stored outside Git under the integration artifact directory.

Read-only Ticket 05 exploration saved `/private/tmp/amea-storage-integration-artifacts/05-writer-map.md`. It identified session mutation before controller dispatch, exit-based redirects, nested lock/session ordering and missing session/S3 coverage in the existing backup harness. These findings guide the gate/backup implementation after Ticket 04 integrates.

### Ticket 04 integration

Signed implementation `f5ea343af3d2be91785caca8ec426b4a8b4a0d94` adds complete raw local/S3 inventory, private JSONL manifests and atomic checkpoints, resumable content-verified transfer, current DB/session/ledger reference audit, reviewed guarded corrections and current-state reverse-copy. Source is retained. Same-namespace S3 aliases and overlapping local upload roots are refused. Unknown/special entries and ambiguous or missing references remain explicit unresolved evidence.

The final exact-code migration suite passed 18 tests and 155 assertions with no skips. Its final affected suite passed 115 tests and 486 assertions. An earlier full run passed 303 tests and 878 assertions before the last namespace/current-presence/remapping guards, which received targeted reruns. PHP standalone, JS theme, three HTTP and Linux invalid-UTF-8/FIFO checks passed. Native MariaDB corrections used the existing migration advisory lock and member lock on the same PDO, including rollback and lock-release proof. Actual MinIO transfer/read-back and newly submitted document reverse-copy passed. Detailed evidence and consumed API/CLI contracts are in `/private/tmp/amea-storage04-artifacts/task-4-report.md`.

A fresh merger agent created signed merge `91a5f7c80e31382e6b97a894da4b3e2912c3bceb` into the integration branch. It verified baseline ancestry, source and merge signatures, exact source-tree equality and byte-for-byte preservation of the controller-owned completion record. Its report is `/private/tmp/amea-storage-integration-artifacts/04-merge-report.md`.

Ticket 04's repository implementation is locally qualified and integrated. Final whole-integration review, latest-head hosted CI and the eventual implementation PR merge remain separate delivery evidence. Ticket 05 is now the implementation frontier. R2 test-bucket qualification and the actual production transition are still operational prerequisites, with the user preparing the bucket and declining assistant-browser production login.

The integration draft is [PR 44](https://github.com/ibrahima-isi/amea/pull/44), targeting `dev`. Its first exact head `6201369b6e9144afda2a36565130dbcdac546af4` passed all four jobs in [CI run 43655924451](https://github.com/ibrahima-isi/amea/actions/runs/43655924451). This qualifies the Ticket 04 integration snapshot, separately from the future Ticket 05/final head. Publication used the existing SSH GitHub access with a TTY, which was verified without changing credentials.

### Ticket 05 in progress

The fresh worktree `/private/tmp/amea-object-storage-05`, branch `feat/object-storage-05`, starts from `6201369`. The shared synthetic services remain under the root-owned integration namespace, with a dedicated `amea_storage05_test` database separate from Ticket 04's qualified database. Initial gate RED/GREEN evidence includes admission/draining, restart closure, nested calls, exit-safe staged-session persistence and actual HTTP/CLI mutation guards. Final code/runtime, R2 qualification and merge evidence will be recorded separately.

### Ticket 05 repository integration and open qualifications

Signed source `7299837d141ab6bd5d58d877d007858d4d6de517` implements the persistent admission/drain gate, session-safe HTTP/CLI guards, final-check/resume and runtime target attestations, coordinated snapshots and isolated restore/reopen, current-state rollback rehearsal, deterministic S3 CI and the dedicated R2 qualification harness. The source signature was independently verified.

The full suite ran once with synthetic MariaDB/S3 and passed 311 tests and 931 assertions, with two opt-in skips and ten inherited deprecations. Final affected checks passed 29 tests and 209 assertions, followed by six final gate/recovery tests with 48 assertions. Standalone PHP, theme, HTTP, both deterministic S3 providers and canonical Doctrine Compose checks passed. Actual local/S3 Compose/Chrome runs exercised the shipped capture/restore/reopen CLIs with baked public/staff/jobs reports, paused private reads, restored sessions and a pre-pause registration confirmed after reopening. Snapshots remain private in the artifact directory. Simulated outage/missing-object/ack-loss cases are identified as injections in the detailed report.

A fresh merger created signed merge `07c142c11aa96bdeb326659d0c1f271368275472`, preserving both the source tree exactly and the dirty controller-owned tracker. Its report is `/private/tmp/amea-storage-integration-artifacts/05-merge-report.md`. Full implementation/CLI/API and evidence pointers are in `/private/tmp/amea-storage05-artifacts/task-5-report.md`.

Actual R2 qualification remains unchecked. The user selected `amea-sandbox` and confirmed generating keys, but the prepared private config still has empty endpoint/access/secret fields at the last check. No actual R2 request has been made. The JPEG path initially supplied was not read because OS access was denied and no image was transmitted.

The user clarified that production deploys from `main` through a pushed Git tag. Repository work continues on the integration branch and PR to `dev`; no release tag has been created or pushed. Exact integration head `6615b7890896687c75bcf71752b17a0c5fd643bb` passed all five jobs in [CI run 43761086439](https://github.com/ibrahima-isi/amea/actions/runs/43761086439), including deterministic S3/cutover/recovery. Global reviewers were interrupted by an account quota before writing results; fresh independent Standards/Spec reviews resumed on the same unchanged head on 2026-11-16.

The implementer additionally flagged an unqualified recovery edge: snapshot reference validation may reconstruct inventory without the earlier reviewed missing-reference dispositions. The global Spec review must verify it against the approved recovery-or-disposition contract. Ticket 05 remains operationally incomplete until this review/fix and real R2 evidence are resolved. Ticket 06 has not executed; no authenticated production access or changes occurred.

### Global review and corrective integration

The independent Standards review found zero hard violations and one optional duplicated restored-object verification heuristic. Spec review confirmed one P2: reviewed historical missing-reference dispositions were not carried into coordinated snapshot reference validation. Its synthetic reproduction passed ordinary migration verification but failed snapshot capture.

A fresh corrective implementer preserved and revalidated the original manifest/checkpoint, original selection and exact expected-old DB/session exceptions through capture, isolated restore and reopening. Only captured session owners are rebound. New, moved, changed or tampered references and newly missing required bytes remain rejected. Earlier version-one archive behavior remains strict. The exact affected suite passed 43 tests and 241 assertions, including dedicated MariaDB/MinIO and actual capture/restore/reopen CLI proof. Source/current-state reverse-copy rehearsal passed again. The detailed report is `/private/tmp/amea-storage-integration-artifacts/global-fix-report.md`.

Both independent scoped re-reviews approved the corrective artifact with no new actionable finding. The indexed artifact and final signed commit diff match SHA-256 `2d71c7f4f708399565661cd2b15299905a84adac2c0f13da6451e4bf68932bbb`. The configured 1Password helper failed twice; after verifying the configured public key was already available through the SSH agent, a per-command standard OpenSSH signer created signed source `09ea38470c74a761d44fd65933b7614a261ed417` with the same key. Persistent configuration and signing requirements were unchanged. Verification passed.

A fresh merger integrated it with signed merge `7c0fbe5a94c980eee66ca92111c5f60ce65b4359`, verifying source-tree equality, matching reviewed diff and preservation of the controller-owned tracker. Reports are `global-standards-review.md`, `global-spec-review.md`, their `*-fix-review.md` counterparts and `global-fix-merge-report.md` in the integration artifacts. Final latest-head hosted CI remains to be checked after publication. Actual R2 qualification remains open because the three required private values are still empty at the last check. Ticket 06 has not run.

### Reviewed code handoff and external execution boundary

Implementation snapshot `6a822ab6f773665a0a217426bb057af63a60a431` passed all five jobs in [CI run 43978429294](https://github.com/ibrahima-isi/amea/actions/runs/43978429294), including deterministic S3/cutover/recovery, system Docker/restore and Doctrine Compose. Both independent review axes and the corrective re-reviews are approved. The remaining verification-duplication heuristic is optional and documented.

The repository implementation for Tickets 04/05 is integrated on the single branch and remains reviewable in draft PR 44. Ticket 05's actual R2 acceptance is still open: `amea-sandbox` is selected, but endpoint/access-key/secret values in the private test config were still empty at the final local check. Its harness has not contacted R2. The user received the private template and a local editor command; secrets remain outside Git and chat.

Ticket 06's real production inventory, verified live backup, selected production bucket, maintenance approval, served deployment and transition evidence are not executed. Production deploys `main` through a pushed Git tag, as confirmed by the user. No tag or production change was made. The user declined browser production login, so operator execution remains a separate required step. Preparing code/procedures is not that execution, and no remaining operational ticket is marked complete merely from these code results.

## R2 and production

Actual bucket credentials, live file/reference inventory, backup restorability and measured maintenance duration require operational qualification. Production execution requires explicit approval of the selected resources and concrete transition. Source-copy deletion remains a separate decision after migration validation.
