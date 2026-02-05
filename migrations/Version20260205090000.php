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
            "WHERE LOWER(Task_Name) IN ('assessment execution', 'installation execution', 'store sign off completed')\n" .
            "  AND (\n" .
            "    DATE(Start_Date) BETWEEN\n" .
            "      DATE_SUB(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 7 DAY)\n" .
            "      AND DATE_SUB(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 1 DAY)\n" .
            "    OR DATE(End_Date) BETWEEN\n" .
            "      DATE_SUB(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 7 DAY)\n" .
            "      AND DATE_SUB(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 1 DAY)\n" .
            "  )"
        );

        $this->addSql('DROP VIEW IF EXISTS nifi.smartsheet_programme_overview_view');
        $this->addSql(
            "CREATE VIEW nifi.smartsheet_programme_overview_view AS\n" .
            "SELECT\n" .
            "  t.country AS country,\n" .
            "  COUNT(DISTINCT t.site_key) AS stores,\n" .
            "  COUNT(DISTINCT CASE WHEN t.task_name_lower = 'assessment completed' AND t.status_class = 'done' THEN t.site_key END) AS assessed,\n" .
            "  COUNT(DISTINCT CASE WHEN t.task_name_lower = 'installation execution' AND t.status_class = 'in_progress' THEN t.site_key END) AS ongoing_installations,\n" .
            "  COUNT(DISTINCT CASE WHEN t.task_name_lower = 'installation execution' AND t.status_class = 'done' THEN t.site_key END) AS stores_installed,\n" .
            "  COUNT(DISTINCT CASE WHEN t.task_name_lower = 'store sign off completed' AND t.status_class = 'done' THEN t.site_key END) AS store_signoff,\n" .
            "  MAX(JSON_UNQUOTE(JSON_EXTRACT(o.content, CONCAT('$.\\\"', REPLACE(t.country, '\\\"', '\\\\\\\"'), '\\\".rag')))) AS rag,\n" .
            "  MAX(JSON_UNQUOTE(JSON_EXTRACT(o.content, CONCAT('$.\\\"', REPLACE(t.country, '\\\"', '\\\\\\\"'), '\\\".comment')))) AS comment\n" .
            "FROM (\n" .
            "  SELECT\n" .
            "    COALESCE(NULLIF(TRIM(smartsheet_master_data.country), ''), 'Unspecified') AS country,\n" .
            "    COALESCE(NULLIF(TRIM(smartsheet_master_data.site_id), ''), NULLIF(TRIM(smartsheet_master_data.site_name), '')) AS site_key,\n" .
            "    LCASE(TRIM(smartsheet_master_data.task_name)) AS task_name_lower,\n" .
            "    CASE\n" .
            "      WHEN smartsheet_master_data.status IS NULL OR TRIM(smartsheet_master_data.status) = '' THEN 'not_started'\n" .
            "      WHEN LCASE(smartsheet_master_data.status) LIKE '%not started%' OR LCASE(smartsheet_master_data.status) LIKE '%not_started%' OR LCASE(smartsheet_master_data.status) LIKE '%todo%' OR LCASE(smartsheet_master_data.status) LIKE '%pending%' THEN 'not_started'\n" .
            "      WHEN LCASE(smartsheet_master_data.status) LIKE '%done%' OR LCASE(smartsheet_master_data.status) LIKE '%complete%' OR LCASE(smartsheet_master_data.status) LIKE '%completed%' OR LCASE(smartsheet_master_data.status) LIKE '%sign off%' OR LCASE(smartsheet_master_data.status) LIKE '%signed off%' OR LCASE(smartsheet_master_data.status) LIKE '%closed%' THEN 'done'\n" .
            "      ELSE 'in_progress'\n" .
            "    END AS status_class\n" .
            "  FROM nifi.smartsheet_master_data\n" .
            ") t\n" .
            "CROSS JOIN (\n" .
            "  SELECT content\n" .
            "  FROM repweb.smartsheet_content\n" .
            "  WHERE section = 'overview_overrides'\n" .
            "  ORDER BY created_at DESC\n" .
            "  LIMIT 1\n" .
            ") o\n" .
            "WHERE t.site_key IS NOT NULL\n" .
            "GROUP BY t.country"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP VIEW IF EXISTS nifi.smartsheet_planned_week_view');
        $this->addSql('DROP VIEW IF EXISTS nifi.smartsheet_programme_overview_view');
    }
}
