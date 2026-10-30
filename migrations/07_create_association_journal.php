<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../config/database.php';
try {
    $conn->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS journal_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('ACTIVITY','REPORT','ANNOUNCEMENT') NOT NULL,
    status ENUM('DRAFT','PUBLISHED') NOT NULL DEFAULT 'DRAFT',
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
    updated_at DATETIME NOT NULL,
    INDEX journal_publication (status,published_at,id),
    INDEX journal_activity (status,type,activity_end_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    echo "Journal schema ready.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Migration failed: ' . $error->getMessage() . "\n");
    exit(1);
}
