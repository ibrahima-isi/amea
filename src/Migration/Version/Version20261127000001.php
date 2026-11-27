<?php

declare(strict_types=1);

namespace Amea\Migration\Version;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261127000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add performance indexes for member, journal, slider, and lookup queries';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $statements = [
            <<<'SQL'
ALTER TABLE `students`
    ADD KEY `idx_students_active_kyc_reg` (`deleted_at`, `kyc_status`, `registration_date`, `id`),
    ADD KEY `idx_students_active_reg_stats` (`deleted_at`, `registration_date`, `kyc_status`, `status`, `gender`, `consent_privacy`),
    ADD KEY `idx_students_active_institution` (`deleted_at`, `institution`, `status`, `kyc_status`),
    ADD KEY `idx_students_active_status_level` (`deleted_at`, `kyc_status`, `status`, `study_level`),
    ADD KEY `idx_students_identity_document` (`identity_document`),
    ADD KEY `idx_students_cv_path` (`cv_path`)
SQL,
            <<<'SQL'
ALTER TABLE `users`
    ADD KEY `idx_users_created_at` (`created_at`)
SQL,
            <<<'SQL'
ALTER TABLE `locations`
    ADD KEY `idx_locations_region_name` (`region`, `name`)
SQL,
            <<<'SQL'
ALTER TABLE `slider_images`
    ADD KEY `idx_slider_images_image_path` (`image_path`),
    ADD KEY `idx_slider_images_display_order` (`display_order`, `id`)
SQL,
            <<<'SQL'
ALTER TABLE `countries`
    ADD KEY `idx_countries_name_fr` (`name_fr`)
SQL,
            <<<'SQL'
ALTER TABLE `journal_posts`
    ADD KEY `idx_journal_type_publication` (`status`, `type`, `published_at`, `id`),
    ADD KEY `idx_journal_admin_updated` (`updated_at`, `id`),
    ADD KEY `idx_journal_cover_path` (`cover_path`)
SQL,
        ];

        foreach ($statements as $sql) {
            $this->addSql($sql);
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
