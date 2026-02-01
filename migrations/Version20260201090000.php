<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260201090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add timesheet contracts and seed IKEA contract.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE timesheet_contract (
            id BIGINT AUTO_INCREMENT NOT NULL,
            project_name VARCHAR(190) NOT NULL,
            po_number VARCHAR(64) DEFAULT NULL,
            supplier VARCHAR(190) NOT NULL,
            workload_hours_week DECIMAL(6,2) NOT NULL,
            billing_frequency VARCHAR(32) NOT NULL DEFAULT 'monthly',
            requires_signed_report TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("INSERT INTO timesheet_contract (project_name, supplier, workload_hours_week, billing_frequency, requires_signed_report)
            VALUES ('IKEA', 'IKEA', 42.00, 'monthly', 1)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE timesheet_contract');
    }
}
