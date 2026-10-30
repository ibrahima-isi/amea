<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../functions/utility-functions.php';

$_ENV['APP_ENV'] = 'test';
$_ENV['APP_URL'] = 'http://localhost';
$_ENV['MAIL_USER'] = 'test@test.local';
$_ENV['MAIL_PASS'] = 'test';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function makeTestDb(): PDO
{
    $db = new PDO('sqlite::memory:');
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
    ");

    $db->exec("INSERT INTO users (id, username, email, last_name, first_name, password, role, is_active)
               VALUES (1, '_superadmin', 'sa@test.internal', 'SA', 'Test', 'x', 'admin', 1)");

    $db->exec("CREATE TABLE journal_posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
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
    ); CREATE INDEX journal_publication ON journal_posts(status,published_at,id); CREATE INDEX journal_activity ON journal_posts(status,type,activity_end_at);");

    return $db;
}
