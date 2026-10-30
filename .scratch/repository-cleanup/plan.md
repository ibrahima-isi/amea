# Repository Cleanup Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Remove proven repository clutter and retired cPanel support in one reviewable PR into `dev`.

**Architecture:** Application behavior and data stay intact. Delete obsolete artifacts, consolidate supported guidance, and retain meaningful CI checks in one workflow.

**Tech Stack:** PHP, Twig, vanilla JavaScript, Composer, GitHub Actions, Docker Compose.

**Spec:** [Approved cleanup specification](spec.md)

## Global Constraints

- Keep historical material in Git rather than moving it to an archive directory.
- One reviewable PR into `dev`.
- Worktree: `/private/tmp/amea-repository-cleanup-20261030`.
- Preserve both other worktrees and all their uncommitted files.
- No schema, business rule, HTTP contract, visible design, or dependency upgrade is included.
- Create cryptographically signed commits; stop if signing fails.

## Task 1: Remove obsolete artifacts and repair their maintained consumers

**Files:** Delete the exact categories and paths in the approved specification. Modify `README.md`, `AGENTS.md`, `docs/agents/issue-tracker.md`, `docs/email-queue.md`, `.gitignore`, `.github/workflows/ci.yml`, and `completion.md`. Create `docs/testing/README.md` and this task's numbered tracker record.

**Interfaces:** Consume the existing application/test entry points unchanged. Produce maintained documentation, one CI workflow, and the exact deletion manifest for review. Existing required check contexts are `test` and `php-tests` (repository ruleset `8898075`); both must remain emitted.

- [x] Verify isolation and baseline: `git status --short`, then use the controller's `/private/tmp/amea-cleanup-baseline-*.log` results. The controller installs locked dependencies and runs baseline PHP/HTTP suites before implementation starts.
- [x] Verify each asset/template has no consumer on current `HEAD` or `feat/association-journal`: use `rg` plus inspection of Twig asset construction, controller rendering, and script loading. Keep database-driven photos, legal PDF, compatibility helpers, and unresolved test triage.
- [x] Delete the approved obsolete files only. Record exact removed paths and byte count using `git diff --numstat` / `git diff --name-only --diff-filter=D`; never delete ignored runtime data or other worktrees.
- [x] Replace historical testing reports with one current `docs/testing/README.md`. Preserve actual PHP, loopback HTTP, browser, synthetic two-host, jobs, cache, restore and rollback reproduction commands and their limits. Keep `tests/http/README.md` aligned; ignore generated screenshot/cache output.
- [x] Remove the cPanel hook and host-specific guidance; update README's stale Twig/file-layout/deployment instructions and retain maintained queue/rollout guides. Document the existing Docker/Dokploy options without claiming live cutover or enabling jobs/SMTP.
- [x] Consolidate workflows in `ci.yml`: keep `apps-rehearsal` and `test`; emit required `php-tests` for syntax and the original migration access assertions. Preserve existing PR, push, and manual events, and do not duplicate the full PHP suites across jobs.
- [x] Add the agreed documentation placement rule: feature designs/plans live with local specs under `.scratch/<feature-slug>/`; `docs/` contains maintained guides and ADRs. Replace root completion history with this cleanup's current evidence; retain unresolved work.
- [x] Validate tracked links and deletion consumers, workflow YAML/event structure, PHP syntax, and `git diff --check`. Do not add implementation-mirroring tests for deletions; use existing behavioral suites.
- [x] Run final `./vendor/bin/phpunit --colors=never`, `php tests/test_permissions_unit.php`, `php tests/test_oop_unit.php`, `php tests/test_oop_integration.php`, `php tests/test_password_reset_security.php`, `python3 tests/http/baseline.py`, and `python3 tests/http/tokens.py`. Record output and baseline deprecations accurately.
- [x] Run existing browser checks: `AMEA_BROWSER_CHECK=1 AMEA_REGISTRATION_BROWSER=1 python3 tests/http/baseline.py` and `AMEA_BROWSER_CHECK=1 python3 tests/http/tokens.py`. Use fresh Chrome profiles and synthetic data only.
- [x] Run `AMEA_REHEARSAL_PROJECT=amea-ui-test-cleanup-20261030 AMEA_PUBLIC_PORT=28443 AMEA_STAFF_PORT=29443 AMEA_SCREENSHOT_DIR=/private/tmp/amea-cleanup-browser bash tests/apps/run.sh`. First confirm these ports and restore ports 18448/19448 are free. Never touch existing projects; the harness cleans only its fresh owned source/restore projects. Use sandbox escalation for network/Docker/loopback permissions if needed.
- [x] Self-review scope, evidence, and diff; write a detailed implementer report with results and limitations. Commit using `git commit -S` and verify the signature; stop immediately on failure. Do not push or open the PR: the controller handles final review and authorized PR delivery after task review.

The cleanup was rebased onto merged journal base `87d6a19` before publication; preserve journal files and revalidate the combined tree. Previous reviews cover the earlier base; review the rebased result separately.

## Controller delivery

Review Task 1 for spec compliance and quality, then commission broad branch review. Resolve findings, verify preservation snapshot `/private/tmp/amea-cleanup-preservation.json`, sign any evidence-only changes, and push `chore/repository-cleanup` to open one PR into `dev`. Report latest-head CI for user review. Keep the worktree until an actual later merge; do not push remote `main`.
