<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260201194500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow nullable approvals and add comments to timesheet contract approvals.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE timesheet_contract_approval CHANGE approved_at approved_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE timesheet_contract_approval ADD comment LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE timesheet_contract_approval DROP comment');
        $this->addSql('ALTER TABLE timesheet_contract_approval CHANGE approved_at approved_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }
}
