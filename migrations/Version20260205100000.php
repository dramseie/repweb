<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260205100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Recreate smartsheet_programme_overview_view with fully qualified tables.';
    }

    public function up(Schema $schema): void
    {
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
            "    COALESCE(NULLIF(TRIM(nifi.smartsheet_master_data.country), ''), 'Unspecified') AS country,\n" .
            "    COALESCE(NULLIF(TRIM(nifi.smartsheet_master_data.site_id), ''), NULLIF(TRIM(nifi.smartsheet_master_data.site_name), '')) AS site_key,\n" .
            "    LCASE(TRIM(nifi.smartsheet_master_data.task_name)) AS task_name_lower,\n" .
            "    CASE\n" .
            "      WHEN nifi.smartsheet_master_data.status IS NULL OR TRIM(nifi.smartsheet_master_data.status) = '' THEN 'not_started'\n" .
            "      WHEN LCASE(nifi.smartsheet_master_data.status) LIKE '%not started%' OR LCASE(nifi.smartsheet_master_data.status) LIKE '%not_started%' OR LCASE(nifi.smartsheet_master_data.status) LIKE '%todo%' OR LCASE(nifi.smartsheet_master_data.status) LIKE '%pending%' THEN 'not_started'\n" .
            "      WHEN LCASE(nifi.smartsheet_master_data.status) LIKE '%done%' OR LCASE(nifi.smartsheet_master_data.status) LIKE '%complete%' OR LCASE(nifi.smartsheet_master_data.status) LIKE '%completed%' OR LCASE(nifi.smartsheet_master_data.status) LIKE '%sign off%' OR LCASE(nifi.smartsheet_master_data.status) LIKE '%signed off%' OR LCASE(nifi.smartsheet_master_data.status) LIKE '%closed%' THEN 'done'\n" .
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
        $this->addSql('DROP VIEW IF EXISTS nifi.smartsheet_programme_overview_view');
    }
}
