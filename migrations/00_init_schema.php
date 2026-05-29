<?php
/**
 * Migration 00: Initial schema setup.
 * Reproduces the baseline database structure.
 */

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

require_once __DIR__ . '/../config/database.php';

try {
    echo "Starting migration: 00_init_schema...\n";

    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `students`
(
    `id`                     int(11)                                                                  NOT NULL AUTO_INCREMENT,
    `last_name`              varchar(100)                                                             NOT NULL,
    `first_name`             varchar(100)                                                             NOT NULL,
    `gender`                 enum ('Male','Female')                                                   NOT NULL,
    `age`                    int(11)      DEFAULT NULL,
    `birth_date`             date             DEFAULT NULL,
    `residence`              varchar(150)      DEFAULT NULL,
    `institution`            varchar(200)      DEFAULT NULL,
    `status`                 enum ('PUPIL','STUDENT','TRAINEE')                                       NOT NULL,
    `study_field`            varchar(200)      DEFAULT NULL,
    `study_level`            varchar(100)      DEFAULT NULL,
    `phone`                  varchar(20)       DEFAULT NULL UNIQUE,
    `email`                  varchar(100)      DEFAULT NULL UNIQUE,
    `arrival_year`           int(11)      DEFAULT NULL,
    `housing_type`           enum ('With family','Shared housing','University residence','Other')     DEFAULT NULL,
    `housing_details`        varchar(100) DEFAULT NULL,
    `post_training_project`  text         DEFAULT NULL,
    `identity_document`      varchar(255) DEFAULT NULL,
    `cv_path`                varchar(255) DEFAULT NULL,
    `nationalities`          text         DEFAULT NULL,
    `kyc_status`             enum ('PENDING_CONFIRMATION','UNDER_REVIEW','APPROVED','NEEDS_CLARIFICATION','REJECTED') NOT NULL DEFAULT 'PENDING_CONFIRMATION',
    `kyc_notes`              text         DEFAULT NULL,
    `review_token`           varchar(64)  DEFAULT NULL UNIQUE,
    `kyc_updated_at`         datetime     DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    `registration_date`      datetime     DEFAULT current_timestamp(),
    `graduation_date`        date         DEFAULT NULL,
    `is_locked`              tinyint(1)   NOT NULL DEFAULT 0,
    `consent_privacy`        tinyint(1)   NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `users`
(
    `id`                 int(11)                      NOT NULL AUTO_INCREMENT,
    `username`           varchar(50)                  NOT NULL,
    `password`           varchar(255)                 NOT NULL,
    `last_name`          varchar(100)                 NOT NULL,
    `first_name`         varchar(100)                 NOT NULL,
    `email`              varchar(100)                 NOT NULL,
    `role`               enum ('admin','user')        NOT NULL DEFAULT 'user',
    `is_active`          tinyint(1)                   NOT NULL DEFAULT 1,
    `session_version`    int(11)                      NOT NULL DEFAULT 1,
    `last_login`         datetime                              DEFAULT NULL,
    `created_at`         datetime                              DEFAULT current_timestamp(),
    `permissions`        text                                  DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `username` (`username`),
    UNIQUE KEY `email` (`email`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS institutions
(
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS study_fields
(
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS study_levels
(
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS locations
(
    id     INT AUTO_INCREMENT PRIMARY KEY,
    name   VARCHAR(255) NOT NULL,
    region VARCHAR(100) NOT NULL,
    UNIQUE (name)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `slider_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `image_path` varchar(255) NOT NULL,
  `title` varchar(100) DEFAULT NULL,
  `caption` text DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `uploaded_by` (`uploaded_by`),
  CONSTRAINT `slider_images_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `password_resets` (
  `email` varchar(100) NOT NULL,
  `token` varchar(100) NOT NULL,
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`token`),
  KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `countries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code_iso` char(2) NOT NULL,
  `name_fr` varchar(100) NOT NULL,
  `name_en` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code_iso` (`code_iso`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `student_country` (
  `student_id` int(11) NOT NULL,
  `country_id` int(11) NOT NULL,
  PRIMARY KEY (`student_id`,`country_id`),
  KEY `country_id` (`country_id`),
  CONSTRAINT `student_country_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_country_ibfk_2` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `setting_key` varchar(50) NOT NULL,
    `setting_value` text,
    `setting_group` varchar(50) DEFAULT 'general',
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL;

    $conn->exec($sql);
    echo "Initial schema created successfully.\n";
    echo "Migration completed successfully.\n";

} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
