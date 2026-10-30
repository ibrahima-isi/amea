# Repository cleanup

Status: ready-for-agent

## Intent and agreed decisions

Keep a lean, maintained repository without changing application behavior. Audit the whole repository; remove only files whose purpose is obsolete or whose lack of consumers is supported by evidence. Keep historical material in Git rather than moving it to an archive directory.

The user selected one reviewable PR into `dev`, removal of the bundled UI skill in favor of the existing personal installation, and removal of retired cPanel support. Starting point: `origin/dev` at `23b5c556221c432084288c85988718e5e1f245ef`, after merged PR #17. Worktree: `/private/tmp/amea-repository-cleanup-20261030`.

## Approaches

- Selected: one PR containing the documented removals, documentation repairs, and CI consolidation. Most changes are deletions; one review keeps related link repairs together.
- Alternative: separate documentation/tooling and code/CI PRs. This gives smaller reviews but adds delivery overhead.

## Proposed removal list

1. Entire `.codex/skills/ui-ux-pro-max/`, including its tracked Python caches. The personal skill remains available; its datasets are newer but are not an exact copy of this repository's older web examples. Git preserves the removed version.
2. Historical `docs/superpowers/`, `plans/registration-kyc-workflow.md`, completed `.scratch/ui-and-app-separation/`, completed `.scratch/ui-visual-refresh/`, and the completed chore directories `agents-md-promotion-rules`, `ci-on-pull-requests`, `resume-file`, and `user-merges-prs`.
3. Obsolete root `resume.md`. Replace historical entries in root `completion.md` with the current cleanup's execution and delivery evidence, retaining the tracker convention.
4. Historical per-ticket reports, served-file manifests, and screenshots under `docs/testing/`. Preserve useful reproduction instructions in one current `docs/testing/README.md`; retain the executable test harnesses. Ignore generated screenshot output so running existing browser checks does not reintroduce it as tracked source.
5. Retired `.cpanel.yml` and obsolete cPanel guidance. Remove the speculative, unimplemented `docs/scalability-plan.md` roadmap; update README and the maintained email-queue runbook to describe the supported Docker/Dokploy configuration without claiming any production cutover occurred.
6. Unreferenced CSS: `assets/css/edit-student.css`, `edit-user.css`, `export.css`, `profile.css`, `student-details.css`, and `users.css`.
7. Unreferenced JavaScript: `assets/js/admin-confirmations.js`, `dashboard.js`, `edit-user.js`, `form-validation-alerts.js`, and `manage-slider.js`.
8. Unreferenced `templates/auth/ready.html.twig`. Confirm the asset/template findings against the current cleanup base and the active journal branch before removal.

## Supporting changes

- Consolidate `.github/workflows/deploy.yml` and `migration-validation.yml` into the required `.github/workflows/ci.yml`. Preserve relevant branch events, manual execution, PHP syntax checks, migration access assertions, HTTP journeys, and application rehearsal; avoid duplicate test jobs. Check repository-required status names before changing job names.
- Repair surviving links and stale documentation. Keep feature designs and plans with their local tracker under `.scratch/<feature-slug>/`; reserve `docs/` for maintained guides and ADRs.
- Prevent generated Python caches from being tracked again. Keep documentation of test commands aligned with actual CI.

## Preserved boundaries

Keep Composer dependencies and lockfile, Tagify, application entry points, migrations/schema, operational runbooks, domain glossary/ADR, agent/tracker instructions, meaningful regression tests, and unresolved `.scratch/chores/triage-orphan-tests/`.

Keep the legal source PDF, database-driven photos, and compatibility helpers whose obsolescence is not established. No deletion is justified solely by age or by absence from a text search when dynamic consumers are possible.

PR #18's active association-journal specifications, implementation, and evidence are outside this PR. Preserve both other worktrees and all their uncommitted files. Their future integration may need deleted historical links adjusted; this cleanup does not modify those branches.

Do not access or change production, credentials, uploads, live databases, storage, SMTP, DNS, or existing Docker projects. No schema, business rule, HTTP contract, visible design, or dependency upgrade is included.

## Validation and delivery

- Verify the removal manifest and references on the exact final tree, including dynamic asset/template resolution and tracked documentation links.
- Run PHP syntax checks, PHPUnit, the four CI standalone PHP suites, both isolated HTTP journeys, and the disposable application rehearsal from this worktree. Run existing browser journeys relevant to the removed assets; inspect their output for rendering/load failures.
- Validate workflow YAML/events and preserve the unique migration access checks. Run `git diff --check` and confirm the primary checkout and unrelated worktrees remain unchanged.
- Record precise results and any baseline limitations in root `completion.md`. Create cryptographically signed commits; stop if signing fails. Open one PR targeting `dev` and report checks on its latest commit for user review.
- Integration into local `main` and temporary-branch/worktree cleanup are conditional on an actual later merge into remote `dev`, per repository instructions. Do not push remote `main`.

## Approval

All scope decisions and this concrete removal/supporting-change list were approved through interactive input on 2026-10-30. Implementation is authorized.
