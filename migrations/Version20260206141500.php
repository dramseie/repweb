<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260206141500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add unique index for upserts on nifi.smartsheet_cell_history.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX ux_smartsheet_cell_history_keys ON nifi.smartsheet_cell_history (sheet_id, row_id, column_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX ux_smartsheet_cell_history_keys ON nifi.smartsheet_cell_history');
    }
}
