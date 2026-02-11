<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260211150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add name and duration fields to task dependency edges.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE smartsheet_task_dependency_edge ADD name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE smartsheet_task_dependency_edge ADD duration INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE smartsheet_task_dependency_edge DROP COLUMN duration');
        $this->addSql('ALTER TABLE smartsheet_task_dependency_edge DROP COLUMN name');
    }
}
