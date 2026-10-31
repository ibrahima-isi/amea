# Repository cleanup

The approved [spec](.scratch/repository-cleanup/spec.md) and [ticket 01](.scratch/repository-cleanup/issues/01-remove-obsolete-artifacts.md) are implemented and published in [PR #19](https://github.com/ibrahima-isi/amea/pull/19), targeting `dev`. The unresolved [orphan-test triage](.scratch/chores/triage-orphan-tests/spec.md) remains open.

On 2026-10-30, PHPUnit passed 143 tests / 250 assertions with the same 10 baseline deprecations; the synthetic MySQL CLI emitted its pre-existing password-on-command-line warnings; the four standalone suites passed 47, 186, 78 and 24 checks. HTTP journeys passed 35 and 28 checks. Fresh-profile browser runs passed their combined 79 and 89 HTTP/browser checks. The disposable two-host Docker rehearsal passed with 433 PASS lines and cleaned its owned resources. PHP syntax, migration access assertions, workflow structure, links and diff whitespace were checked. Logs are local to the implementation environment.

Independent task and broad branch reviews found no blockers; a minor stale queue troubleshooting reference was corrected. No production deployment, DNS or real SMTP qualification is claimed.

The merged [association journal tracker](.scratch/association-journal/completion.md) retains its own milestones and evidence.

After PR #18 merged, the cleanup was rebased onto `87d6a19`. On the rebased tree PHPUnit passed 159 tests / 310 assertions (the same 10 deprecations); standalone suites passed 48/186/78/24, HTTP 35/28, and browser runs 79/89 combined checks. The synthetic journal-inclusive Docker rehearsal passed 557 PASS lines, including jobs, rollback and restore, and cleaned its owned resources. It emitted the existing synthetic MySQL CLI password warning. PHP syntax, tracked Markdown links, deletion manifest equality and whitespace checks passed.

## Resumption and delivery

On 2026-10-30, fresh-context Standards and Spec reviewers independently examined `87d6a19...17e3b10`: zero actionable findings on each axis. See the [review record](.scratch/repository-cleanup/review.md). All 131 deletions (7,631,988 bytes) match the approved manifest. Application code, journal files, dependencies and executable tests are unchanged. Subsequent delivery edits only update this record and the cleanup tracker.

The primary checkout and visual-preview worktree match their preservation snapshot. The journal worktree retains its untracked runtime files and clean tracked tree; its historical head had already advanced to merged `87d6a19` before this resumption. The journal's own browser QA remains open; registration/token browser checks do not close it.

Remote validation is recorded in [PR #19 checks](https://github.com/ibrahima-isi/amea/pull/19/checks). Check the current head SHA and all three jobs (`test`, `php-tests`, `apps-rehearsal`); an earlier successful run does not qualify a later documentation commit. The PR is submitted for user review. Actual merge, integration into local `main`, and temporary-branch/worktree cleanup remain conditional on a later remote `dev` merge. No auto-merge or remote `main` push is performed.
