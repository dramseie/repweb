<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260201100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create timesheet_hour table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE timesheet_hour (id BIGINT AUTO_INCREMENT NOT NULL, contract_id BIGINT NOT NULL, work_date DATE NOT NULL, hours NUMERIC(6, 2) NOT NULL, comment LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_97B2A3922576E0FD (contract_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE timesheet_hour ADD CONSTRAINT FK_97B2A3922576E0FD FOREIGN KEY (contract_id) REFERENCES timesheet_contract (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE timesheet_hour');
    }
}
