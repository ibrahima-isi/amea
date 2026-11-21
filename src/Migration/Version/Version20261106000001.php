<?php

declare(strict_types=1);

namespace Amea\Migration\Version;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261106000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Install the initial AMEA settings';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?), (?, ?), (?, ?)',
            ['contact_email', 'admin@aeesgs.org', 'contact_phone', '+221 XX XXX XX XX', 'organization_name', 'AEESGS']
        );
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
