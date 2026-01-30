<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260127120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create smartsheet_issue_log table for presentation issues.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE smartsheet_issue_log (
    id INT AUTO_INCREMENT NOT NULL,
    country VARCHAR(120) NOT NULL,
    store_name VARCHAR(255) DEFAULT NULL,
    store_id VARCHAR(64) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    priority VARCHAR(16) DEFAULT NULL,
    responsible_party VARCHAR(120) DEFAULT NULL,
    action_required TEXT DEFAULT NULL,
    resolve_date DATE DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(id),
    INDEX idx_smartsheet_issue_country (country),
    INDEX idx_smartsheet_issue_store (store_id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE smartsheet_issue_log');
    }
}
