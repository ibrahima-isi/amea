<?php

declare(strict_types=1);

namespace Amea\Migration\Version;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261127000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Preserve legacy member housing details and unspecified gender';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
ALTER TABLE students
    MODIFY gender ENUM('Male','Female','Masculin','Féminin') DEFAULT NULL,
    MODIFY housing_details VARCHAR(255) DEFAULT NULL
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
