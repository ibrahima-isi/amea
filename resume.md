# Resume point

Where to pick up work. Read this at the start of every session. Rewrite it whenever a resume point is stated and as the last step of every task, shipping the update in that task's PR. This file holds the current state only; history lives in git and in the Change log of `AGENTS.md`.

**Last updated:** 2026-10-21, after PR #9 and `user-merges-prs` ticket 01.

## Repository state

- `main` is production, at `325fe14` (last deploy 2026-06-15, CI green). `dev` is `main` plus squash commits for the agent setup (#5), CI on pull requests (#6), the promotion rules (#7), the resume file (#8, #9) and the merge policy. All of it is docs, tracker files and a CI workflow; no application code.
- GitHub has only `dev` and `main`. Ruleset `stop-push-to-main-and-dev` guards both: PR required, squash only, signed commits, linear history, no force-push or deletion. Admins can bypass it; agents do not.
- The user promotes `dev` to `main` personally. Agents stop once `dev` is merged and green.
- The user merges ticket PRs into `dev` with the GitHub "Squash and merge" button. Agents open the PR, wait for a green `test` check, hand it over, and clean up once it shows `MERGED`.
- Commits are signed with `GitSigningKeyISI`, set repo-locally.
- Tracker (`.scratch/`): `chores/ci-on-pull-requests` done, `chores/agents-md-promotion-rules` done, `chores/resume-file` done (tickets 01 and 02), `chores/user-merges-prs` done, `chores/triage-orphan-tests` needs-triage.

## Resume from here

1. **User:** promote `dev` to `main` when ready. It is docs, tracker files and a workflow only, so the deploy it triggers changes nothing visible.
2. **Next work item:** triage `chores/triage-orphan-tests`. Run a grilling session with interactive questions, decide per script, then write tickets. Three scripts fail (`test_filter_option_nulls`, `test_students_search_placeholders`, `test_permissions_integration`) and three pass (`test_document_reconcile`, `test_js_string_literal`, `test_kyc_schema`); CI runs none of them. Deleting any script needs the user's explicit approval.
3. **After that:** the product backlog is empty. The next feature or fix comes from the user: grill, then spec, then tickets.

## Open items and risks

- Push-triggered `CI` runs on `dev` by merge: #6 yes, #7 no (`gh pr merge`), #8 no (`gh pr merge`), #9 yes (button). GitHub's event feed shows a push event only for the merges that fired a run. The root cause is not proven; the merge policy above avoids it. If agents ever need to merge, a `workflow_dispatch` trigger on `ci.yml` would restore the post-merge re-test.
- The `test` check is not a required status check in the ruleset (the user's call).
- `scripts/purge-trashed-students.php` permanently deletes trashed students after 30 days and assumes a database backup runs first. The user confirmed the cron jobs run; a pre-purge backup is not confirmed.
- Untracked files in the primary checkout, not touched: `.phpunit.result.cache` (should be gitignored) and `docs/superpowers/plans/2026-06-13-full-oop-migration.md` (stale, the OOP migration shipped).
- No `GLOSSARY.md` or `docs/adr/` yet. The first `/domain-modeling` session should seed them (member, KYC, trash).
- `GEMINI.md` duplicates the project description and is not kept in sync with `AGENTS.md`.
