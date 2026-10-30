# Repository cleanup

The approved [spec](.scratch/repository-cleanup/spec.md) and [ticket 01](.scratch/repository-cleanup/issues/01-remove-obsolete-artifacts.md) are implemented locally. The unresolved [orphan-test triage](.scratch/chores/triage-orphan-tests/spec.md) remains open.

On 2026-10-30, PHPUnit passed 143 tests / 250 assertions with the same 10 baseline deprecations; the synthetic MySQL CLI emitted its pre-existing password-on-command-line warnings; the four standalone suites passed 47, 186, 78 and 24 checks. HTTP journeys passed 35 and 28 checks. Fresh-profile browser runs passed their combined 79 and 89 HTTP/browser checks. The disposable two-host Docker rehearsal passed with 433 PASS lines and cleaned its owned resources. PHP syntax, migration access assertions, workflow structure, links and diff whitespace were checked. Logs are local to the implementation environment.

Independent task and broad branch reviews found no blockers; a minor stale queue troubleshooting reference was corrected. PR and current-head remote CI remain pending. No production deployment, DNS or real SMTP qualification is claimed.

The merged [association journal tracker](.scratch/association-journal/completion.md) retains its own milestones and evidence.
