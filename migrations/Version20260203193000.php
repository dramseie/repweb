<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260203193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move task tracker files to smartsheet_files.task_tracker_files and drop repweb.smartsheet_files.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS smartsheet_files');

        $this->addSql("CREATE TABLE IF NOT EXISTS smartsheet_files.task_tracker_files (
            id INT AUTO_INCREMENT NOT NULL,
            task_tracker_id INT NOT NULL,
            filename VARCHAR(255) NOT NULL,
            mime_type VARCHAR(120) NOT NULL,
            size_bytes INT NOT NULL,
            content LONGBLOB NOT NULL,
            created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            INDEX IDX_TASK_TRACKER_FILES_TRACKER (task_tracker_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS smartsheet_files.task_tracker_files');
    }
}
