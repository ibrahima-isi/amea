# Spec: The user merges PRs into dev

**Status:** ready-for-agent

## Problem Statement

`AGENTS.md` tells agents to merge ticket PRs into `dev` with `gh pr merge --squash`. Of the three merges made that way (#6, #7, #8), only #6 fired a push-triggered CI run on `dev`; the one merge made with the GitHub web button (#9) did. The cause is unknown and no pattern is proven, so the post-merge re-test of the squashed commit cannot be relied on when an agent merges.

## Solution

The user merges every ticket PR into `dev` with the GitHub "Squash and merge" button. Agents open the PR, wait for a green `test` check, hand the PR over, and clean up once it shows `MERGED`.

## User Stories

1. As the maintainer of a production system, I want every squash commit on `dev` re-tested after the merge, so that a bad squash is caught before I promote `dev` to `main`.
2. As the maintainer, I want to be the one who clicks merge, so that I see each change land.
3. As an agent, I want a clear hand-off point (green PR, waiting for the user), so that the merge happens through the one path confirmed to fire the re-test.
4. As an agent, I want the cleanup trigger spelled out (the PR shows `MERGED`), so that branches and worktrees are removed without asking.
5. As a future session, I want `resume.md` to show the result of the push-trigger test and the merge policy, so that I do not repeat the investigation.

## Implementation Decisions

- The Git section of `AGENTS.md` replaces the `gh pr merge --squash` instruction: squash stays the only method; the user merges with the web button; agents hand over a green PR and clean up after `MERGED`.
- A dated Change log row records the rule and what applied before.
- `resume.md` records the push-trigger result per merge (`gh pr merge`: 1 of 3 fired a run; button: 1 of 1; cause unknown, no pattern proven), drops the finished test step, and states the policy.
- Squash-only stays enforced by the ruleset; nothing about the ruleset changes.

## Testing Decisions

- A documentation change: the observable behaviours are that `AGENTS.md` and `resume.md` agree with each other and with the observed history, and that the CI check on the PR is green.
- Single seam: this ticket's own PR. When the user merges it with the button, a push run on `dev` is expected (as seen for #9); a missing run adds a second data point against the button path.
- Prior art: `chores/agents-md-promotion-rules`, another `AGENTS.md` rule change.

## Out of Scope

- A manual `workflow_dispatch` trigger for `ci.yml`.
- Making `test` a required status check.
- Finding the root cause of the missing push runs.

## Further Notes

- Evidence so far, per merge: #6 push run yes (`gh pr merge`), #7 no (`gh pr merge`), #8 no (`gh pr merge`), #9 yes (web button).
- The policy rests on the user's control over each merge plus the button being the one path confirmed to fire the run. It is not a proven fix. The first draft of this spec wrongly counted #6 as a button merge; the user re-confirmed the policy on the corrected evidence.
