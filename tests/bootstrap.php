<?php

require_once __DIR__ . '/../vendor/autoload.php';

$_ENV['APP_ENV'] = 'test';
$_ENV['APP_URL'] = 'http://localhost';
$_ENV['MAIL_USER'] = 'test@test.local';
$_ENV['MAIL_PASS'] = 'test';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function makeTestDb(): PDO
{
    $db = class_exists(\Pdo\Sqlite::class) ? \Pdo\Sqlite::connect('sqlite::memory:') : new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $db->exec("
        CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            email TEXT NOT NULL DEFAULT '',
            last_name TEXT NOT NULL DEFAULT '',
            first_name TEXT NOT NULL DEFAULT '',
            password TEXT NOT NULL DEFAULT '',
            role TEXT NOT NULL DEFAULT 'user',
            permissions TEXT DEFAULT NULL,
            is_active INTEGER NOT NULL DEFAULT 1,
            session_version INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            last_login TEXT DEFAULT NULL
        );

        CREATE TABLE students (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            last_name TEXT NOT NULL DEFAULT '',
            first_name TEXT NOT NULL DEFAULT '',
            gender TEXT NOT NULL DEFAULT '',
            birth_date TEXT DEFAULT NULL,
            residence TEXT DEFAULT NULL,
            institution TEXT DEFAULT NULL,
            status TEXT NOT NULL DEFAULT '',
            study_field TEXT DEFAULT NULL,
            study_level TEXT DEFAULT NULL,
            phone TEXT DEFAULT NULL,
            email TEXT DEFAULT NULL,
            arrival_year INTEGER DEFAULT NULL,
            housing_type TEXT DEFAULT NULL,
            housing_details TEXT DEFAULT NULL,
            post_training_project TEXT DEFAULT NULL,
            identity_document TEXT DEFAULT NULL,
            nationalities TEXT DEFAULT NULL,
            cv_path TEXT DEFAULT NULL,
            registration_date TEXT NOT NULL DEFAULT (datetime('now')),
            graduation_date TEXT DEFAULT NULL,
            is_locked INTEGER NOT NULL DEFAULT 0,
            consent_privacy INTEGER NOT NULL DEFAULT 0,
            cgu_token TEXT DEFAULT NULL UNIQUE,
            cgu_reminder_sent_at DATETIME DEFAULT NULL,
            consent_refused_at DATETIME DEFAULT NULL,
            deletion_requested_at DATETIME DEFAULT NULL,
            kyc_status TEXT NOT NULL DEFAULT 'PENDING_CONFIRMATION',
            kyc_notes TEXT DEFAULT NULL,
            review_token TEXT DEFAULT NULL,
            kyc_updated_at TEXT DEFAULT (datetime('now')),
            deleted_at TEXT DEFAULT NULL,
            deleted_by INTEGER DEFAULT NULL
        );

        CREATE TABLE settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            setting_key TEXT NOT NULL UNIQUE,
            setting_value TEXT NOT NULL DEFAULT ''
        );

        CREATE TABLE institutions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE
        );

        CREATE TABLE slider_images (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            image_path TEXT NOT NULL,
            caption TEXT DEFAULT NULL,
            display_order INTEGER DEFAULT 0,
            created_at TEXT DEFAULT (datetime('now'))
        );

        CREATE INDEX idx_students_deleted_at ON students(deleted_at);
        CREATE INDEX idx_students_active_kyc_reg ON students(deleted_at, kyc_status, registration_date, id);
        CREATE INDEX idx_students_active_reg_stats ON students(deleted_at, registration_date, kyc_status, status, gender, consent_privacy);
        CREATE INDEX idx_students_active_institution ON students(deleted_at, institution, status, kyc_status);
        CREATE INDEX idx_students_active_status_level ON students(deleted_at, kyc_status, status, study_level);
        CREATE INDEX idx_students_identity_document ON students(identity_document);
        CREATE INDEX idx_students_cv_path ON students(cv_path);
        CREATE INDEX idx_users_created_at ON users(created_at);
        CREATE INDEX idx_slider_images_image_path ON slider_images(image_path);
        CREATE INDEX idx_slider_images_display_order ON slider_images(display_order, id);
    ");

    $db->exec("INSERT INTO users (id, username, email, last_name, first_name, password, role, is_active)
               VALUES (1, '_superadmin', 'sa@test.internal', 'SA', 'Test', 'x', 'admin', 1)");

    $db->exec("CREATE TABLE journal_posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    revision INTEGER NOT NULL DEFAULT 1,
    type TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'DRAFT',
    slug VARCHAR(220) NOT NULL UNIQUE,
    title VARCHAR(180) NOT NULL,
    summary VARCHAR(320) NOT NULL DEFAULT '',
    body MEDIUMTEXT NOT NULL,
    cover_path VARCHAR(255) NULL,
    cover_mime VARCHAR(100) NULL,
    cover_alt VARCHAR(500) NOT NULL DEFAULT '',
    activity_start_at DATETIME NULL,
    activity_end_at DATETIME NULL,
    activity_start_time_set BOOLEAN NOT NULL DEFAULT 0,
    activity_end_time_set BOOLEAN NOT NULL DEFAULT 0,
    location VARCHAR(255) NULL,
    published_at DATETIME NULL,
    created_by INT NOT NULL,
    updated_by INT NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
    ); CREATE INDEX journal_publication ON journal_posts(status,published_at,id); CREATE INDEX journal_activity ON journal_posts(status,type,activity_end_at); CREATE INDEX idx_journal_type_publication ON journal_posts(status,type,published_at,id); CREATE INDEX idx_journal_admin_updated ON journal_posts(updated_at,id); CREATE INDEX idx_journal_cover_path ON journal_posts(cover_path);");

    $db->exec("CREATE TABLE member_file_lock (id INTEGER PRIMARY KEY);
        INSERT INTO member_file_lock VALUES (1);
        CREATE TABLE file_operations (
            operation_id VARCHAR(100) PRIMARY KEY, backend VARCHAR(64) NOT NULL,
            reason VARCHAR(50) NOT NULL, keys_json TEXT NOT NULL, student_id INTEGER NULL,
            expected_identity TEXT NULL, expected_cv TEXT NULL, expected_deleted_at DATETIME NULL,
            started INTEGER NOT NULL DEFAULT 0, attempts INTEGER NOT NULL DEFAULT 0,
            last_error TEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE communications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            type TEXT NOT NULL DEFAULT 'BULK',
            subject TEXT NOT NULL,
            body TEXT NOT NULL,
            filters_json TEXT DEFAULT NULL,
            status TEXT NOT NULL DEFAULT 'QUEUED',
            total_recipients INTEGER NOT NULL DEFAULT 0,
            sent_count INTEGER NOT NULL DEFAULT 0,
            failed_count INTEGER NOT NULL DEFAULT 0,
            cancelled_count INTEGER NOT NULL DEFAULT 0,
            created_by INTEGER DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            started_at DATETIME DEFAULT NULL,
            completed_at DATETIME DEFAULT NULL
        );
        CREATE TABLE communication_recipients (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            communication_id INTEGER NOT NULL,
            student_id INTEGER DEFAULT NULL,
            email TEXT NOT NULL,
            first_name TEXT NOT NULL DEFAULT '',
            last_name TEXT NOT NULL DEFAULT '',
            status TEXT NOT NULL DEFAULT 'PENDING',
            attempts INTEGER NOT NULL DEFAULT 0,
            next_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            claimed_at DATETIME DEFAULT NULL,
            sent_at DATETIME DEFAULT NULL,
            last_error TEXT DEFAULT NULL
        );
        CREATE TABLE mail_daily_usage (
            usage_date TEXT PRIMARY KEY,
            sent_count INTEGER NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        );");

    return $db;
}
