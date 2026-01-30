<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260126120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add smartsheet status log for presentation status tab.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE smartsheet_status_log (
            id BIGINT AUTO_INCREMENT NOT NULL,
            country VARCHAR(150) NOT NULL,
            site_id VARCHAR(64) NOT NULL,
            site_name VARCHAR(255) DEFAULT NULL,
            category VARCHAR(64) NOT NULL,
            rag_confidence VARCHAR(32) DEFAULT NULL,
            status_text TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX IDX_SMARTSHEET_STATUS_SITE (country, site_id, category),
            INDEX IDX_SMARTSHEET_STATUS_CREATED (created_at),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE smartsheet_status_log');
    }
}
