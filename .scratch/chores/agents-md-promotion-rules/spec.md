# Spec: AGENTS.md promotion rules

**Status:** ready-for-agent

## Problem Statement

AGENTS.md tells agents to promote `dev` to `main` themselves by fast-forward (`git push origin dev:main`). That is no longer possible or wanted. The GitHub ruleset `stop-push-to-main-and-dev` now guards both branches (pull request required, squash only, signed commits, linear history, no force-push or deletion), so the fast-forward push is rejected. The user also decided to promote `dev` to `main` personally, since a push to `main` deploys to a live production system holding sensitive member data. Left as is, AGENTS.md sends agents toward an action that fails and that is no longer theirs to take. It also does not say which key signs commits, and only one registered GitHub signing key is accepted.

## Solution

AGENTS.md states that the user promotes `dev` to `main` personally and that agents stop once `dev` is merged and green. It documents the ruleset and the signing-key rule. The Change log records the decision with what applied before.

## Decisions

- The agent hand-off ends at `dev`: merged by squash, CI green on the push. Agents never push to `main` and never open a pull request into `main`.
- The Git section of AGENTS.md records the ruleset `stop-push-to-main-and-dev` and the signing key `GitSigningKeyISI`, the only registered GitHub signing key; a commit signed by any other key is rejected as unverified.
- Older Change log rows are history and are left unchanged. Only live instructions are fixed.
- Other statements in AGENTS.md or `docs/agents/*.md` that say agents promote `dev` to `main` are reported, not edited, unless they fall under the three edits above.
- Docs-only change: the workflows, `src/` and `tests/` are untouched.

## Out of Scope

- Changing `.github/workflows/ci.yml` or `.github/workflows/deploy.yml`.
- Changing the ruleset itself, branch protection or required status checks.
- Configuring signing on the user's machine (already repointed to `GitSigningKeyISI`).
