<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260212171500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix analysis pivot procedure country ambiguity.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP PROCEDURE IF EXISTS nifi.sp_smartsheet_site_task_analysis_pivot');
        $this->addSql(
            "CREATE DEFINER=`webuser`@`%` PROCEDURE `nifi`.`sp_smartsheet_site_task_analysis_pivot`(IN p_tasks TEXT, IN p_columns TEXT)\n"
            . "BEGIN\n"
            . "  DECLARE cols TEXT;\n"
            . "  DECLARE cols_normalized TEXT;\n"
            . "  DECLARE show_start_date TINYINT(1);\n"
            . "  DECLARE show_end_date TINYINT(1);\n"
            . "  DECLARE show_pct_complete TINYINT(1);\n"
            . "  DECLARE show_status TINYINT(1);\n"
            . "  DECLARE task_filter TEXT;\n\n"
            . "  SET cols_normalized = LOWER(REPLACE(IFNULL(p_columns, ''), ' ', ''));\n"
            . "  SET show_start_date = FIND_IN_SET('start_date', cols_normalized) > 0;\n"
            . "  SET show_end_date = FIND_IN_SET('end_date', cols_normalized) > 0;\n"
            . "  SET show_pct_complete = FIND_IN_SET('pct_complete', cols_normalized) > 0;\n"
            . "  SET show_status = FIND_IN_SET('status', cols_normalized) > 0;\n\n"
            . "  IF show_start_date = 0 AND show_end_date = 0 AND show_pct_complete = 0 AND show_status = 0 THEN\n"
            . "    SET show_start_date = 1;\n"
            . "    SET show_end_date = 1;\n"
            . "    SET show_pct_complete = 1;\n"
            . "    SET show_status = 1;\n"
            . "  END IF;\n\n"
            . "  SELECT GROUP_CONCAT(\n"
            . "           CONCAT(\n"
            . "             IF(show_start_date,\n"
            . "               CONCAT(\n"
            . "                 'MAX(CASE WHEN task_name = ', QUOTE(task_name),\n"
            . "                 ' THEN DATE_FORMAT(start_date, ''%Y-%m-%d'') END) AS `',\n"
            . "                 REPLACE(REPLACE(REPLACE(REPLACE(task_name,'`',''),'/', '_'),' ', '_'), '-', '_'),\n"
            . "                 '_start_date`'\n"
            . "               ),\n"
            . "               ''\n"
            . "             ),\n"
            . "             IF(show_end_date,\n"
            . "               CONCAT(\n"
            . "                 IF(show_start_date, ', ', ''),\n"
            . "                 'MAX(CASE WHEN task_name = ', QUOTE(task_name),\n"
            . "                 ' THEN DATE_FORMAT(end_date, ''%Y-%m-%d'') END) AS `',\n"
            . "                 REPLACE(REPLACE(REPLACE(REPLACE(task_name,'`',''),'/', '_'),' ', '_'), '-', '_'),\n"
            . "                 '_end_date`'\n"
            . "               ),\n"
            . "               ''\n"
            . "             ),\n"
            . "             IF(show_pct_complete,\n"
            . "               CONCAT(\n"
            . "                 IF(show_start_date OR show_end_date, ', ', ''),\n"
            . "                 'MAX(CASE WHEN task_name = ', QUOTE(task_name),\n"
            . "                 ' THEN `%_complete` END) AS `',\n"
            . "                 REPLACE(REPLACE(REPLACE(REPLACE(task_name,'`',''),'/', '_'),' ', '_'), '-', '_'),\n"
            . "                 '_pct_complete`'\n"
            . "               ),\n"
            . "               ''\n"
            . "             ),\n"
            . "             IF(show_status,\n"
            . "               CONCAT(\n"
            . "                 IF(show_start_date OR show_end_date OR show_pct_complete, ', ', ''),\n"
            . "                 'MAX(CASE WHEN task_name = ', QUOTE(task_name),\n"
            . "                 ' THEN status END) AS `',\n"
            . "                 REPLACE(REPLACE(REPLACE(REPLACE(task_name,'`',''),'/', '_'),' ', '_'), '-', '_'),\n"
            . "                 '_status`'\n"
            . "               ),\n"
            . "               ''\n"
            . "             )\n"
            . "           )\n"
            . "           ORDER BY min_row_num\n"
            . "           SEPARATOR ', '\n"
            . "         )\n"
            . "    INTO cols\n"
            . "  FROM (\n"
            . "    SELECT task_name, MIN(row_num) AS min_row_num\n"
            . "    FROM nifi.smartsheet_master_data\n"
            . "    WHERE IFNULL(phase, '') NOT IN ('Store','Country')\n"
            . "      AND task_name IS NOT NULL\n"
            . "      AND task_name <> ''\n"
            . "      AND (IFNULL(p_tasks, '') = '' OR FIND_IN_SET(task_name, p_tasks) > 0)\n"
            . "    GROUP BY task_name\n"
            . "  ) t;\n\n"
            . "  SET task_filter = '';\n"
            . "  IF IFNULL(p_tasks, '') <> '' THEN\n"
            . "    SET task_filter = CONCAT(' AND FIND_IN_SET(task_name, ', QUOTE(p_tasks), ') > 0 ');\n"
            . "  END IF;\n\n"
            . "  SET @sql = CONCAT(\n"
            . "    'SELECT ',\n"
            . "      'COALESCE(NULLIF(TRIM(smd.country), ''''), '''') AS country, ',\n"
            . "      'COALESCE(ns.site_label, TRIM(site_name)) AS site_label, ',\n"
            . "      'COALESCE(ns.normalized_site_id, site_id) AS normalized_site_id, ',\n"
            . "      cols, ' ',\n"
            . "    'FROM nifi.smartsheet_master_data smd ',\n"
            . "    'LEFT JOIN nifi.smartsheet_site_normalization ns ',\n"
            . "      'ON ns.source_table = ''smartsheet_master_data'' AND ns.raw_id = smd.site_id ',\n"
            . "    'WHERE IFNULL(phase, '''') NOT IN (''Store'',''Country'') ',\n"
            . "    task_filter,\n"
            . "    'GROUP BY country, site_label, normalized_site_id ',\n"
            . "    'ORDER BY country, site_label, normalized_site_id'\n"
            . "  );\n\n"
            . "  PREPARE stmt FROM @sql;\n"
            . "  EXECUTE stmt;\n"
            . "  DEALLOCATE PREPARE stmt;\n"
            . "END"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP PROCEDURE IF EXISTS nifi.sp_smartsheet_site_task_analysis_pivot');
    }
}
