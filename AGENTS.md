# Repository Guidelines

## Canonical Instructions and Language

This file is the single source of durable project instructions. `CLAUDE.md` and `GEMINI.md` each contain only `@AGENTS.md`. Keep ordinary documentation, domain records and task tracking in their own documents; avoid duplicating agent rules there.

Use English for first-party technical documentation, code comments, internal identifiers, new commit messages and PR text. Preserve French application and email content, exact business values, existing URLs, form/JSON/session keys and other compatibility contracts. English prose may quote those literals and proper names. Preserve published Git history, human data, legal sources and third-party/generated content.

Keep replies concise and use the conversation's language. Use interactive input tools for user-facing design, scope and clarification questions; wait for answers before dependent decisions or work. Execute only authorized scope: ticket readiness is not permission to start another ticket.

## Project Structure & Module Organization

AMEA manages AEESGS membership for Guinean pupils, students, and interns in Senegal. The interface is French. The stack uses PHP 8.5, MariaDB/MySQL, Bootstrap 5, vanilla JavaScript, Tagify, Twig, AWS S3 SDK (Cloudflare R2 / S3-compatible storage), Doctrine Migrations, and PHPMailer via Brevo SMTP. The public website and administration share one Composer package, business code, database, and files. They have independent document roots and route boundaries.

