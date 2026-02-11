<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260211153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add duration field to task dependency nodes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE smartsheet_task_dependency_node ADD duration INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE smartsheet_task_dependency_node DROP COLUMN duration');
    }
}
