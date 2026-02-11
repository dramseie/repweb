<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260211180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add task manager table for task hierarchy.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE smartsheet_task_manager (id INT AUTO_INCREMENT NOT NULL, task_name VARCHAR(255) NOT NULL, parent_id INT DEFAULT NULL, sort_order INT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_5C6E4D6E727ACA70 (parent_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE smartsheet_task_manager ADD CONSTRAINT FK_5C6E4D6E727ACA70 FOREIGN KEY (parent_id) REFERENCES smartsheet_task_manager (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE smartsheet_task_manager');
    }
}
