# AGENTS.md

Single source of agent instructions for this repository. `CLAUDE.md` only points here.

**The rules under "Mandatory workflow" apply to every task, every time.** Where a skill's default conflicts with a rule here, this file wins.

## Mandatory workflow

### Production safety: ask before any delete or drop

This project is live in production and holds sensitive member data (IDs, CVs, photos, personal details). Assume nothing about what is safe to remove.

- Covered actions:
  - Code: removing a file, class, function, route or feature.
  - Database: `DELETE`, `DROP`, `TRUNCATE`, and migrations that drop, rename or narrow a table or column.
  - Data files: anything under `uploads/`.
  - Code that performs any of the above when it runs, such as purges and cleanup jobs.
- Before acting, state the exact targets (paths, tables, columns, row criteria and counts) and the effect, then wait for the user's explicit yes in chat.
- Approval covers the named targets only. A ticket, spec or plan that mentions a deletion does not approve it.
- A reply without a clear yes means the action has not been approved.
- The deploy runs `rsync --delete` (excluding `uploads/`, `storage/`, `.env`): a file removed from the repo disappears from production on the next push to `main`.

### Git: worktree, `dev`, `main`

- Do every implementation in a git worktree, one per ticket, created from `dev`:
  `git worktree add .claude/worktrees/<issue-slug>-<NN> -b <issue-slug>-<NN> dev`
- Name the worktree and its branch `{issue-slug}-{NN}`: the issue's folder name plus the ticket number (`soft-delete-students-03`).
- Long-living branches: `dev` and `main`. Every other branch is short-lived.
- Merge every ticket PR into `dev` by **squash** (`gh pr merge --squash`), the only merge method: one commit per ticket, linear history, no merge commit.
- All changes reach `main` through `dev`. Promote by fast-forward: `git push origin dev:main`. `main` and `dev` keep identical history. A push to `main` deploys (see Commands).
- After the squash merge, remove that work: `git worktree remove <path>`, `git branch -D <branch>` (a squashed branch is never an ancestor of `dev`, so `-d` refuses), `git push origin --delete <branch>`.
- A branch, local or remote, whose code already exists in `main` or `dev` may be deleted without asking. Proof, any one of: the branch is an ancestor of `main`/`dev`; `git cherry <base> <branch>` shows no `+` lines; its PR is `MERGED` and the branch tip equals the PR's head commit. A branch holding code that is in neither follows Production safety.

### Issue tracking: local markdown

Issues, specs and tickets are markdown files under `.scratch/`, grouped by nature (`features/`, `bugs/`, `refactors/`, `chores/`, `docs/`). Each issue's `completion.md` tracks the progress of its tickets. Layout, statuses and `completion.md` format: `docs/agents/issue-tracker.md`.

### Method: TDD + DDD together

- Start from a failing test (_red_) written in the domain's ubiquitous language: terms from `GLOSSARY.md` name the test, the class and the method.
- Make it _green_ in the domain model (entities, value objects, aggregates, domain services, repositories), then _refactor_ with the test green.
- Resolve new domain terms and decisions into `GLOSSARY.md` and `docs/adr/` as they arise (`/domain-modeling`).

### Questions: interactive

Ask every grilling and brainstorming question (`grilling`, `grill-me`, `grill-with-docs`, `brainstorming`) through the `AskUserQuestion` tool: one decision per question, recommended option first.

### Keeping this file current

When the user gives an "always" or "never" instruction, add it to this file in the same turn and append a row to the Change log: date, the instruction, and what applied before.

## Project

PHP 8 / MySQL 8 web app managing members of AEESGS (Guinean students in Senegal). French-language UI; database enums are French (`Masculin`/`Féminin`, `ELEVE`/`ETUDIANT`/`STAGIAIRE`) — use these exact values in backend code.

Production state, confirmed by the user on 2026-10-21: migrations `00`–`05` are applied, and the cron jobs (including the mail-queue worker) run as expected. The next migration is `06`.

## Commands

```bash
composer install                      # dependencies (phpmailer, twig)
php -S localhost:8000                 # dev server (needs .env + MySQL; compose.yml provides MySQL 8)
mysql -u root -p amea_db < schema.sql # load schema

# Tests — plain PHP scripts (no PHPUnit). Run individually:
php tests/test_oop_unit.php
php tests/test_oop_integration.php
php tests/test_permissions_unit.php
php tests/test_password_reset_security.php
```

Tests use SQLite in-memory, so no MySQL is required to run them. CI (`.github/workflows/deploy.yml`) runs the four tests above on push to `main` and deploys to production via SSH only if they pass — every push to `main` deploys.

Migrations are idempotent PHP CLI scripts in `migrations/`, run manually in order (`php migrations/NN_*.php`). Schema changes go in a new migration plus `schema.sql`.

