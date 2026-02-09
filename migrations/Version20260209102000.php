<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260209102000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create smartsheet_task_name_view for trend task selector.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP VIEW IF EXISTS nifi.smartsheet_task_name_view');
        $this->addSql(
            "CREATE VIEW nifi.smartsheet_task_name_view AS\n"
            . "SELECT DISTINCT task_name\n"
            . "FROM nifi.smartsheet_master_data\n"
            . "WHERE IFNULL(phase, '') NOT IN ('Store', 'Country')\n"
            . "  AND IFNULL(task_name, '') <> ''"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP VIEW IF EXISTS nifi.smartsheet_task_name_view');
    }
}
