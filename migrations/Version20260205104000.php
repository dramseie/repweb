<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260205104000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Optimize smartsheet_planned_week_view and add indexes for task/date filters.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP VIEW IF EXISTS nifi.smartsheet_planned_week_view');
        $this->addSql(
            "CREATE VIEW nifi.smartsheet_planned_week_view AS\n" .
            "SELECT\n" .
            "  smartsheet_master_data_lastweek.task_id AS task_id,\n" .
            "  smartsheet_master_data_lastweek.sheet_id AS sheet_id,\n" .
            "  smartsheet_master_data_lastweek.sheet_name AS sheet_name,\n" .
            "  smartsheet_master_data_lastweek.workspace_id AS workspace_id,\n" .
            "  smartsheet_master_data_lastweek.workspace_name AS workspace_name,\n" .
            "  smartsheet_master_data_lastweek.folder_id AS folder_id,\n" .
            "  smartsheet_master_data_lastweek.folder_name AS folder_name,\n" .
            "  smartsheet_master_data_lastweek.row_num AS row_num,\n" .
            "  smartsheet_master_data_lastweek.parent_id AS parent_id,\n" .
            "  smartsheet_master_data_lastweek.indent AS indent,\n" .
            "  smartsheet_master_data_lastweek.expanded AS expanded,\n" .
            "  smartsheet_master_data_lastweek.permalink AS permalink,\n" .
            "  smartsheet_master_data_lastweek.task_created AS task_created,\n" .
            "  smartsheet_master_data_lastweek.task_modified AS task_modified,\n" .
            "  smartsheet_master_data_lastweek.phase AS phase,\n" .
            "  smartsheet_master_data_lastweek.site_id AS site_id,\n" .
            "  smartsheet_master_data_lastweek.country AS country,\n" .
            "  smartsheet_master_data_lastweek.task_name AS task_name,\n" .
            "  smartsheet_master_data_lastweek.replanning AS replanning,\n" .
            "  smartsheet_master_data_lastweek.site_name AS site_name,\n" .
            "  smartsheet_master_data_lastweek.duration AS duration,\n" .
            "  smartsheet_master_data_lastweek.start_date AS start_date,\n" .
            "  smartsheet_master_data_lastweek.end_date AS end_date,\n" .
            "  smartsheet_master_data_lastweek.`%_complete` AS `%_complete`,\n" .
            "  smartsheet_master_data_lastweek.actual_start AS actual_start,\n" .
            "  smartsheet_master_data_lastweek.status AS status,\n" .
            "  smartsheet_master_data_lastweek.actual_finish AS actual_finish,\n" .
            "  smartsheet_master_data_lastweek.predecessors AS predecessors,\n" .
            "  smartsheet_master_data_lastweek.comments AS comments,\n" .
            "  smartsheet_master_data_lastweek.assigned_to AS assigned_to,\n" .
            "  smartsheet_master_data_lastweek.description AS description,\n" .
            "  smartsheet_master_data_lastweek.`7_days_ago` AS `7_days_ago`,\n" .
            "  smartsheet_master_data_lastweek.region AS region,\n" .
            "  smartsheet_master_data_lastweek.baseline_start AS baseline_start,\n" .
            "  smartsheet_master_data_lastweek.baseline_finish AS baseline_finish,\n" .
            "  smartsheet_master_data_lastweek.start AS start,\n" .
            "  smartsheet_master_data_lastweek.finish AS finish,\n" .
            "  smartsheet_master_data_lastweek.hpe_spoc AS hpe_spoc,\n" .
            "  smartsheet_master_data_lastweek.`%_work_complete` AS `%_work_complete`,\n" .
            "  smartsheet_master_data_lastweek.in_scope AS in_scope,\n" .
            "  smartsheet_master_data_lastweek.identifier AS identifier,\n" .
            "  smartsheet_master_data_lastweek.month AS month,\n" .
            "  smartsheet_master_data_lastweek.baseline_start2 AS baseline_start2,\n" .
            "  smartsheet_master_data_lastweek.country_scope AS country_scope,\n" .
            "  smartsheet_master_data_lastweek.baseline_finish2 AS baseline_finish2,\n" .
            "  smartsheet_master_data_lastweek.end_month AS end_month,\n" .
            "  smartsheet_master_data_lastweek.start_month AS start_month,\n" .
            "  smartsheet_master_data_lastweek.current_month AS current_month,\n" .
            "  smartsheet_master_data_lastweek.variance AS variance,\n" .
            "  smartsheet_master_data_lastweek.last_month AS last_month,\n" .
            "  smartsheet_master_data_lastweek.next_month AS next_month,\n" .
            "  smartsheet_master_data_lastweek.start_activities AS start_activities,\n" .
            "  smartsheet_master_data_lastweek.end_activities AS end_activities,\n" .
            "  JSON_UNQUOTE(JSON_EXTRACT(COALESCE(o.content, '{}'), CONCAT('$.\\\"', REPLACE(\n" .
            "    LOWER(CONCAT_WS('||',\n" .
            "      COALESCE(NULLIF(TRIM(smartsheet_master_data_lastweek.country), ''), 'Unspecified'),\n" .
            "      COALESCE(NULLIF(TRIM(smartsheet_master_data_lastweek.site_id), ''), NULLIF(TRIM(smartsheet_master_data_lastweek.site_name), '')),\n" .
            "      TRIM(smartsheet_master_data_lastweek.task_name),\n" .
            "      DATE(smartsheet_master_data_lastweek.start_date),\n" .
            "      DATE(smartsheet_master_data_lastweek.end_date)\n" .
            "    )), '\\\"', '\\\\\\\"'), '\\\"'))) AS comment\n" .
            "FROM nifi.smartsheet_master_data_lastweek\n" .
            "LEFT JOIN (\n" .
            "  SELECT content\n" .
            "  FROM repweb.smartsheet_content\n" .
            "  WHERE section = 'planned_week_comments'\n" .
            "  ORDER BY created_at DESC\n" .
            "  LIMIT 1\n" .
            ") o ON 1 = 1\n" .
            "WHERE smartsheet_master_data_lastweek.task_name IN (\n" .
            "  'Assessment Execution', 'assessment execution',\n" .
            "  'Installation execution', 'installation execution'\n" .
            ")\n" .
            "  AND (\n" .
            "    DATE(smartsheet_master_data_lastweek.start_date) BETWEEN\n" .
            "      DATE_SUB(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 7 DAY)\n" .
            "      AND DATE_SUB(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 1 DAY)\n" .
            "    OR DATE(smartsheet_master_data_lastweek.end_date) BETWEEN\n" .
            "      DATE_SUB(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 7 DAY)\n" .
            "      AND DATE_SUB(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 1 DAY)\n" .
            "  )"
        );

        $this->addSql('CREATE INDEX idx_smd_task_name ON nifi.smartsheet_master_data_lastweek (task_name)');
        $this->addSql('CREATE INDEX idx_smd_start_date ON nifi.smartsheet_master_data_lastweek (start_date)');
        $this->addSql('CREATE INDEX idx_smd_end_date ON nifi.smartsheet_master_data_lastweek (end_date)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP VIEW IF EXISTS nifi.smartsheet_planned_week_view');
        $this->addSql('DROP INDEX idx_smd_task_name ON nifi.smartsheet_master_data_lastweek');
        $this->addSql('DROP INDEX idx_smd_start_date ON nifi.smartsheet_master_data_lastweek');
        $this->addSql('DROP INDEX idx_smd_end_date ON nifi.smartsheet_master_data_lastweek');
    }
}
