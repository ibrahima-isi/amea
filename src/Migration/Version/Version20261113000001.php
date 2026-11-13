<?php

declare(strict_types=1);

namespace Amea\Migration\Version;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261113000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initialize the member document serialization lock';
    }
    public function up(Schema $schema): void
    {
        $this->addSql('INSERT INTO member_file_lock (id) VALUES (1)');
    }
    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
