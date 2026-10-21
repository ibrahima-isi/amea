# 01: AGENTS.md promotion rules

**What to build:** AGENTS.md matches the new reality on `main` and `dev`. The user promotes `dev` to `main` personally and agents stop once `dev` is merged and green. The GitHub ruleset `stop-push-to-main-and-dev` and the commit-signing key are documented, and the Change log records the decision.

**Blocked by:** None (can start immediately).

**Status:** ready-for-agent

- [ ] AGENTS.md states that the user promotes `dev` to `main` personally and that agents never push to or open pull requests into `main`.
- [ ] AGENTS.md no longer tells agents to run `git push origin dev:main`.
- [ ] AGENTS.md documents the ruleset `stop-push-to-main-and-dev` (PR required, squash only, signed commits, linear history, no force-push or deletion).
- [ ] AGENTS.md documents that commits are signed with `GitSigningKeyISI`, the only registered GitHub signing key, and that any other key is rejected as unverified.
- [ ] The Change log has a new dated row at the top with the instruction and what applied before.
- [ ] Other statements that agents promote `dev` to `main` in AGENTS.md and `docs/agents/*.md` are searched for and reported.
- [ ] The diffs of `.github/workflows/deploy.yml` and `.github/workflows/ci.yml` are empty.
- [ ] Every commit on the pull request shows `verified: true` on GitHub, and the CI check is green.

## Comments
