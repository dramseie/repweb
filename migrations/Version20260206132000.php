<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260206132000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Recreate nifi.smartsheet_cell_history with only core columns and JSON data payload.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS nifi.smartsheet_cell_history');
        $this->addSql(
            'CREATE TABLE nifi.smartsheet_cell_history (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                sheet_id BIGINT NOT NULL,
                row_id BIGINT NOT NULL,
                column_id BIGINT NOT NULL,
                data JSON NULL,
                fetched_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS nifi.smartsheet_cell_history');
    }
}