- `apps/web/public/` and `apps/backoffice/public/`: thin HTTP entry points. `src/Core/Router.php` owns the route inventory and dispatch.
- `src/`: PSR-4 classes under `Amea\`, including `Controller/`, `Service/`, `Repository/`, `Model/`, `Command/`, `Migration/`, `Storage/`, `Config/`, and `Core/`.
- `scripts/`: thin CLI entry points backed by classes in `src/Command/`, plus `scripts/stellar/` for native split hosting packaging, verification, and atomic release deployment.
- `templates/`: Twig views and HTML email templates. `assets/` contains CSS, vanilla JavaScript and images.
- `tests/`: PHPUnit suites, standalone regressions, and HTTP/browser rehearsals.
- `database/`, `migrations/`, and `schema.sql`: database definitions and history. `config/database.php` supports historical migration rehearsal.

Place new classes in `src/` following PSR-4. Use `Amea\Core\Container` for the shared PDO connection and storage backend, and current Twig views for rendering.

## Build, Test, and Development Commands

- `composer install`: install locked PHP dependencies, including development tools.
- `php scripts/migrate.php migrate`: initialize or migrate the explicitly selected database; export its `DB_*` connection variables first.
- `docker compose up -d --build --remove-orphans`: start the local Docker stack. See [local Docker setup](docs/testing/README.md#local-docker-stack) for URLs and certificate setup.
- `./vendor/bin/phpunit`: run Unit and Integration suites from `phpunit.xml`.
- `php tests/test_oop_unit.php`: run a standalone regression script; CI also runs `test_oop_integration.php`, `test_permissions_unit.php`, and `test_password_reset_security.php`.
- `php -l path/to/file.php`: check changed PHP syntax.
- `docker compose -f compose.dokploy.yml build web backoffice`: build the separate PHP 8.5 Apache application targets with the required deployment environment configured. Building is distinct from deployment approval.

## Coding Style & Naming Conventions

Match adjacent code: four-space PHP indentation, PascalCase classes, camelCase methods, and namespace-aligned filenames. Keep business rules in services and SQL in repositories. No formatter configuration is supplied. Preserve French UI text and exact enum values such as `Masculin`, `Féminin`, `ELEVE`, `ETUDIANT`, and `STAGIAIRE`. Support `[data-bs-theme="dark"]` through CSS variables; `assets/js/theme.js` handles switching. Keep `edit-student.php` visually and logically aligned with registration.

## Theme Requirement

First visit always renders in light mode regardless of the operating-system theme, with a valid explicitly saved light/dark choice taking priority. Preserve manual switching, French action labels, pre-render initialization, keyboard accessibility, and the light no-JavaScript fallback. Invalid or unavailable storage must allow a usable light fallback and page-local switching.

## Testing Guidelines

Use PHPUnit 11 with `*Test.php` files under `tests/Unit/` or `tests/Integration/`; standalone checks use `test_*.php`. Existing tests use SQLite fixtures. Add regression coverage for changed behavior. No numeric coverage threshold is configured. Run relevant suites and the CI command set before requesting review. SQLite-backed tests do not require MySQL. Keep all GitHub Actions workflows in `.github/workflows/ci.yml`; consult that file for actual triggers.

## GitHub Flow

`main` is the only long-lived branch locally and on GitHub. Every change to `main` goes through a GitHub pull request. Never commit directly to `main`, push directly to it, force-push it, or merge a development branch into it locally. Never bypass branch protection.

**Explicit commit and push authorization:** Commit only when the user explicitly says so. Push only when the user explicitly says so. Leave completed work uncommitted in the task worktree until the user asks to commit or push.

1. Check Git status and fetch `origin`. Create a short-lived branch from the latest `origin/main` in an isolated worktree. Use `feature/<description>`, `fix/<description>`, `refactor/<description>`, `chore/<description>`, or `docs/<description>`. Include the Jira key when available. Keep the original checkout untouched.
2. Implement and validate in the worktree. Wait for explicit user instruction before committing. When committing, use concise Conventional Commits (`feat:`, `fix:`, `refactor:`, `docs:`, `test:`, `chore:`, `ci:`, `security:`, `infra:`), cryptographically sign every commit (stop if signing fails or a commit is unsigned), and omit Co-Authored-By and Claude attribution trailers.
3. Push only when the user explicitly says `push` (applying the push workflow below) or explicitly asks to push. Open a PR only when the user explicitly requests one, invokes the `pr` skill, or says `push`.
4. Target `main` for every PR. Describe the change, link relevant issues, report validation, and include screenshots for UI changes. Prefer squash merging through GitHub after the required checks pass on the latest PR revision and all branch protections are satisfied. A PR creation request does not authorize a merge. Merge only with explicit user authorization and never enable auto-merge.
5. After verifying the PR actually merged into remote `main`, synchronize local `main` by fast-forward only in an isolated worktree. If it diverged, preserve its commits and report the divergence. If any task branch or worktree still needs cleanup, verify the merged PR covers the task branch tip first. Obtain explicit approval before deleting any pre-existing branch. Preserve unmerged work and published history.

Keep GitHub's default branch set to `main`. Its ruleset must require PRs and the CI checks defined in `.github/workflows/ci.yml`, enforce signed commits, and block force pushes and deletion without bypass actors. Keep squash merging available, auto-merge and automatic branch deletion disabled, and the minimum approving-review count at zero for the sole maintainer.

### Push workflow

Treat a request to `push` as authorization for this sequence. Apply any explicit user exceptions to the affected steps.

1. If the task has a spec, ensure completed acceptance-criteria checkboxes in each ticket (`issues/<NN>-<slug>.md`) and completed ticket checkboxes in `.scratch/<feature-slug>/completion.md` are checked off.
2. If uncommitted task changes remain, commit them with signed commits. Use the `pr` skill to prepare the PR, push the task branch to `origin`, and open a PR to `main` (or update the existing open PR for the branch).
3. Verify that the open PR points to the pushed tip and that removing the local checkout would lose no work. Remove the isolated worktree and local branch created for this task. Keep the remote branch available for the PR and preserve any pre-existing branches and worktrees.
4. Report the PR link, changes, validation results, and any cleanup still pending.

Merging and production deployment require separate explicit authorization.

## Security & Collaboration

Configure database credentials, Brevo SMTP credentials, storage credentials, and application origins in untracked `.env` (or private `0600` `AMEA_CONFIG_FILE` on native hosting); no `.env.example` currently exists. PHP needs `pdo_mysql`, `fileinfo`, `mbstring`, `gd`, `curl`, `iconv`, `openssl`, and `simplexml`; tests also need `pdo_sqlite`. Preserve CSRF checks, escaped output, prepared queries, and authenticated private-document access. Validate upload MIME types strictly. Student photos, CVs, and IDs use `uploads/students/`; serve private downloads through `download.php` and staged previews through authorized endpoints. Preserve session authentication, `admin`/`user` role checks, host-scoped public/staff session isolation, secure cookies, and the 30-minute session lifetime. Obtain explicit approval for production changes; repository validation is not production deployment or live-data qualification.

## Database Changes

Add new Doctrine versions in `src/Migration/Version/` and keep `schema.sql` aligned. Preserve applied versions and the historical `migrations/` scripts as reference. Both Compose stacks run pending Doctrine versions through the one-shot `migrate` service before the applications and jobs start. Outside Docker, export the selected database's `DB_*` variables and run `php scripts/migrate.php migrate`.

For an existing database, follow [enrollment](docs/operations/doctrine-enrollment.md) before startup. For schema changes, interruptions or reconciliation, follow [migration recovery](docs/operations/migration-recovery.md): separate nontransactional DDL from transactional data versions, verify a restorable backup, and preserve the shared database lock and durable recovery markers.

## Local Issue Tracker

Specs and work items use local Markdown, not remote issues or labels. For a skill's "publish to the issue tracker", create the corresponding local file. For "fetch the relevant ticket", read that file, including its appended comments. Keep specs/tickets version-controlled unless the user explicitly requests disposable tracking.

- One feature directory: `.scratch/<feature-slug>/`; its spec is `spec.md`. Keep feature designs/plans alongside it; `docs/` contains maintained guides and ADRs.
- One implementation ticket per `issues/<NN>-<slug>.md`, numbered from `01`, with blockers numbered before dependents; never combine all tickets in one issue file.
- Record triage in a `Status:` line near the top. Append conversation under `## Comments` at the bottom.
- Each implementation ticket declares blockers and acceptance criteria as Markdown checkboxes (`- [ ]`). Start only after every blocker is verified complete. `ready-for-agent` describes specification readiness, not execution permission or completion.
- **Completion checklist (`completion.md`):** Right after creating `spec.md` and the ticket files, create `.scratch/<feature-slug>/completion.md`. Its content is strictly a Markdown checkbox list of the feature's tickets (for example, `- [ ] [01 — Ticket title](issues/01-slug.md)`). Omit completion proofs, test logs, and explanatory prose.
- **Progress updates:** As each acceptance criterion in `issues/<NN>-<slug>.md` is completed, check its box (`- [x]`). When all acceptance criteria for a ticket are completed, check that ticket (`- [x]`) in `.scratch/<feature-slug>/completion.md`.
- **Post-spec memory sync:** After all tickets in a spec are completed, read the project memory (`.scratch/README.md`, `AGENTS.md`, `README.md`, `GLOSSARY.md`, and relevant `docs/` guides and ADRs) and update it so documentation matches the real repository state. Remove completed feature directories from `.scratch/` once their tracking is closed and record them in `.scratch/README.md` with their pinned Git revision.