## Architecture

Hybrid codebase: legacy procedural entry points at the repo root, plus a newer OOP core in `src/` (PSR-4, namespace `Amea\`).

- **Root `*.php` files** are the pages/routes (e.g. `students.php`, `dashboard.php`, `register.php`). No front controller — each file is its own entry point. They bootstrap via `src/bootstrap.php`, `config/database.php` (PDO from `.env`), and helpers in `functions/`.
- **`src/`** — `Core/` (TemplateEngine, Session, CsrfGuard, Flash, FileUploader, Router), `Controller/`, `Model/`, `Repository/` (all DB access via PDO), `Service/` (business logic: AuthService, StudentService, ExportService, EmailService…). New code goes here following PSR-4.
- **`functions/`** — procedural helpers still widely used: `utility-functions.php` (env(), CSRF, flash, uploads, export), `email-service.php` (PHPMailer via Brevo SMTP).
- **Templating is dual**: admin pages use a custom `{{placeholder}}` string-replacement engine (`src/Core/TemplateEngine.php`) with templates in `templates/admin/pages/` wrapped by `templates/admin/layout.html`; some public pages use Twig (`*.html.twig`).
- **Auth**: session-based, roles `admin`/`user`; admin pages require `$_SESSION['user_id']`, admin-only pages also check `role = 'admin'`. 30-min session timeout, secure cookies.

## Conventions

- CSRF token required on every POST form (generated/verified via existing helpers); always escape output.
- Dark Mode: all UI must support `[data-bs-theme="dark"]` via CSS variables (`assets/js/theme.js` handles switching). Frontend is Bootstrap 5 + vanilla JS + Tagify.
- The student edit form (`edit-student.php`) must stay visually and logically identical to the registration form.
- Uploads (photos, CVs, IDs) go under `uploads/students/` with strict MIME validation; serve downloads through `download.php`.
- `.env` holds DB, Brevo SMTP, and `APP_URL` config; never commit it.
- Never add Co-Authored-By / Claude attribution trailers to commits.

## Agent skills

### Issue tracker

Local markdown under `.scratch/<nature>/<issue-slug>/`, with a `completion.md` per issue. See `docs/agents/issue-tracker.md`.

### Triage labels

Default five-role vocabulary (`needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`), recorded as a `Status:` line. See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: one `GLOSSARY.md` + `docs/adr/` at the repo root. See `docs/agents/domain.md`.

## Change log

Newest first. "Before" records what applied until that date.

| Date | Instruction | Before |
| --- | --- | --- |
| 2026-10-21 | A branch whose code already exists in `main` or `dev` may be deleted without asking. | Each branch deletion needed the user's yes; the 16 local and 2 remote branches were approved in batches. |
| 2026-10-21 | Squash is the only PR merge method (no merge commit, no rebase merge), and `dev` is promoted to `main` by fast-forward (`git push origin dev:main`), not by PR. | Merge method unspecified: GitHub allowed merge commits, squash and rebase, and `main` holds 19 merge commits (latest 2026-06-14). Promotion was defined earlier today as a PR from `dev` into `main`. |
| 2026-10-21 | The project is live in production with sensitive data: every delete or drop in code, database or `uploads/` needs the user's explicit approval in chat, with exact targets stated first. | No project rule; destructive actions were governed only by the tool's default permission prompts. |
| 2026-10-21 | Every "always"/"never" instruction is added to this file with a dated Change log row. | No procedure: standing rules were added ad hoc to `CLAUDE.md` or to auto-memory. |
| 2026-10-21 | Ask grilling and brainstorming questions through `AskUserQuestion`. | Skills asked inline in chat. |
| 2026-10-21 | TDD and DDD together on all implementation. | Tests were plain PHP scripts under `tests/`; no stated method. |
| 2026-10-21 | Long-living branches are `dev` and `main` only; work merges by PR into `dev`, `dev` is promoted to `main` by PR; merged branches are deleted. | Short-lived `task/*` and `feature/*` branches accumulated; a push to `main` deployed to production; local `dev` was 66 commits behind `main` and absent from `origin`. |
| 2026-10-21 | One git worktree per ticket, named `{issue-slug}-{NN}`, created from `dev`. | No worktree convention; branch names were free-form (`task/N-*`, `feature/*`). |
| 2026-10-21 | Issue tracker is local markdown in `.scratch/<nature>/<issue-slug>/` with a `completion.md` per issue. | No issue tracker configured (GitHub Issues was proposed during setup and superseded before being written). |
| 2026-10-21 | All agent instructions live in `AGENTS.md`; `CLAUDE.md` only points to it. | All instructions lived in `CLAUDE.md`. |
