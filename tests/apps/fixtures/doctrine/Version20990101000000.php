<?php

declare(strict_types=1);

namespace Amea\Migration\Version;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20990101000000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE settings SET setting_value = 'Recovery fixture' WHERE setting_key = 'organization_name'");
    }
}
