<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260211170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add duration mode to task dependency nodes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE smartsheet_task_dependency_node ADD duration_mode VARCHAR(20) NOT NULL DEFAULT 'ignore'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE smartsheet_task_dependency_node DROP COLUMN duration_mode');
    }
}
