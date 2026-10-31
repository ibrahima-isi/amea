# Repository cleanup

## Closeout state — 31 October 2026

Cleanup and the journal are delivered to `dev`: PR #17, #18, #19 and [#20](https://github.com/ibrahima-isi/amea/pull/20) merged. Final CI for #20 is green on signed commit `09a414c` ([three jobs](https://github.com/ibrahima-isi/amea/actions/runs/41162939038)); [`dev` CI](https://github.com/ibrahima-isi/amea/actions/runs/41163154556) is green on `3033d4b`. Local integration `0343f9d` is signed, with no push to remote `main`.

On 31 October, the user explicitly deferred browser QA and will resume it later. It remains **unvalidated**, with no new automated attempt requested. Remaining checks/procedure are in [journal tracking](.scratch/association-journal/completion.md) and the [browser resumption record](.scratch/association-journal/browser-qa.md). This attempt's disposable fixture is stopped; the retained demo is intact. No production, DNS change or real SMTP delivery performed. Orphan-test triage remains out of scope.

The following sections retain dated history; their mentions of pending/draft PRs are superseded by this closeout state.

The approved [spec](.scratch/repository-cleanup/spec.md) and [ticket 01](.scratch/repository-cleanup/issues/01-remove-obsolete-artifacts.md) are implemented and published in [PR #19](https://github.com/ibrahima-isi/amea/pull/19), targeting `dev`. The unresolved [orphan-test triage](.scratch/chores/triage-orphan-tests/spec.md) remains open.

On 2026-10-30, PHPUnit passed 143 tests / 250 assertions with the same 10 baseline deprecations; the synthetic MySQL CLI emitted its pre-existing password-on-command-line warnings; the four standalone suites passed 47, 186, 78 and 24 checks. HTTP journeys passed 35 and 28 checks. Fresh-profile browser runs passed their combined 79 and 89 HTTP/browser checks. The disposable two-host Docker rehearsal passed with 433 PASS lines and cleaned its owned resources. PHP syntax, migration access assertions, workflow structure, links and diff whitespace were checked. Logs are local to the implementation environment.

Independent task and broad branch reviews found no blockers; a minor stale queue troubleshooting reference was corrected. No production deployment, DNS or real SMTP qualification is claimed.

The merged [association journal tracker](.scratch/association-journal/completion.md) retains its own milestones and evidence.

After PR #18 merged, the cleanup was rebased onto `87d6a19`. On the rebased tree PHPUnit passed 159 tests / 310 assertions (the same 10 deprecations); standalone suites passed 48/186/78/24, HTTP 35/28, and browser runs 79/89 combined checks. The synthetic journal-inclusive Docker rehearsal passed 557 PASS lines, including jobs, rollback and restore, and cleaned its owned resources. It emitted the existing synthetic MySQL CLI password warning. PHP syntax, tracked Markdown links, deletion manifest equality and whitespace checks passed.

## Resumption and delivery

On 2026-10-30, fresh-context Standards and Spec reviewers independently examined `87d6a19...17e3b10`: zero actionable findings on each axis. See the [review record](.scratch/repository-cleanup/review.md). All 131 deletions (7,631,988 bytes) match the approved manifest. Application code, journal files, dependencies and executable tests are unchanged. Subsequent delivery edits only update this record and the cleanup tracker.

The primary checkout and visual-preview worktree match their preservation snapshot. The journal worktree retains its untracked runtime files and clean tracked tree; its historical head had already advanced to merged `87d6a19` before this resumption. The journal's own browser QA remains open; registration/token browser checks do not close it.

Remote validation is recorded in [PR #19 checks](https://github.com/ibrahima-isi/amea/pull/19/checks). Check the current head SHA and all three jobs (`test`, `php-tests`, `apps-rehearsal`); an earlier successful run does not qualify a later documentation commit. The PR is submitted for user review. Actual merge, integration into local `main`, and temporary-branch/worktree cleanup remain conditional on a later remote `dev` merge. No auto-merge or remote `main` push is performed.

## Merge and continuation — 2026-10-31

The user authorized merge and continuation. PR #19 was squash-merged as `4aab39f10ec5f86299a1bdfb7629280a0bdf2468` after verifying all three successful jobs on signed head `9f1296b`. The [merged-dev CI](https://github.com/ibrahima-isi/amea/actions/runs/41152150101) also passed on that exact squash commit.

A signed isolated integration commit `16078350278d6fa913e620e13de0a31b7564aada` contains the merged result; its tree equals `origin/dev`. The user explicitly approved synchronizing the clean primary `main` checkout with that prepared commit. No remote `main` push occurred. The cleanup worktree and temporary branches were removed after verifying `/private/tmp/amea-pr19-20261031.bundle` and the complete `/private/tmp/amea-cleanup-worktree-20261031.tar.gz`; the isolated integration worktree was also archived before removal. Unrelated preview/journal worktrees and mounted runtime files were retained.

Continuation is bounded to the remaining [journal browser qualification](.scratch/association-journal/browser-qa.md), in isolated branch `test/journal-browser-qa` based on merged `4aab39f`. A disposable fixture uses real PHP/Twig/forms with synthetic SQLite accounts and content. The public dark desktop view was observed; repeated native-control timeouts and blank render surfaces prevent full browser qualification so far. Mobile, keyboard and editorial browser actions remain open. The fixture's HTTP checks do not close them. The orphan-test triage remains unchanged.

Follow-up [PR #20](https://github.com/ibrahima-isi/amea/pull/20) stays draft. Independent Standards and Spec reviews on `4aab39f...81472d2` each report zero findings. Its [latest-head checks](https://github.com/ibrahima-isi/amea/pull/20/checks) do not replace the outstanding browser qualification or authorize a production operation.

Fresh local PHP validation passed 159 tests / 310 assertions, standalone 48/186/78/24 and HTTP 35 + 28, with 10 existing PHPUnit metadata deprecations. The final support CI exposed a concurrent Docker copy-up failure on the fresh restore storage volume. The failure was reproduced at trial 7; signed fix `840a507` precreates `public` before full restore startup and passed 100 fresh-volume trials. The complete local run was interrupted after 521 PASS lines and is not a successful full restore; its two owned projects were cleaned. See [diagnosis and exact evidence](.scratch/association-journal/restore-ci.md), then the latest-head CI for the complete final gate. The browser gap remains separate.
