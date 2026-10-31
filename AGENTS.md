# Repository Guidelines

## Canonical Instructions and Language

This file is the single source of durable project instructions. `CLAUDE.md` and `GEMINI.md` each contain only `read AGENTS.md`. Keep ordinary documentation, domain records and task evidence in their own documents; avoid duplicating agent rules there.

Use English for first-party technical documentation, code comments, internal identifiers, new commit messages and PR text. Preserve French application and email content, exact business values, existing URLs, form/JSON/session keys and other compatibility contracts. English prose may quote those literals and proper names. Preserve published Git history, human data, legal sources and third-party/generated content. The remaining documentation/identifier conversion is separate work, not a completed result of this instruction change.

Keep replies concise and use the conversation's language. Use interactive input tools for user-facing design, scope and clarification questions; wait for answers before dependent decisions or work. Execute only authorized scope: ticket readiness is not permission to start another ticket.

## Project Structure & Module Organization

AMEA manages AEESGS membership for Guinean pupils, students, and interns in Senegal. The interface is French. The stack uses PHP 8, MySQL/MariaDB, Bootstrap 5, vanilla JavaScript, Tagify, Twig, and PHPMailer via Brevo SMTP. Public and staff applications have independent entry points and route boundaries, with shared business code, database and files; physical separation into repository subprojects remains deferred. Root PHP files are individual HTTP entry points; application code lives in `src/` under the PSR-4 namespace `Amea\`:

- `Controller/`: request handling; `Service/`: business logic; `Repository/`: PDO queries.
- `Core/` and `Model/`: shared infrastructure and domain objects.
- `templates/`: Twig and legacy HTML templates; `assets/`: CSS, vanilla JavaScript, images.
- `tests/`: PHPUnit suites and standalone regression scripts.
- `database/`, `migrations/`, and `schema.sql`: database definitions, evolution, and baseline schema.
- `functions/`: procedural helpers, including `utility-functions.php` and `email-service.php`; `config/`: database and session setup.

Place new classes in `src/` following PSR-4. Entry points bootstrap through `src/bootstrap.php` and use PDO configuration from `config/database.php`. Use current Twig views; older guides describing a custom `TemplateEngine.php` are outdated.

## Build, Test, and Development Commands

- `composer install`: install locked PHP dependencies, including development tools.
- `mysql -u root -p amea_db < schema.sql`: initialize an empty local database.
- `docker compose up -d mysql`: start local MySQL using configured environment variables.
- `php -S localhost:8000`: serve locally after configuring `.env` and initializing the database.
- `./vendor/bin/phpunit`: run Unit and Integration suites from `phpunit.xml`.
- `php tests/test_oop_unit.php`: run a standalone regression script; CI also runs `test_oop_integration.php`, `test_permissions_unit.php`, and `test_password_reset_security.php`.
- `php -l path/to/file.php`: check changed PHP syntax.
- `docker compose -f compose.dokploy.yml build public staff`: build the separate PHP 8.4 Apache application targets with the required deployment environment configured. Building is distinct from deployment approval.
- `docker build -t amea:local .`: build the retained legacy root Dockerfile image.

## Coding Style & Naming Conventions

Match adjacent code: four-space PHP indentation, PascalCase classes, camelCase methods, and namespace-aligned filenames. Keep business rules in services and SQL in repositories. No formatter configuration is supplied. Preserve French UI text and exact enum values such as `Masculin`, `Féminin`, `ELEVE`, `ETUDIANT`, and `STAGIAIRE`. Support `[data-bs-theme="dark"]` through CSS variables; `assets/js/theme.js` handles switching. Keep `edit-student.php` visually and logically aligned with registration.

## Theme Requirement

The approved product requirement is a light first visit regardless of the operating-system theme, with a valid explicitly saved light/dark choice taking priority. Preserve manual switching, French action labels, pre-render initialization, keyboard accessibility and the existing light no-JavaScript fallback. Invalid or unavailable storage must allow a usable light fallback and page-local switching. This is an implementation requirement: editing these instructions does not implement or validate the separate theme ticket.

## Testing Guidelines

Use PHPUnit 11 with `*Test.php` files under `tests/Unit/` or `tests/Integration/`; standalone checks use `test_*.php`. Existing tests use SQLite fixtures. Add regression coverage for changed behavior. No numeric coverage threshold is configured. Run relevant suites and the CI command set before requesting review. SQLite-backed tests do not require MySQL. Keep all GitHub Actions CI and other workflows in the single file `.github/workflows/ci.yml`; consult that file for actual triggers. This is the required convention; existing workflows must be consolidated when workflow changes are in scope.

## Commit & Pull Request Guidelines

Use existing prefixes such as `feat:`, `fix:`, `security:`, `infra:`, `ci:`, and `docs:`. Push feature branches and open PRs into `dev`; `dev` and `main` are protected long-lived branches. Describe behavior, link relevant issues, report validation, and include screenshots for UI changes. Omit Co-Authored-By and Claude attribution trailers.

- Create every commit with a cryptographic signature, including local merge commits. If signing fails or a created commit is unsigned, abort the operation and resolve signing before proceeding. Never disable signing or retry with an unsigned commit.
- Perform all edits, commits, and tests in an isolated Git worktree based on the task's selected starting branch. Keep the original checkout untouched; read-only inspection is allowed.
- Temporary working branches are allowed locally in isolated worktrees and on the remote for PRs. Target every PR at `dev`.
- Enable auto-merge into `dev` only when all required checks pass on the latest PR commit and all repository protections are satisfied. Earlier green checks are insufficient.
- After the PR has actually merged into remote `dev`, integrate its merged result into local `main` from an isolated worktree. A PR being ready to merge is insufficient. Do not push remote `main` as part of this operation.
- After integration, remove the task's temporary worktree and branches when their work is safely preserved. The steady state is only `main` locally and `dev` plus `main` on the remote. Preserve unrelated work and branches; defer their cleanup until safe and authorized.

## Security & Collaboration

Configure database credentials, Brevo SMTP credentials, and application origins in untracked `.env`; no `.env.example` currently exists. PHP needs `pdo_mysql`, `fileinfo`, `mbstring`, and `gd`; tests also need `pdo_sqlite`. Preserve CSRF checks, escaped output, prepared queries, and authenticated private-document access. Validate upload MIME types strictly. Student photos, CVs, and IDs use `uploads/students/`; serve private downloads through `download.php` and staged previews through authorized endpoints. Preserve session authentication, `admin`/`user` role checks, host-scoped public/staff session isolation, secure cookies, and the 30-minute session lifetime. Obtain explicit approval for production changes; repository validation is not production deployment or live-data qualification.

## Database Changes

Add schema changes as new idempotent PHP CLI scripts in `migrations/` and update `schema.sql`. Run migrations manually in numeric order, for example `php migrations/00_init_schema.php`, against the explicitly selected database.

## Local Issue Tracker

Specs and work items use local Markdown, not remote issues or labels. For a skill's "publish to the issue tracker", create the corresponding local file. For "fetch the relevant ticket", read that file, including its appended comments. Keep specs/tickets version-controlled unless the user explicitly requests disposable tracking.

- One feature directory: `.scratch/<feature-slug>/`; its spec is `spec.md`. Keep feature designs/plans alongside it; `docs/` contains maintained guides and ADRs.
- One implementation ticket per `issues/<NN>-<slug>.md`, numbered from `01`, with blockers numbered before dependents; never combine all tickets in one issue file.
- Record triage in a `Status:` line near the top. Append conversation under `## Comments` at the bottom.
- Each implementation ticket declares blockers and acceptance criteria. Start only after every blocker is verified complete. `ready-for-agent` describes specification readiness, not execution permission or completion.
- Record implementation, tests, reviews, CI, actual merge and release evidence separately in `.scratch/<feature-slug>/completion.md`. Keep per-feature execution evidence out of the canonical instructions and triage status.

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
