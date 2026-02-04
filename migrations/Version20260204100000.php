<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260204100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create smartsheet_country_gantt_view for Country Gantt data.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP VIEW IF EXISTS nifi.smartsheet_country_gantt_view');
        $this->addSql('CREATE VIEW nifi.smartsheet_country_gantt_view AS SELECT * FROM nifi.smartsheet_master_data');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP VIEW IF EXISTS nifi.smartsheet_country_gantt_view');
    }
}
