# Resume point

Where to pick up work. Read this at the start of every session. Rewrite it whenever a resume point is stated and as the last step of every task, shipping the update in that task's PR. This file holds the current state only; history lives in git and in the Change log of `AGENTS.md`.

**Last updated:** 2026-10-21, after PR #8 and `resume-file` ticket 02.

## Repository state

- `main` is production, at `325fe14` (last deploy 2026-06-15, CI green). `dev` is `main` plus squash commits for the agent setup (#5), CI on pull requests (#6), the promotion rules (#7) and the resume file. All of it is docs, tracker files and a CI workflow; no application code.
- GitHub has only `dev` and `main`. Ruleset `stop-push-to-main-and-dev` guards both: PR required, squash only, signed commits, linear history, no force-push or deletion. Admins can bypass it; agents do not.
- The user promotes `dev` to `main` personally. Agents stop once `dev` is merged and green.
- Commits are signed with `GitSigningKeyISI`, set repo-locally.
- Tracker (`.scratch/`): `chores/ci-on-pull-requests` done, `chores/agents-md-promotion-rules` done, `chores/resume-file` done (tickets 01 and 02), `chores/triage-orphan-tests` needs-triage.

## Resume from here

1. **Agent, first thing:** test the CI push trigger. `resume-file` ticket 02's PR is merged by the user with the GitHub web button. Check whether a push-triggered `CI` run exists for its squash commit on `dev` (`gh run list --branch dev`), then record the result here. A run means the `gh pr merge` API path is the likely cause: ask the user to merge with the button, or accept PR-time CI alone. No run means the cause is elsewhere: investigate (a `workflow_dispatch` trigger, GitHub support).
2. **User:** promote `dev` to `main` when ready. It is docs, tracker files and a workflow only, so the deploy it triggers changes nothing visible.
3. **Next work item:** triage `chores/triage-orphan-tests`. Run a grilling session with interactive questions, decide per script, then write tickets. Three scripts fail (`test_filter_option_nulls`, `test_students_search_placeholders`, `test_permissions_integration`) and three pass (`test_document_reconcile`, `test_js_string_literal`, `test_kyc_schema`); CI runs none of them. Deleting any script needs the user's explicit approval.
4. **After that:** the product backlog is empty. The next feature or fix comes from the user: grill, then spec, then tickets.

## Open items and risks

- The squash merges of #7 and #8, both made with `gh pr merge`, produced no push-triggered `CI` run on `dev`, and GitHub's event feed shows no push event for them. #6's merge did fire one. Cause unknown; unconfirmed guess: merges through the API do not fire the event, the web button does. No coverage gap: the PR-time check tests the same content, but the post-merge re-test cannot be relied on. Step 1 above tests the guess.
- The `test` check is not a required status check in the ruleset (the user's call).
- `scripts/purge-trashed-students.php` permanently deletes trashed students after 30 days and assumes a database backup runs first. The user confirmed the cron jobs run; a pre-purge backup is not confirmed.
- Untracked files in the primary checkout, not touched: `.phpunit.result.cache` (should be gitignored) and `docs/superpowers/plans/2026-06-13-full-oop-migration.md` (stale, the OOP migration shipped).
- No `GLOSSARY.md` or `docs/adr/` yet. The first `/domain-modeling` session should seed them (member, KYC, trash).
- `GEMINI.md` duplicates the project description and is not kept in sync with `AGENTS.md`.
