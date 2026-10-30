# Repository Guidelines

## Project Structure & Module Organization

AMEA manages AEESGS membership for Guinean pupils, students, and interns in Senegal. The interface is French. The stack uses PHP 8, MySQL 8, Bootstrap 5, vanilla JavaScript, Tagify, Twig, and PHPMailer via Brevo SMTP. Root PHP files are individual HTTP entry points; application code lives in `src/` under the PSR-4 namespace `Amea\`:

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
- `docker build -t amea:local .`: build the PHP 8.4 Apache production image.

## Coding Style & Naming Conventions

Match adjacent code: four-space PHP indentation, PascalCase classes, camelCase methods, and namespace-aligned filenames. Keep business rules in services and SQL in repositories. No formatter configuration is supplied. Preserve French UI text and exact enum values such as `Masculin`, `Féminin`, `ELEVE`, `ETUDIANT`, and `STAGIAIRE`. Support `[data-bs-theme="dark"]` through CSS variables; `assets/js/theme.js` handles switching. Keep `edit-student.php` visually and logically aligned with registration.

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

Configure database credentials, Brevo SMTP credentials, and `APP_URL` in untracked `.env`; no `.env.example` currently exists. PHP needs `pdo_mysql`, `fileinfo`, `mbstring`, and `gd`; tests also need `pdo_sqlite`. Preserve CSRF checks, escaped output, prepared queries, and authenticated private-document access. Validate upload MIME types strictly. Student photos, CVs, and IDs use `uploads/students/`; serve private downloads through `download.php` and staged previews through authorized endpoints. Preserve session authentication, `admin`/`user` role checks, secure cookies, and the 30-minute session lifetime. Obtain explicit approval for production changes. Always use interactive input tools for user-facing questions, including design, scope, and clarification. Wait for the user’s answers before proceeding with dependent decisions or work. Separate public and administration applications are an agreed design direction, not an implemented architecture.

## Database Changes

Add schema changes as new idempotent PHP CLI scripts in `migrations/` and update `schema.sql`. Run migrations manually in numeric order, for example `php migrations/00_init_schema.php`, against the explicitly selected database.

## Agent skills

### Issue tracker

Use local Markdown specs and numbered tickets under `.scratch/<feature-slug>/`. Keep feature designs and plans alongside them; `docs/` is for maintained guides and ADRs. Read `docs/agents/issue-tracker.md` before creating, updating, or resolving work items.

### Triage labels

Use the five default triage statuses. Read `docs/agents/triage-labels.md` when setting issue status; track execution and evidence separately in `completion.md`.

### Domain docs

Single-context layout: root `GLOSSARY.md` and `docs/adr/`. Before domain exploration or spec/ticket writing, read `docs/agents/domain.md` and the applicable glossary/ADRs.
