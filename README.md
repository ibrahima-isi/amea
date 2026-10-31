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
| Dependencies | Composer (`phpmailer/phpmailer`, `twig/twig`) |
| Server | Apache/LiteSpeed with `.htaccess` |

---

## Prerequisites

- PHP 8.0+ with the following extensions: `pdo_mysql`, `fileinfo`, `mbstring`, `gd`
- MySQL 8.0+
- Composer
- Apache or LiteSpeed web server (mod_rewrite enabled)
- **Brevo** account (formerly Sendinblue) for SMTP email delivery

---

## Local installation

```bash
# 1. Clone the repository
git clone <repository-url> amea
cd amea

# 2. Install PHP dependencies
composer install

# 3. Create the environment file
touch .env # no .env.example file is provided
# Edit .env with local credentials (see the section below)

# 4. Create the database and load the schema
mysql -u root -p -e "CREATE DATABASE amea_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p amea_db < schema.sql

# 5. Create the first administrator account
mysql -u root -p amea_db <<'SQL'
INSERT INTO users (username, password, last_name, first_name, email, role, is_active)
VALUES ('admin', '$2y$12$REPLACE_WITH_HASH', 'Admin', 'Système', 'admin@aeesgs.org', 'admin', 1);
SQL
# Generate a PHP hash: php -r "echo password_hash('YourPassword', PASSWORD_DEFAULT);"

# 6. Create upload and log directories
mkdir -p uploads/students/cvs uploads/slider logs
chmod -R 755 uploads logs

# 7. Start the development server
php -S localhost:8000
```

Local access:
- Home: `http://localhost:8000`
- Staff login: `http://localhost:8000/login.php`

---

## Environment variables

The `.env` file lives at the project root:

```dotenv
# Database
DB_HOST=127.0.0.1
DB_NAME=amea_db
DB_USER=root
DB_PASS=secret

# SMTP email (Brevo)
MAIL_USER=your_login@brevo.com
MAIL_PASS=your_brevo_smtp_api_key

# Base URL (used in email links)
APP_URL=https://aeesgs.org
```

> **In production**: never commit `.env`. The file is listed in `.gitignore`.

---

## Project structure

```
amea/
├── .env                        # Environment variables (untracked)
├── .htaccess                   # Apache security: HTTPS, VCS blocking, headers
├── schema.sql                  # Complete database schema
├── composer.json
│
├── src/                        # Application core (OOP, PSR-4)
│   ├── Core/                   # Twig View, Router, Session
│   ├── Controller/             # Controllers (Auth, Registration, KYC)
│   ├── Model/                  # Entities (User, Student)
│   ├── Repository/             # Data access
│   └── Service/                # Business logic (AuthService, StudentService)
│
├── config/
│   ├── database.php            # PDO connection (loads .env, UTF-8)
│   └── session.php             # Session setup: 30-minute lifetime, secure cookies
│
├── functions/
│   ├── utility-functions.php   # env(), CSRF, flash messages, upload, export, versioning
│   ├── email-service.php       # sendMail() + renderEmailTemplate() through Brevo SMTP
│   └── document-reconcile.php  # File-path reconciliation helpers
│
├── migrations/                 # Migration scripts (CLI only)
│
├── templates/                  # Twig views and HTML email templates
│   ├── layouts/                # Public and administration layouts
│   ├── admin/pages/            # Administration views
│   └── emails/                 # Email templates
│
├── assets/
│   ├── css/                    # Bootstrap + custom CSS
│   ├── js/                     # Frontend scripts (including theme.js for Dark Mode)
│   ├── img/                    # Logos, icons
│   └── json/                   # Static data (country list, etc.)
│
├── uploads/
│   ├── slider/                 # Homepage carousel images
│   └── students/               # Student photos and CVs
│       └── cvs/                # CVs (PDF/PNG)
│
├── tests/                      # Unit and integration tests
└── logs/                       # Error logs
```

---

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

Migrations are idempotent PHP CLI scripts in `migrations/`. They add columns, change constraints or create tables without recreating the existing schema.

Run them manually in numeric order against the explicitly selected database:

```bash
php migrations/00_init_schema.php
```

---

## Authentication & sessions

- **Login**: `password_verify()` check and `session_regenerate_id(true)` on success
- **Roles**: `admin` (full access) / `user` (restricted access)
- **Session lifetime**: 30 minutes of inactivity
- **Cookies**: `HttpOnly`, `Secure`, `SameSite=Strict`
- **CSRF**: a `bin2hex(random_bytes(32))` token checked with `hash_equals()` on all POST forms

---

## Public pages and anonymous entry points

The table includes anonymous authentication entry points in the local root layout. In the separate deployed applications, staff login/recovery and private downloads belong to the staff application; requesting staff paths on the public host returns a local 404. See the [independent entry decision](docs/adr/0001-independent-public-and-staff-entry.md) and [rollout guide](docs/operations/public-staff-rollout.md).

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
