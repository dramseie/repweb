<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260126130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add reptenant JSON field to report table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE report ADD reptenant JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE report DROP COLUMN reptenant');
    }
}
