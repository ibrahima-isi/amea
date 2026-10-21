# 01: CI workflow on pull requests

**What to build:** Pull requests into `dev` or `main`, and pushes to `dev`, run the same test suite that gates the production deploy and report a pass or fail check. The check is proven green on its own pull request and proven able to go red. AGENTS.md matches reality: its Commands section says what CI runs, and its Git section explains how worktree-per-ticket works in the desktop app.

**Blocked by:** None (can start immediately).

**Status:** ready-for-agent

- [ ] A pull request into `dev` shows a CI check that runs the four plain-PHP test scripts and PHPUnit.
- [ ] The check passes on this ticket's own pull request.
- [ ] A throwaway commit with a deliberately failing test turns the check red; it is reverted before merge.
- [ ] After merge, the push to `dev` shows a CI run.
- [ ] The workflow requests read-only contents permission and references no secrets.
- [ ] The deploy workflow is unchanged (empty diff).
- [ ] The Commands section of AGENTS.md states that PHPUnit is part of the suite and that CI runs it.
- [ ] The Git section of AGENTS.md explains the one-session-per-ticket worktree flow and the absolute-path caveat.

## Comments
