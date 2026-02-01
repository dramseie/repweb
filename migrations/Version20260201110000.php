<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260201110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add start/end time to timesheet_hour';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE timesheet_hour ADD start_time TIME DEFAULT NULL, ADD end_time TIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE timesheet_hour DROP start_time, DROP end_time');
    }
}
