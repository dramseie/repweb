<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260211120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Extend smartsheet duration pivot with duration/start/end/% complete columns.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP PROCEDURE IF EXISTS nifi.sp_smartsheet_site_task_duration_pivot');
        $this->addSql(
            "CREATE DEFINER=`webuser`@`%` PROCEDURE `sp_smartsheet_site_task_duration_pivot`()\n"
            . "BEGIN\n"
            . "  DECLARE cols TEXT;\n\n"
            . "  SELECT GROUP_CONCAT(\n"
            . "           CONCAT(\n"
            . "             'MAX(CASE WHEN task_name = ', QUOTE(task_name),\n"
            . "             ' THEN DATEDIFF(end_date, start_date) END) AS `',\n"
            . "             REPLACE(REPLACE(REPLACE(REPLACE(task_name,'`',''),'/', '_'),' ', '_'), '-', '_'),\n"
            . "             '_duration`',\n"
            . "             ', ',\n"
            . "             'MAX(CASE WHEN task_name = ', QUOTE(task_name),\n"
            . "             ' THEN DATE_FORMAT(start_date, ''%Y-%m-%d'') END) AS `',\n"
            . "             REPLACE(REPLACE(REPLACE(REPLACE(task_name,'`',''),'/', '_'),' ', '_'), '-', '_'),\n"
            . "             '_start_date`',\n"
            . "             ', ',\n"
            . "             'MAX(CASE WHEN task_name = ', QUOTE(task_name),\n"
            . "             ' THEN DATE_FORMAT(end_date, ''%Y-%m-%d'') END) AS `',\n"
            . "             REPLACE(REPLACE(REPLACE(REPLACE(task_name,'`',''),'/', '_'),' ', '_'), '-', '_'),\n"
            . "             '_end_date`',\n"
            . "             ', ',\n"
            . "             'MAX(CASE WHEN task_name = ', QUOTE(task_name),\n"
            . "             ' THEN `%_complete` END) AS `',\n"
            . "             REPLACE(REPLACE(REPLACE(REPLACE(task_name,'`',''),'/', '_'),' ', '_'), '-', '_'),\n"
            . "             '_pct_complete`'\n"
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
            . "    GROUP BY task_name\n"
            . "  ) t;\n\n"
            . "  SET @sql = CONCAT(\n"
            . "    'SELECT ',\n"
            . "      'COALESCE(NULLIF(TRIM(country), ''''), '''') AS country_abbr, ',\n"
            . "      'TRIM(REPLACE(REPLACE(REPLACE(site_name, ''IKEAStore - '', ''''), ''IKEA - '', ''''), ''Store - '', '''')) AS site_name_clean, ',\n"
            . "      'CONCAT(COALESCE(NULLIF(TRIM(country), ''''), ''''), '' '', ',\n"
            . "        'TRIM(REPLACE(REPLACE(REPLACE(site_name, ''IKEAStore - '', ''''), ''IKEA - '', ''''), ''Store - '', ''''))',\n"
            . "      ') AS site_label, ',\n"
            . "      cols, ' ',\n"
            . "    'FROM nifi.smartsheet_master_data ',\n"
            . "    'WHERE IFNULL(phase, '''') NOT IN (''Store'',''Country'') ',\n"
            . "    'GROUP BY country_abbr, site_name_clean, site_label ',\n"
            . "    'ORDER BY country_abbr, site_name_clean'\n"
            . "  );\n\n"
            . "  PREPARE stmt FROM @sql;\n"
            . "  EXECUTE stmt;\n"
            . "  DEALLOCATE PREPARE stmt;\n"
            . "END"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP PROCEDURE IF EXISTS nifi.sp_smartsheet_site_task_duration_pivot');
        $this->addSql(
            "CREATE DEFINER=`webuser`@`%` PROCEDURE `sp_smartsheet_site_task_duration_pivot`()\n"
            . "BEGIN\n"
            . "  DECLARE cols TEXT;\n\n"
            . "  SELECT GROUP_CONCAT(\n"
            . "           CONCAT(\n"
            . "             'MAX(CASE WHEN task_name = ', QUOTE(task_name),\n"
            . "             ' THEN DATEDIFF(end_date, start_date) END) AS `',\n"
            . "             REPLACE(REPLACE(REPLACE(REPLACE(task_name,'`',''),'/', '_'),' ', '_'), '-', '_'),\n"
            . "             '_days`',\n"
            . "             ', ',\n"
            . "             'MAX(CASE WHEN task_name = ', QUOTE(task_name),\n"
            . "             ' THEN `%_complete` END) AS `',\n"
            . "             REPLACE(REPLACE(REPLACE(REPLACE(task_name,'`',''),'/', '_'),' ', '_'), '-', '_'),\n"
            . "             '_pct`'\n"
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
            . "    GROUP BY task_name\n"
            . "  ) t;\n\n"
            . "  SET @sql = CONCAT(\n"
            . "    'SELECT ',\n"
            . "      'COALESCE(NULLIF(TRIM(country), ''''), '''') AS country_abbr, ',\n"
            . "      'TRIM(REPLACE(REPLACE(REPLACE(site_name, ''IKEAStore - '', ''''), ''IKEA - '', ''''), ''Store - '', '''')) AS site_name_clean, ',\n"
            . "      'CONCAT(COALESCE(NULLIF(TRIM(country), ''''), ''''), '' '', ',\n"
            . "        'TRIM(REPLACE(REPLACE(REPLACE(site_name, ''IKEAStore - '', ''''), ''IKEA - '', ''''), ''Store - '', ''''))',\n"
            . "      ') AS site_label, ',\n"
            . "      cols, ' ',\n"
            . "    'FROM nifi.smartsheet_master_data ',\n"
            . "    'WHERE IFNULL(phase, '''') NOT IN (''Store'',''Country'') ',\n"
            . "    'GROUP BY country_abbr, site_name_clean, site_label ',\n"
            . "    'ORDER BY country_abbr, site_name_clean'\n"
            . "  );\n\n"
            . "  PREPARE stmt FROM @sql;\n"
            . "  EXECUTE stmt;\n"
            . "  DEALLOCATE PREPARE stmt;\n"
            . "END"
        );
    }
}
