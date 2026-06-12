# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

PHP 8 / MySQL 8 web app managing members of AEESGS (Guinean students in Senegal). French-language UI; database enums are French (`Masculin`/`Féminin`, `ELEVE`/`ETUDIANT`/`STAGIAIRE`) — use these exact values in backend code.

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
