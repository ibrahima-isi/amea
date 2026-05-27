# AMEA — Plateforme de gestion AEESGS

## Project Overview

This is a PHP 8 web application designed for managing the members of the **Amicale des Élèves, Étudiants et Stagiaires Guinéens au Sénégal (AEESGS)**. It provides features for member registration, document management (CVs, IDs), email communications, level upgrades, data exports, and an administration dashboard.

## Technologies

*   **Backend:** PHP 8.x (requires extensions: `pdo_mysql`, `fileinfo`, `mbstring`, `gd`)
*   **Database:** MySQL 8.0+
*   **Frontend:** Bootstrap 5, Vanilla JavaScript, Tagify
*   **Email:** PHPMailer 6.x (via Brevo SMTP)
*   **Templating:** Custom string-replacement engine (`{{...}}`)

## Architecture & Directory Structure

The project uses a custom, lightweight MVC-like architecture with a mix of OOP components and procedural scripts.

*   **`src/`**: Contains the OOP core of the application following PSR-4 autoloading (`Amea\\`).
    *   `Core/`: Fundamental components like `Database.php`, `Session.php`, `TemplateEngine.php`.
    *   `Model/`: Data structures representing entities (`User.php`, `Student.php`).
    *   `Repository/`: Database access logic.
    *   `Service/`: Business logic and orchestration (`AuthService.php`, `EmailService.php`).
*   **`templates/`**: HTML views. Pages use a custom templating system. Separated into public pages, admin pages, and email templates.
*   **`assets/`**: Static assets (CSS, JS, images, JSON data).
*   **`functions/`**: Procedural helper files (`utility-functions.php`, `email-service.php`, `document-reconcile.php`).
*   **`database/` & `schema.sql`**: Database definitions.
*   **`migrations/`**: Procedural PHP scripts for database migrations.
*   **`uploads/`**: Directory for user uploads (student CVs, ID photos, slider images).
*   **Root PHP files**: These act as the entry points or controllers for specific pages (e.g., `index.php`, `register.php`, `dashboard.php`, `students.php`).

## Building, Testing, and Deployment

1.  **Install Dependencies:**
    ```bash
    composer install
    ```
2.  **Environment Setup:**
    Create a `.env` file at the root (based on a template or structure defined in the README). You need database and SMTP credentials.
3.  **Database Setup:**
    Create a MySQL database (`amea_db`) and import the schema:
    ```bash
    mysql -u root -p amea_db < schema.sql
    ```
4.  **Run Tests:**
    Unit tests use SQLite in-memory and are built for PHP 8.4 compatibility.
    ```bash
    php tests/test_oop_unit.php
    php tests/test_permissions_unit.php
    php tests/test_oop_integration.php
    ```
5.  **Deployment (CI/CD):**
    Deployment is fully automated via **GitHub Actions** (`.github/workflows/deploy.yml`). Pushing to `main` triggers tests, connects to the Namecheap server via SSH, robustly installs dependencies with `composer.phar`, synchronizes files using strict `rsync` rules, applies secure `755/644` permissions to prevent `403 Forbidden` errors, and dynamically generates the production `.env` from GitHub Secrets.

## Development Conventions

*   **Autoloading:** Use Composer's PSR-4 autoloader for any new classes added to the `src/` directory under the `Amea\\` namespace.
*   **Database:** Always use PDO prepared statements to prevent SQL injection. The `Amea\Config\Database` class should be used for getting the connection.
*   **Security:**
    *   All POST forms must include and verify a CSRF token.
    *   Output escaping (`htmlspecialchars`) is mandatory for all user-provided data rendered in templates.
    *   File uploads are validated by MIME type using `fileinfo`.
*   **Templating:** Use the custom `TemplateEngine` or the procedural view wrappers instead of mixing complex PHP logic directly into HTML files.
*   **Migrations:** Schema changes MUST be handled via explicit PHP CLI scripts in the `migrations/` directory. Auto-healing `ALTER TABLE` queries embedded in web controllers are deprecated to reduce overhead and prevent silent fatal errors. Migrations must be run manually on the production server from the `public_html/` root with `display_errors=1`.