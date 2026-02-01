<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260201143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow timesheet_hour contract_id to be nullable for global entries';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE timesheet_hour MODIFY contract_id BIGINT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE timesheet_hour MODIFY contract_id BIGINT NOT NULL');
    }
}
