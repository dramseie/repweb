<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260201123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add category to timesheet_hour entries';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE timesheet_hour ADD category VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE timesheet_hour DROP category');
    }
}
