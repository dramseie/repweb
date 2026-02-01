<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260201193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add timesheet_contract_approval table for monthly approvals.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE timesheet_contract_approval (id BIGINT AUTO_INCREMENT NOT NULL, contract_id BIGINT NOT NULL, report_month VARCHAR(7) NOT NULL, approved_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', approved_by VARCHAR(190) DEFAULT NULL, UNIQUE INDEX uniq_contract_month (contract_id, report_month), INDEX IDX_TCA_CONTRACT (contract_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE timesheet_contract_approval ADD CONSTRAINT FK_TCA_CONTRACT FOREIGN KEY (contract_id) REFERENCES timesheet_contract (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE timesheet_contract_approval DROP FOREIGN KEY FK_TCA_CONTRACT');
        $this->addSql('DROP TABLE timesheet_contract_approval');
    }
}
