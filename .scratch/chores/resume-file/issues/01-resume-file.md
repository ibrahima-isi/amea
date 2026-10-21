# 01: Resume file

**What to build:** A tracked `resume.md` at the repository root that states the current resume point, and an `AGENTS.md` rule that makes every agent read it first and rewrite it whenever a resume point is stated.

**Blocked by:** None (can start immediately).

**Status:** ready-for-agent

- [ ] `resume.md` exists at the repository root with: last-updated line, repository state, "Resume from here", open items and risks.
- [ ] `AGENTS.md` has a "Resume point" subsection at the top of the Mandatory workflow and a pointer in its opening summary.
- [ ] The rule says: read `resume.md` first; rewrite it whenever a resume point is stated and as the last step of every task; ship it in the task's PR.
- [ ] A dated Change log row records the rule and what applied before.
- [ ] The CI check on the pull request is green.

## Comments
