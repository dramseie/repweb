<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260211182000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add phase to task manager for filtering.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE smartsheet_task_manager ADD phase VARCHAR(80) DEFAULT NULL AFTER task_name');
        $this->addSql(
            "UPDATE smartsheet_task_manager t
            JOIN (
                SELECT task_id, MAX(phase) AS phase
                FROM nifi.smartsheet_master_data
                WHERE task_name IS NOT NULL AND task_name <> ''
                GROUP BY task_id
            ) src ON src.task_id = t.id
            SET t.phase = src.phase"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE smartsheet_task_manager DROP COLUMN phase');
    }
}
