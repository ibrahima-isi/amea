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
| Backend | PHP 8.5 (OOP, PDO, fileinfo, GD, cURL, OpenSSL) |
| Database | MariaDB 10.11 / 11.4 (MySQL-compatible, `utf8mb4` charset) |
| Storage | Portable local or S3-compatible object storage (Cloudflare R2 via `aws/aws-sdk-php`) |
| Caching & CDN | Cloudflare Edge Cache (`CDN-Cache-Control`, Cache Rules, Cache Purge API v4), strong `ETag`/`304` revalidation, atomic origin `AppCache` |
| Frontend | Bootstrap 5, vanilla JavaScript, Tagify, **Dark Mode** (light default) |
| Email | PHPMailer 6.x through Brevo SMTP |
| Templating | Twig |
| Dependencies | Composer (`phpmailer/phpmailer`, `twig/twig`, `doctrine/migrations`, `aws/aws-sdk-php`) |
| Server | Apache in split Docker Compose targets or native split LiteSpeed/Apache hosting |

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

Open the public website at `https://web.localhost:8000` and staff login at `https://backoffice.localhost:8090/login.php`. Follow the [local Docker setup](docs/testing/README.md#local-docker-stack) to trust the local HTTPS certificate and handle existing databases.

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

WEB_APP_URL=https://web.localhost:8000
BACKOFFICE_APP_URL=https://backoffice.localhost:8090
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
│   ├── Cache/                       # Cloudflare HTTP/Purge caching and atomic origin AppCache
│   ├── Config/                      # Environment and PDO setup
│   ├── Controller/                  # HTTP request handling
│   ├── Service/                     # Business behavior
│   ├── Model/                       # Domain objects
│   ├── Repository/                  # PDO queries
│   ├── Command/                     # Job and migration commands
│   ├── Migration/                   # Doctrine migration runtime and versions
│   └── Storage/                     # Local and S3/R2 storage backends, migration & recovery
├── scripts/                         # Thin CLI entry scripts and native Stellar release tooling (`scripts/stellar/`)
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
| `locations` | Locations and regions |
| `countries` | Reference countries |
| `slider_images` | Homepage carousel images |
| `journal_posts` | Association journal activities and announcements |
| `student_country` | Nationalities pivot table |
| `settings` | System configuration (contact email, phone, etc.) |
| `password_resets` | Password reset tokens |
| `file_operations` | Durable object-storage deletion and reconciliation ledger |
| `member_file_lock` | Serialization lock for member file mutations and cleanup |

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
| `index.php` (`/`) | Homepage with dynamic carousel and recent journal items |
| `journal.php` | Association journal listing (upcoming activities, reports, announcements) |
| `journal-article.php` | Public association journal article view |
| `register.php` | Registration form (**Dark Mode supported**) |
| `registration-details.php` | Member review and confirmation of a staged registration |
| `registration-preview.php` | Session-owned preview of staged registration documents |
| `kyc-correction.php` | Tokenized member document correction flow |
| `legal-notice.php` | Legal notice and privacy policy (French “CGU”) |
| `accept-cgu.php` | Accept/refuse terms through a tokenized email link |

---

## Administration pages

Staff authentication (`login.php`, `logout.php`, `forgot-password.php`, `reset-password.php`) lives exclusively on the administration host. Authenticated administration pages require a staff session with `user_id` set; pages marked **(admin)** require `role = 'admin'` and module permissions.

| Page | Description |
|---|---|
| `login.php` | Staff login |
| `forgot-password.php` / `reset-password.php` | Staff password recovery |
| `dashboard.php` | Dashboard: KPIs, review queue and theme-aware charts |
| `students.php` | Approved member list with search, filters and pagination |
| `student-details.php` | Detailed member record (read-only) |
| `edit-student.php` | Editing aligned with the registration form |
| `kyc-list.php` / `kyc-detail.php` **(admin)** | KYC review queue and approval/rejection/clarification workflow |
| `download.php` **(admin)** | Authorized private download of member identity and CV documents |
| `trash.php` **(admin)** | Soft-deleted member restoration and backup-gated permanent purge |
| `manage-slider.php` **(admin)** | Homepage carousel media management |
| `manage-journal.php` / `edit-journal.php` **(admin)** | Association journal editorial management and draft preview |
| `export.php` **(admin)** | CSV/JSON/PDF export with field selection and filters |
| `upgrade-levels.php` **(admin)** | Annual study-level progression tool |
| `consent.php` **(admin)** | Consentement & RGPD: CGU consent reminders and GDPR deletion requests |
| `communications.php` **(admin)** | Hybrid bulk member communications with live progress, self-test, and history |
| `users.php` **(admin)** | Staff user and permission management |
| `settings.php` **(admin)** | System settings |
| `profile.php` | Edit the current staff profile |

---

## Feature details

### Dark mode

The project includes full dark-mode support.
- **Light first visit**: first visits render in light mode regardless of operating-system preference; an explicitly saved light/dark choice takes priority.
- **Responsive toggle**: shows text on tablet/desktop and an icon on mobile.
- **Persistence**: saves the choice locally per origin.
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

GitHub Actions (`.github/workflows/ci.yml`) runs validation checks on every pull request and push to `main`, and provides gated Namecheap Stellar publication jobs (`deploy-stellar-staging` via `workflow_dispatch` with `STELLAR_STAGING_DEPLOY_ENABLED=true`, and `deploy-stellar` on `main` gated behind `STELLAR_DEPLOY_ENABLED=true` and the `production` GitHub Environment). See the [Stellar production transition record](docs/operations/stellar-production-transition.md) for the current release, backups, scheduler, and rollback procedure. Docker and Dokploy are documented in the [rollout guide](docs/operations/public-staff-rollout.md), and portable S3/R2 configuration in the [object storage guide](docs/operations/object-storage.md). See the [Cloudflare caching guide](docs/operations/cloudflare-caching.md) for edge rules and the purge CLI. Jobs, storage cutover, and production deployment require explicit authorization.

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
