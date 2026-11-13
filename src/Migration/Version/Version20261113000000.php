<?php

declare(strict_types=1);

namespace Amea\Migration\Version;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261113000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add durable member document operations and their shared lock';
    }
    public function isTransactional(): bool
    {
        return false;
    }
    public function up(Schema $schema): void
    {
        $ddl = <<<'SQL'
CREATE TABLE member_file_lock (
    id TINYINT NOT NULL PRIMARY KEY
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE file_operations (
    operation_id VARCHAR(100) NOT NULL PRIMARY KEY,
    backend VARCHAR(64) NOT NULL,
    reason VARCHAR(50) NOT NULL,
    keys_json TEXT NOT NULL,
    student_id INT NULL,
    expected_identity VARCHAR(255) NULL,
    expected_cv VARCHAR(255) NULL,
    expected_deleted_at DATETIME NULL,
    started TINYINT NOT NULL DEFAULT 0,
    attempts INT NOT NULL DEFAULT 0,
    last_error TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX file_operations_backend (backend, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL;
        foreach (explode(";\n", $ddl) as $statement) {
            if (trim($statement) !== '') {
                $this->addSql(trim($statement));
            }
        }
    }
    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
