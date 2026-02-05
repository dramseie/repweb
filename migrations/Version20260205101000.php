<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260205101000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Recreate smartsheet_planned_week_view with comment from planned_week_comments overrides.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP VIEW IF EXISTS nifi.smartsheet_planned_week_view');
        $this->addSql(
            "CREATE VIEW nifi.smartsheet_planned_week_view AS\n" .
            "SELECT\n" .
            "  smartsheet_master_data.task_id AS task_id,\n" .
            "  smartsheet_master_data.sheet_id AS sheet_id,\n" .
            "  smartsheet_master_data.sheet_name AS sheet_name,\n" .
            "  smartsheet_master_data.workspace_id AS workspace_id,\n" .
            "  smartsheet_master_data.workspace_name AS workspace_name,\n" .
            "  smartsheet_master_data.folder_id AS folder_id,\n" .
            "  smartsheet_master_data.folder_name AS folder_name,\n" .
            "  smartsheet_master_data.row_num AS row_num,\n" .
            "  smartsheet_master_data.parent_id AS parent_id,\n" .
            "  smartsheet_master_data.indent AS indent,\n" .
            "  smartsheet_master_data.expanded AS expanded,\n" .
            "  smartsheet_master_data.permalink AS permalink,\n" .
            "  smartsheet_master_data.task_created AS task_created,\n" .
            "  smartsheet_master_data.task_modified AS task_modified,\n" .
            "  smartsheet_master_data.phase AS phase,\n" .
            "  smartsheet_master_data.site_id AS site_id,\n" .
            "  smartsheet_master_data.country AS country,\n" .
            "  smartsheet_master_data.task_name AS task_name,\n" .
            "  smartsheet_master_data.replanning AS replanning,\n" .
            "  smartsheet_master_data.site_name AS site_name,\n" .
            "  smartsheet_master_data.duration AS duration,\n" .
            "  smartsheet_master_data.start_date AS start_date,\n" .
            "  smartsheet_master_data.end_date AS end_date,\n" .
            "  smartsheet_master_data.`%_complete` AS `%_complete`,\n" .
            "  smartsheet_master_data.actual_start AS actual_start,\n" .
            "  smartsheet_master_data.status AS status,\n" .
            "  smartsheet_master_data.actual_finish AS actual_finish,\n" .
            "  smartsheet_master_data.predecessors AS predecessors,\n" .
            "  smartsheet_master_data.comments AS comments,\n" .
            "  smartsheet_master_data.assigned_to AS assigned_to,\n" .
            "  smartsheet_master_data.description AS description,\n" .
            "  smartsheet_master_data.`7_days_ago` AS `7_days_ago`,\n" .
            "  smartsheet_master_data.region AS region,\n" .
            "  smartsheet_master_data.baseline_start AS baseline_start,\n" .
            "  smartsheet_master_data.baseline_finish AS baseline_finish,\n" .
            "  smartsheet_master_data.start AS start,\n" .
            "  smartsheet_master_data.finish AS finish,\n" .
            "  smartsheet_master_data.hpe_spoc AS hpe_spoc,\n" .
            "  smartsheet_master_data.`%_work_complete` AS `%_work_complete`,\n" .
            "  smartsheet_master_data.in_scope AS in_scope,\n" .
            "  smartsheet_master_data.identifier AS identifier,\n" .
            "  smartsheet_master_data.month AS month,\n" .
            "  smartsheet_master_data.baseline_start2 AS baseline_start2,\n" .
            "  smartsheet_master_data.country_scope AS country_scope,\n" .
            "  smartsheet_master_data.baseline_finish2 AS baseline_finish2,\n" .
            "  smartsheet_master_data.end_month AS end_month,\n" .
            "  smartsheet_master_data.start_month AS start_month,\n" .
            "  smartsheet_master_data.current_month AS current_month,\n" .
            "  smartsheet_master_data.variance AS variance,\n" .
            "  smartsheet_master_data.last_month AS last_month,\n" .
            "  smartsheet_master_data.next_month AS next_month,\n" .
            "  smartsheet_master_data.start_activities AS start_activities,\n" .
            "  smartsheet_master_data.end_activities AS end_activities,\n" .
            "  JSON_UNQUOTE(JSON_EXTRACT(o.content, CONCAT('$.\\\"', REPLACE(\n" .
            "    LOWER(CONCAT_WS('||',\n" .
            "      COALESCE(NULLIF(TRIM(smartsheet_master_data.country), ''), 'Unspecified'),\n" .
            "      COALESCE(NULLIF(TRIM(smartsheet_master_data.site_id), ''), NULLIF(TRIM(smartsheet_master_data.site_name), '')),\n" .
            "      TRIM(smartsheet_master_data.task_name),\n" .
            "      DATE(smartsheet_master_data.start_date),\n" .
            "      DATE(smartsheet_master_data.end_date)\n" .
            "    )), '\\\"', '\\\\\\\"'), '\\\"'))) AS comment\n" .
            "FROM nifi.smartsheet_master_data\n" .
            "CROSS JOIN (\n" .
            "  SELECT content\n" .
            "  FROM repweb.smartsheet_content\n" .
            "  WHERE section = 'planned_week_comments'\n" .
            "  ORDER BY created_at DESC\n" .
            "  LIMIT 1\n" .
            ") o\n" .
            "WHERE LOWER(smartsheet_master_data.task_name) IN ('assessment execution', 'installation execution')\n" .
            "  AND (\n" .
            "    DATE(smartsheet_master_data.start_date) BETWEEN\n" .
            "      DATE_SUB(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 7 DAY)\n" .
            "      AND DATE_SUB(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 1 DAY)\n" .
            "    OR DATE(smartsheet_master_data.end_date) BETWEEN\n" .
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
