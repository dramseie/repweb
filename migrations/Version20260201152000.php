<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260201152000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add contract details fields to timesheet_contract';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE timesheet_contract ADD from_date DATE DEFAULT NULL, ADD to_date DATE DEFAULT NULL, ADD total_hours NUMERIC(8, 2) DEFAULT NULL, ADD customer_approval_emails LONGTEXT DEFAULT NULL, ADD supplier_timesheet_receiver_email VARCHAR(190) DEFAULT NULL, ADD contract_pdf_path VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE timesheet_contract DROP from_date, DROP to_date, DROP total_hours, DROP customer_approval_emails, DROP supplier_timesheet_receiver_email, DROP contract_pdf_path');
    }
}
