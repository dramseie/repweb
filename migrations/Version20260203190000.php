<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260203190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add smartsheet_files table for task tracker uploads.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE smartsheet_files (
            id INT AUTO_INCREMENT NOT NULL,
            task_tracker_id INT NOT NULL,
            filename VARCHAR(255) NOT NULL,
            mime_type VARCHAR(120) NOT NULL,
            size_bytes INT NOT NULL,
            content LONGBLOB NOT NULL,
            created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            INDEX IDX_SMARTSHEET_FILES_TASK_TRACKER (task_tracker_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql('ALTER TABLE smartsheet_files ADD CONSTRAINT FK_SMARTSHEET_FILES_TASK_TRACKER FOREIGN KEY (task_tracker_id) REFERENCES smartsheet_task_tracker (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE smartsheet_files DROP FOREIGN KEY FK_SMARTSHEET_FILES_TASK_TRACKER');
        $this->addSql('DROP TABLE smartsheet_files');
    }
}
