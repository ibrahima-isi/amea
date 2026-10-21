# Spec: CI on pull requests

**Status:** ready-for-agent

## Problem Statement

The project is live in production with sensitive member data, and every push to `main` deploys. Tests run only at that last moment, inside the deploy workflow. A pull request into `dev` gets no check at all (PR #5 showed zero checks), so a broken change can be squash-merged into `dev` and only discovered when `dev` is promoted to `main`.

## Solution

Every pull request into `dev` or `main`, and every push to `dev`, automatically runs the same tests that already gate the production deploy. The check turns green when they pass and red when any fails, so problems surface on the PR, before the squash merge and long before a promotion to `main`.

## User Stories

1. As a developer, I want every pull request into `dev` to run the test suite automatically, so that I see failures before merging.
2. As a developer, I want the squashed commit that lands on `dev` to be re-tested, so that a bad squash is caught before `dev` is fast-forwarded into `main`.
3. As the maintainer of a production system holding sensitive data, I want broken code stopped before it reaches `main`, so that the deploy gate is the last line of defence, not the first.
4. As a developer, I want CI to run exactly the tests that gate the deploy, so that a green pull request predicts a green deploy.
5. As a developer, I want a failing test to turn the check red, so that green carries meaning.
6. As a developer, I want a new push to a pull request to cancel its superseded run, so that results are never stale and runner time is not wasted.
7. As the maintainer, I want CI to need no secrets and no external database, so that it is safe to run on any branch.
8. As an agent following AGENTS.md, I want the documented test commands to match what CI runs, so that I run the same checks locally before opening a pull request.
9. As an agent following AGENTS.md, I want the worktree-per-ticket rule to describe how it works in the desktop app, so that I do not hit the cross-worktree write guard.
10. As the maintainer, I want the production deploy workflow left unchanged, so that this work cannot affect deployments.

## Implementation Decisions

- A new workflow dedicated to CI, separate from the deploy workflow.
- Triggers: pull requests targeting `dev` or `main`, and pushes to `dev`. Pushes to `main` stay with the deploy workflow.
- One job mirroring the deploy workflow's test job: Linux runner, PHP 8.4 with the PDO and SQLite extensions, dependency install, the four plain-PHP test scripts, then PHPUnit. Same pinned action versions as the deploy workflow.
- Least privilege: read-only repository contents permission; no secrets referenced.
- Concurrency grouped per ref, cancelling superseded runs.
- The deploy workflow is not modified. Its test list is deliberately duplicated; extracting a shared reusable workflow is deferred because it changes the production pipeline.
- The Commands section of AGENTS.md is corrected: PHPUnit is part of the suite and CI runs it.
- The Git section of AGENTS.md records how worktree-per-ticket works in the desktop app: a session is bound to one worktree and cannot write into another, so each ticket runs in its own session whose worktree is named `{issue-slug}-{NN}`; when creating a worktree from inside another, use an absolute path so it does not nest.
- The check is not made a required status check; branch protection is a separate decision for the maintainer.

## Testing Decisions

- A good test observes the behaviour of the CI check on a real pull request, not the internals of the workflow file.
- Single seam: the workflow run on this ticket's own pull request.
- Red: before the change, a pull request into `dev` reports zero checks (observed on PR #5). Green: the pull request shows the CI check passing. Negative proof: a throwaway commit with a deliberately failing test turns the check red, then is reverted.
- Local pre-check: the same commands pass locally on `dev` (baseline 2026-10-21: four scripts exit 0; PHPUnit 140 tests, 240 assertions, OK).
- Prior art: the test job in the deploy workflow.

## Out of Scope

- Changing the deploy workflow.
- Branch protection and required status checks.
- The six test scripts that CI does not run (tracked in `chores/triage-orphan-tests`).
- A PHP version matrix, coverage reporting, linting or static analysis.

## Further Notes

- CI uses PHP 8.4; the local machine runs 8.5. The baseline passed locally on 8.5 with 10 PHPUnit deprecation notices that do not fail the run.
- `failOnWarning` is enabled in the PHPUnit configuration, so a new warning would turn the check red.
