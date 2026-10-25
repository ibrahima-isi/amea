# 01: The user merges PRs into dev

**What to build:** `AGENTS.md` says the user merges ticket PRs into `dev` with the GitHub button and agents hand over a green PR, replacing the `gh pr merge --squash` instruction. `resume.md` reflects the finished push-trigger test and the policy.

**Blocked by:** None (can start immediately).

**Status:** ready-for-agent

- [ ] The Git section of `AGENTS.md` no longer instructs agents to run `gh pr merge`, and states the hand-over and cleanup-after-`MERGED` flow.
- [ ] A dated Change log row records the rule and what applied before.
- [ ] `resume.md` records the push-trigger result and the merge policy, and no longer lists the finished test as a pending step.
- [ ] The CI check on the pull request is green.
- [ ] After the user merges the PR with the button, a push run appears for its squash commit on `dev`.

## Comments
