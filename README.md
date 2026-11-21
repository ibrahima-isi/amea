# AMEA — AEESGS management platform

Web platform for managing members of the **Amicale des Élèves, Étudiants et Stagiaires Guinéens au Sénégal (AEESGS)**. It covers member registration, member records, communications, exports and administration tools.

---

## Table of contents

1. [Technology stack](#technology-stack)
2. [Prerequisites](#prerequisites)
3. [Local installation](#local-installation)
4. [Environment variables](#environment-variables)
5. [Project structure](#project-structure)
6. [Database](#database)
7. [Authentication & sessions](#authentication--sessions)
8. [Public pages and anonymous entry points](#public-pages-and-anonymous-entry-points)
9. [Administration pages](#administration-pages)
10. [Email system](docs/email-queue.md)
11. [Feature details](#feature-details)
12. [Tests](#tests)
13. [Deployment (production)](#deployment-production)
14. [Security](#security)

---

## Technology stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.x (OOP, PDO, fileinfo, GD) |
| Database | MySQL 8.0 (utf8mb4 charset) |
| Frontend | Bootstrap 5, vanilla JavaScript, Tagify, **Dark Mode** |
| Email | PHPMailer 6.x through Brevo SMTP |
| Templating | Twig |
| Dependencies | Composer (`phpmailer/phpmailer`, `twig/twig`, `doctrine/migrations`) |
| Server | Apache in split Docker Compose targets |

---

## Prerequisites

- Docker with the Compose plugin for local application development.
- PHP 8.5 with `pdo_sqlite`, `fileinfo`, `mbstring`, `gd`, and `curl`, plus Composer, for host-side PHP tests.

## Local installation

Create an untracked `.env` using the values below, then start both applications.

```sh
docker compose up -d --build --remove-orphans
docker compose logs migrate
```

Open the public website at `https://public.localhost:8000` and staff login at `https://staff.localhost:8090/login.php`. Follow the [local Docker setup](docs/testing/README.md#local-docker-stack) to trust the local HTTPS certificate and handle existing databases.

On a fresh database, generate a password hash with PHP's `password_hash()` and create the first staff administrator. Open the database client with `docker compose exec db mariadb -uamea -p amea_db` using the database user and name from your `.env`.

```sql
INSERT INTO users (username, password, last_name, first_name, email, role, is_active)
VALUES ('admin', '$2y$12$REPLACE_WITH_HASH', 'Admin', 'Système', 'admin@aeesgs.org', 'admin', 1);
```

## Environment variables

Create `.env` at the project root and substitute local credentials.

```dotenv
DB_NAME=amea_db
DB_USER=amea
DB_PASS=replace-with-local-password
MARIADB_ROOT_PASSWORD=replace-with-local-root-password

PUBLIC_APP_URL=https://public.localhost:8000
STAFF_APP_URL=https://staff.localhost:8090
APP_ENV=local
```

The local stack suppresses real mail with `APP_ENV=local`. Deployment environment settings are documented in the [rollout guide](docs/operations/public-staff-rollout.md).

## Project structure

```text
amea/
├── composer.json                    # Shared dependencies and PSR-4 mapping
├── apps/web/public/index.php        # Public website entry point
├── apps/backoffice/public/index.php # Administration entry point
├── src/
│   ├── Core/                        # Routing, sessions and Twig
│   ├── Config/                      # Environment and PDO setup
│   ├── Controller/                  # HTTP request handling
│   ├── Service/                     # Business behavior
│   ├── Model/                       # Domain objects
│   ├── Repository/                  # PDO queries
│   ├── Command/                     # Job and migration commands
│   └── Migration/                   # Doctrine migration runtime and versions
├── scripts/                         # Thin CLI entry scripts
├── config/database.php              # Historical migration adapter
├── migrations/                      # Historical migration sources
├── templates/                       # Twig views and HTML email templates
├── assets/                          # Shared public static files
└── tests/                           # Regression suites and disposable rehearsals
```

Member uploads, storage, and logs are mounted outside both document roots.

## Database

### Main tables

#### `students` — Member records
| Column | Type | Description |
|---|---|---|
| `id` | INT PK | Unique identifier |
| `last_name`, `first_name` | VARCHAR | Full name |
| `gender` | ENUM | `Masculin` / `Féminin` |
| `email` | VARCHAR UNIQUE | Email address |
| `phone` | VARCHAR UNIQUE | Phone number |
| `status` | ENUM | `ELEVE` / `ETUDIANT` / `STAGIAIRE` |
| `institution` | VARCHAR | Enrolling institution |
| `study_level` | VARCHAR | Current study level |
| `nationalities` | JSON | List of nationalities |
| `identity_document` | VARCHAR | Relative path to the identity document |
| `cv_path` | VARCHAR | Relative path to the CV |
| `registration_date` | DATETIME | Registration date |
| `graduation_date` | DATE | Graduation date (for the French status “Diplômé”) |
| `is_locked` | TINYINT | Finalized (1) or editable (0) record |
| `consent_privacy` | TINYINT | Terms accepted (1) or not (0), labelled “CGU” in French |

#### `users` — Administrator accounts
| Column | Type | Description |
|---|---|---|
| `id` | INT PK | |
| `username` | VARCHAR UNIQUE | Login identifier |
| `password` | VARCHAR | bcrypt hash |
| `role` | ENUM | `admin` / `user` |
| `is_active` | TINYINT | Active (1) or disabled (0) account |
| `last_login` | DATETIME | Last login |

#### Other tables

| Table | Description |
|---|---|
| `institutions` | Educational institutions |
| `study_fields` | Available fields of study |
| `study_levels` | Study levels |
| `slider_images` | Homepage carousel images |
| `student_country` | Nationalities pivot table |
| `settings` | System configuration (contact email, phone, etc.) |
| `password_resets` | Password reset tokens |

### Migrations

Doctrine records applied versions in the selected database. Fresh databases initialize automatically; existing databases require [verified enrollment](docs/operations/doctrine-enrollment.md). The local and Dokploy one-shot services use the same command and block application startup on failure. Outside Docker, export the selected database's `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` and `DB_PASS`, then run:

```bash
php scripts/migrate.php status
php scripts/migrate.php migrate
```

New versions belong in `src/Migration/Version/`; keep `schema.sql` aligned. Historical `migrations/` scripts are retained for reference and are excluded from routine startup. Schema changes and interrupted runs require the [backup and recovery procedure](docs/operations/migration-recovery.md).

---

## Authentication & sessions

- **Login**: `password_verify()` check and `session_regenerate_id(true)` on success
- **Roles**: `admin` (full access) / `user` (restricted access)
- **Session lifetime**: 30 minutes of inactivity
- **Cookies**: `HttpOnly`, `Secure`, `SameSite=Strict`
- **CSRF**: a `bin2hex(random_bytes(32))` token checked with `hash_equals()` on all POST forms

---

## Public pages and anonymous entry points

Staff login, account recovery and private downloads belong to administration. The public website returns a local 404 for staff paths. See the [independent entry decision](docs/adr/0001-independent-public-and-staff-entry.md) and [rollout guide](docs/operations/public-staff-rollout.md).

| Page | Description |
|---|---|
| `index.php` | Homepage with a dynamic carousel |
| `register.php` | Registration form (**Dark Mode supported**) |
| `login.php` | Staff login |
| `forgot-password.php` | Password reset request |
| `reset-password.php` | Token-based password reset |
| `legal-notice.php` | Legal notice and privacy policy (French “CGU”) |
| `accept-cgu.php` | Accept/refuse the terms through a tokenized email link |
| `registration-details.php` | Member confirmation of the registration record |
| `download.php` | Secure download of a student's documents (requires staff authentication) |

---

## Administration pages

All administration pages require a session with `user_id` set. Pages marked **(admin only)** require `role = 'admin'`.

| Page | Description |
|---|---|
| `dashboard.php` | Dashboard: KPIs and theme-aware charts |
| `students.php` | Member list with search, filters and pagination |
| `student-details.php` | Detailed member record (read-only) |
| `edit-student.php` | Editing aligned with the registration form |
| `export.php` | CSV/JSON/PDF export with field selection and filters |
| `upgrade-levels.php` **(admin)** | Annual study-level progression tool |
| `communications.php` **(admin)** | Terms campaigns and group emails |
| `settings.php` **(admin)** | System settings |
| `profile.php` | Edit the current staff profile |

---

## Feature details

### Dark mode

The project includes full dark-mode support.
- **Responsive toggle**: shows text on tablet/desktop and an icon on mobile.
- **Persistence**: saves the choice locally.
- **Dynamic charts**: dashboard charts adjust their colors automatically.

### Aligned registration and editing

Registration (`register.php`) and staff editing (`edit-student.php`) forms are aligned in terms of:
- **Visual grouping**: collapsible sections (accordions).
- **Validation**: shared business logic through `StudentService.php`.
- **Required fields**: strictly aligned constraints.

---

## Tests

Commands and limitations are documented in the [testing guide](docs/testing/README.md).

---

## Deployment (production)

GitHub Actions runs checks without deployment. Docker images and Dokploy configuration are documented in the [rollout guide](docs/operations/public-staff-rollout.md). This configuration does not prove a production release; jobs and SMTP require explicit authorization.

---

## Security

| Measure | Implementation |
|---|---|
| CSRF | Unique token checked on all POST requests |
| Passwords | `password_hash()` / `password_verify()` (bcrypt) |
| Sessions | `HttpOnly`, `Secure`, `SameSite=Strict`, 30-minute expiry |
| SQL injection | PDO with prepared queries throughout |
| XSS | `htmlspecialchars()` on output throughout |
| Dark Mode Flash | Flash-prevention script in the `<head>` |
