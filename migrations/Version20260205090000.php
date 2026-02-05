<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260205090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create smartsheet_planned_week_view for last-week planned status rows.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP VIEW IF EXISTS nifi.smartsheet_planned_week_view');
        $this->addSql(
            "CREATE VIEW nifi.smartsheet_planned_week_view AS\n" .
            "SELECT *\n" .
            "FROM nifi.smartsheet_master_data\n" .
            "WHERE (LOWER(Task_Name) LIKE '%assessment%' OR LOWER(Task_Name) LIKE '%installation execution%')\n" .
            "  AND (\n" .
            "    DATE(Start_Date) BETWEEN\n" .
            "      DATE_SUB(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 7 DAY)\n" .
            "      AND DATE_SUB(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 1 DAY)\n" .
            "    OR DATE(End_Date) BETWEEN\n" .
            "      DATE_SUB(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 7 DAY)\n" .
            "      AND DATE_SUB(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 1 DAY)\n" .
            "  )"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP VIEW IF EXISTS nifi.smartsheet_planned_week_view');
    }
}