### Triage Roles

Use these exact local `Status` strings when a skill names a canonical triage role; create no remote labels:

| Status | Meaning |
| --- | --- |
| `needs-triage` | A maintainer must evaluate the issue. |
| `needs-info` | Waiting for information from the reporter. |
| `ready-for-agent` | Fully specified for agent implementation, subject to authorization and blockers. |
| `ready-for-human` | Requires human implementation. |
| `wontfix` | Will not be actioned. |

### Wayfinding Lifecycle

Wayfinding uses `.scratch/<effort>/map.md` with Notes, Decisions-so-far and Fog, plus one child ticket per `issues/NN-<slug>.md`. Record the child question, `Type:` (`research`, `prototype`, `grilling` or `task`), `Status:` (`claimed` or `resolved`) and numbered `Blocked by:` edges.

The frontier contains open, unblocked, unclaimed children; every listed blocker must be `resolved`, and the first eligible number wins unless the user selects work. Claim by saving `Status: claimed` before work. Resolve by appending `## Answer`, setting `Status: resolved` and adding a summary/link context pointer to the map's Decisions-so-far. These lifecycle states belong to wayfinding; implementation progress stays separate from the five triage roles.

## Domain Documents

Before domain exploration or spec/ticket writing, read root `GLOSSARY.md` and applicable ADRs under `docs/adr/`. If `GLOSSARY-MAP.md` exists, follow it to the relevant context glossaries and context-scoped ADRs, including any `src/<context>/docs/adr/`. If these documents are absent, proceed silently rather than requesting their creation up front. Create domain records lazily only when terms or decisions are actually resolved.
