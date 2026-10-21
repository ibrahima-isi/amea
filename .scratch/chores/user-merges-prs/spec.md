# Spec: The user merges PRs into dev

**Status:** ready-for-agent

## Problem Statement

`AGENTS.md` tells agents to merge ticket PRs into `dev` with `gh pr merge --squash`. On this repository, the two merges made that way (#7, #8) fired no push-triggered CI run on `dev`, while the two made with the GitHub web button (#6, #9) did. The post-merge re-test of the squashed commit is therefore skipped whenever an agent merges.

## Solution

The user merges every ticket PR into `dev` with the GitHub "Squash and merge" button. Agents open the PR, wait for a green `test` check, hand the PR over, and clean up once it shows `MERGED`.

## User Stories

1. As the maintainer of a production system, I want every squash commit on `dev` re-tested after the merge, so that a bad squash is caught before I promote `dev` to `main`.
2. As the maintainer, I want to be the one who clicks merge, so that I see each change land.
3. As an agent, I want a clear hand-off point (green PR, waiting for the user), so that I do not merge in a way that skips the re-test.
4. As an agent, I want the cleanup trigger spelled out (the PR shows `MERGED`), so that branches and worktrees are removed without asking.
5. As a future session, I want `resume.md` to show the result of the push-trigger test and the merge policy, so that I do not repeat the investigation.

## Implementation Decisions

- The Git section of `AGENTS.md` replaces the `gh pr merge --squash` instruction: squash stays the only method; the user merges with the web button; agents hand over a green PR and clean up after `MERGED`.
- A dated Change log row records the rule and what applied before.
- `resume.md` records the push-trigger result (merges by the button fired a run; `gh pr merge` merges did not; cause unproven), drops the finished test step, and states the policy.
- Squash-only stays enforced by the ruleset; nothing about the ruleset changes.

## Testing Decisions

- A documentation change: the observable behaviours are that `AGENTS.md` and `resume.md` agree with each other and with the observed history, and that the CI check on the PR is green.
- Single seam: this ticket's own PR. When the user merges it with the button, a push run on `dev` is expected (the same behaviour seen for #6 and #9).
- Prior art: `chores/agents-md-promotion-rules`, another `AGENTS.md` rule change.

## Out of Scope

- A manual `workflow_dispatch` trigger for `ci.yml`.
- Making `test` a required status check.
- Finding the root cause of the missing push runs.

## Further Notes

- Evidence so far, per merge: #6 push run yes, #7 no (`gh pr merge`), #8 no (`gh pr merge`), #9 yes (web button).
