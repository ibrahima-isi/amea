<?php

declare(strict_types=1);

namespace Amea\Migration\Version;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261127000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add isolated CGU consent token and privacy refusal/deletion timestamps on students';
    }

    public function isTransactional(): bool
    {
        // MariaDB commits DDL implicitly.
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
ALTER TABLE `students`
    ADD COLUMN `cgu_token`             varchar(64) DEFAULT NULL AFTER `consent_privacy`,
    ADD COLUMN `cgu_reminder_sent_at`  datetime    DEFAULT NULL AFTER `cgu_token`,
    ADD COLUMN `consent_refused_at`    datetime    DEFAULT NULL AFTER `cgu_reminder_sent_at`,
    ADD COLUMN `deletion_requested_at` datetime    DEFAULT NULL AFTER `consent_refused_at`,
    ADD UNIQUE KEY `cgu_token` (`cgu_token`),
    ADD KEY `idx_students_deletion_requested` (`deletion_requested_at`)
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
