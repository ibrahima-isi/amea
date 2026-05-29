# AMEA — Plateforme de gestion AEESGS

## Project Overview

This is a PHP 8 web application designed for managing the members of the **Amicale des Élèves, Étudiants et Stagiaires Guinéens au Sénégal (AEESGS)**. It provides features for member registration, document management (CVs, IDs), email communications, level upgrades, data exports, and an administration dashboard.

## Technologies

*   **Backend:** PHP 8.x (requires extensions: `pdo_mysql`, `fileinfo`, `mbstring`, `gd`) - Refactored to OOP/PSR-4.
*   **Database:** MySQL 8.0+
*   **Frontend:** Bootstrap 5, Vanilla JavaScript, Tagify, **Dark Mode**
*   **Email:** PHPMailer 6.x (via Brevo SMTP)
*   **Templating:** Custom string-replacement engine (`{{...}}`) & Twig

## Architecture & Directory Structure

The project uses a modern MVC architecture for new features and maintained procedural logic for legacy sections.

*   **`src/`**: Contains the OOP core of the application following PSR-4 autoloading (`Amea\\`).
    *   `Core/`: Fundamental components like `TemplateEngine.php`, `Router.php`, `Session.php`.
    *   `Controller/`: Page controllers (Auth, Registration, KYC).
    *   `Model/`: Data entities (`User.php`, `Student.php`).
    *   `Repository/`: Database access logic.
    *   `Service/`: Business logic and orchestration (`AuthService.php`, `StudentService.php`).
*   **`templates/`**: HTML views.
    *   Admin pages use a custom replacement system.
    *   Public pages use Twig templates.
*   **`assets/`**: Static assets.
    *   `js/theme.js`: Manages Dark Mode state and switching.
*   **`functions/`**: Procedural helper files.
*   **`database/` & `schema.sql`**: Database definitions.
*   **`migrations/`**: PHP CLI scripts for schema updates.
*   **`uploads/`**: Directory for user uploads.
*   **Root PHP files**: Main entry points (index.php, register.php, etc.).

## Building, Testing, and Deployment

1.  **Install Dependencies:** `composer install`
2.  **Environment Setup:** Create `.env` from `.env.example`.
3.  **Database Setup:** Import `schema.sql`.
4.  **Run Tests:**
    ```bash
    php tests/test_oop_unit.php
    php tests/test_oop_integration.php
    ```
5.  **Deployment:** Automated via **GitHub Actions**.

## Engineering Standards

*   **Dark Mode:** All new UI elements must support `[data-bs-theme="dark"]` using CSS variables.
*   **Form Synchronization:** The student editing interface must remain visually and logically identical to the registration form.
*   **Data Integrity:** Use database-aligned French enums (`Masculin`, `ELEVE`) in all backend services.
*   **Autoloading:** Always follow PSR-4 for new classes in `src/`.
*   **Security:** Mandatory CSRF tokens, output escaping, and strict MIME type validation for uploads.
*   **Migrations:** Schema changes must be handled via CLI scripts in `migrations/`.
