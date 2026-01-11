# AMEA Project Context

## Project Overview
This is a custom PHP-based web application, likely an association or student management system (AMEA). It manages users, students, and content (sliders).

## Tech Stack
*   **Backend:** PHP (Native/Vanilla, no framework).
*   **Database:** MySQL 8.0.
*   **Frontend:** HTML templates, CSS, JavaScript (Custom + likely Bootstrap based on class names).
*   **Infrastructure:** Docker (via `compose.yml`), cPanel (via `.cpanel.yml`).

## Project Structure
*   **`*.php` (Root):** Main controllers/logic (e.g., `index.php`, `login.php`, `dashboard.php`). These files handle logic and render templates.
*   **`templates/`:** HTML files acting as views. They contain placeholders like `{{header}}` or `{{csrf_token}}` which are replaced by the PHP logic.
*   **`config/`:** Configuration files (`database.php`, `session.php`). Handles DB connection and session settings.
*   **`assets/`:** Static resources (CSS, JS, Images).
*   **`functions/`:** Helper functions (`utility-functions.php`).
*   **`uploads/`:** directory for uploaded files (e.g., student documents, slider images).

## Setup & Development

### Instructions
Before any development work (bug fixes, features), follow these steps:
1. **Create a git branch before making changes.**
2. **Set up the environment using Docker or a local PHP server.**
3. **Test changes thoroughly before committing.**
4. **Push changes to the appropriate branch and create a pull request for review using GitHub cli.**


### Docker (Recommended)
The project includes a `compose.yml` for setting up the environment.
```bash
docker compose up -d
```
*   **Service:** `mysql` (Port 3306).
*   **Note:** The PHP application itself doesn't seem to have a container in the provided `compose.yml` (only MySQL). You might need to run PHP locally or add a PHP service.

### Local PHP Server
To run the application using the built-in PHP server:
```bash
# Ensure you have a local MySQL server running or port-forward the Docker MySQL
php -S localhost:8000
```

### Database Configuration
Database credentials are defined in `config/database.php`. It attempts to load from a `.env` file if present, otherwise uses constants defined in the file (currently fetching from `env()` helper).

**Expected Environment Variables:**
*   `DB_HOST`
*   `DB_NAME`
*   `DB_USER`
*   `DB_PASS`
#### Database Initialization test
We can see that the database `amea_db` is created and accessible:

mysql> show databases;
+--------------------+
| Database           |
+--------------------+
| amea_db            |
| information_schema |
| mysql              |
| performance_schema |
| sys                |
+--------------------+
5 rows in set (0.00 sec)

mysql>
mysql> use amea_db
Database changed
mysql> show tables;
Empty set (0.00 sec)

mysql>


## Development Conventions

*   **Templating:** The project uses a simple search-and-replace system.
    ```php
    $output = strtr($template, [
        '{{header}}' => $headerHtml,
        '{{variable}}' => $value
    ]);
    ```
*   **Security:**
    *   **CSRF:** Implemented via `verifyCsrfToken()` and `generateCsrfToken()`.
    *   **Sessions:** Managed in `config/session.php`.
    *   **PDO:** Used for all database interactions with prepared statements.
*   **Deployment:** Configured for cPanel via `.cpanel.yml` using `rsync`.

## Key Files
*   `index.php`: Homepage logic.
*   `login.php`: Authentication logic.
*   `config/database.php`: Database connection (PDO).
*   `functions/utility-functions.php`: Shared helpers.
