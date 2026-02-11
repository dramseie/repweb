<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260211185000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Recreate nifi.smartsheet_site_normalization with prefix/suffix parsing.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "CREATE OR REPLACE VIEW nifi.smartsheet_site_normalization AS
            SELECT
                CONVERT('wifi_master_plan' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS source_table,
                CONVERT('siteid' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS source_column,
                s.raw_id,
                s.normalized_site_id,
                s.prefix,
                s.suffix,
                s.original_site_name,
                s.site_label,
                s.country
            FROM (
                SELECT
                    CONVERT(CAST(siteid AS CHAR(255)) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS raw_id,
                            UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REGEXP_REPLACE(CONVERT(CAST(siteid AS CHAR(255)) USING utf8mb4), '[[:space:]]', ''), '+', ''), '(', ''), ')', ''), '-', ''))
                    CONVERT(CAST(city AS CHAR(255)) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS original_site_name,
                    CONVERT(TRIM(REGEXP_REPLACE(REGEXP_REPLACE(COALESCE(city, ''), '(?i)\\b(IKEAStore|IKEA|Store)\\b', ''), '\\s+', ' ')) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS site_label,
                    CONVERT(CAST(country AS CHAR(255)) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS country,
                    CONVERT(
                            THEN SUBSTRING_INDEX(REGEXP_REPLACE(CONVERT(CAST(siteid AS CHAR(255)) USING utf8mb4), '[[:space:]]', ''), '-', 1)
                            REGEXP_SUBSTR(REGEXP_REPLACE(CONVERT(CAST(siteid AS CHAR(255)) USING utf8mb4), '[[:space:]]', ''), 'R[A-Za-z]+[0-9]+'),
                            UPPER(REGEXP_REPLACE(REGEXP_REPLACE(CONVERT(CAST(siteid AS CHAR(255)) USING utf8mb4), '[[:space:]]', ''), '[+()\\-]', ''))
                        ) USING utf8mb4
                    ) COLLATE utf8mb4_unicode_ci AS normalized_site_id,
                    CONVERT(
                        CASE WHEN INSTR(REGEXP_REPLACE(CONVERT(CAST(siteid AS CHAR(255)) USING utf8mb4), '[[:space:]]', ''), '-') > 0
                                THEN SUBSTRING_INDEX(REGEXP_REPLACE(CONVERT(CAST(siteid AS CHAR(255)) USING utf8mb4), '[[:space:]]', ''), '+', -1)
                            WHEN INSTR(REGEXP_REPLACE(CONVERT(CAST(siteid AS CHAR(255)) USING utf8mb4), '[[:space:]]', ''), '(') > 0
                                THEN SUBSTRING_INDEX(SUBSTRING_INDEX(REGEXP_REPLACE(CONVERT(CAST(siteid AS CHAR(255)) USING utf8mb4), '[[:space:]]', ''), '(', -1), ')', 1)
                    ) COLLATE utf8mb4_unicode_ci AS prefix,
                    CONVERT(
                        CASE
                            WHEN INSTR(REGEXP_REPLACE(CONVERT(CAST(siteid AS CHAR(255)) USING utf8mb4), '[[:space:]]', ''), '+') > 0
                                THEN REGEXP_REPLACE(REGEXP_SUBSTR(REGEXP_REPLACE(CONVERT(CAST(siteid AS CHAR(255)) USING utf8mb4), '[[:space:]]', ''), '\\+[A-Za-z0-9]+'), '^\\+', '')
                            WHEN REGEXP_REPLACE(CONVERT(CAST(siteid AS CHAR(255)) USING utf8mb4), '[[:space:]]', '') REGEXP '\\([A-Za-z0-9]+\\)'
                                THEN REGEXP_REPLACE(REGEXP_SUBSTR(REGEXP_REPLACE(CONVERT(CAST(siteid AS CHAR(255)) USING utf8mb4), '[[:space:]]', ''), '\\([A-Za-z0-9]+\\)'), '[\\(\\)]', '')
                            ELSE NULL
                        END USING utf8mb4
                    ) COLLATE utf8mb4_unicode_ci AS suffix
                FROM nifi.wifi_master_plan
                WHERE siteid IS NOT NULL AND siteid <> ''
            ) s

            UNION ALL

            SELECT
                CONVERT('smartsheet_master_data' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS source_table,
                CONVERT('site_id' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS source_column,
                s.raw_id,
                s.normalized_site_id,
                s.prefix,
                s.suffix,
                s.original_site_name,
                s.site_label,
                s.country
            FROM (
                SELECT
                    CONVERT(CAST(site_id AS CHAR(255)) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS raw_id,
                            UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REGEXP_REPLACE(CONVERT(CAST(site_id AS CHAR(255)) USING utf8mb4), '[[:space:]]', ''), '+', ''), '(', ''), ')', ''), '-', ''))
                    CONVERT(CAST(site_name AS CHAR(255)) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS original_site_name,
                    CONVERT(TRIM(REGEXP_REPLACE(REGEXP_REPLACE(COALESCE(site_name, ''), '(?i)\\b(IKEAStore|IKEA|Store)\\b', ''), '\\s+', ' ')) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS site_label,
                    CONVERT(CAST(country AS CHAR(255)) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS country,
                    CONVERT(
                            THEN SUBSTRING_INDEX(REGEXP_REPLACE(CONVERT(CAST(site_id AS CHAR(255)) USING utf8mb4), '[[:space:]]', ''), '-', 1)
                            REGEXP_SUBSTR(REGEXP_REPLACE(CONVERT(CAST(site_id AS CHAR(255)) USING utf8mb4), '[[:space:]]', ''), 'R[A-Za-z]+[0-9]+'),
                            UPPER(REGEXP_REPLACE(REGEXP_REPLACE(CONVERT(CAST(site_id AS CHAR(255)) USING utf8mb4), '[[:space:]]', ''), '[+()\\-]', ''))
                        ) USING utf8mb4
                    ) COLLATE utf8mb4_unicode_ci AS normalized_site_id,
                    CONVERT(
                        CASE WHEN INSTR(REGEXP_REPLACE(CONVERT(CAST(site_id AS CHAR(255)) USING utf8mb4), '[[:space:]]', ''), '-') > 0
                                THEN SUBSTRING_INDEX(REGEXP_REPLACE(CONVERT(CAST(site_id AS CHAR(255)) USING utf8mb4), '[[:space:]]', ''), '+', -1)
                            WHEN INSTR(REGEXP_REPLACE(CONVERT(CAST(site_id AS CHAR(255)) USING utf8mb4), '[[:space:]]', ''), '(') > 0
                                THEN SUBSTRING_INDEX(SUBSTRING_INDEX(REGEXP_REPLACE(CONVERT(CAST(site_id AS CHAR(255)) USING utf8mb4), '[[:space:]]', ''), '(', -1), ')', 1)
                    ) COLLATE utf8mb4_unicode_ci AS prefix,
                    CONVERT(
                        CASE
                            WHEN INSTR(REGEXP_REPLACE(CONVERT(CAST(site_id AS CHAR(255)) USING utf8mb4), '[[:space:]]', ''), '+') > 0
                                THEN REGEXP_REPLACE(REGEXP_SUBSTR(REGEXP_REPLACE(CONVERT(CAST(site_id AS CHAR(255)) USING utf8mb4), '[[:space:]]', ''), '\\+[A-Za-z0-9]+'), '^\\+', '')
                            WHEN REGEXP_REPLACE(CONVERT(CAST(site_id AS CHAR(255)) USING utf8mb4), '[[:space:]]', '') REGEXP '\\([A-Za-z0-9]+\\)'
                                THEN REGEXP_REPLACE(REGEXP_SUBSTR(REGEXP_REPLACE(CONVERT(CAST(site_id AS CHAR(255)) USING utf8mb4), '[[:space:]]', ''), '\\([A-Za-z0-9]+\\)'), '[\\(\\)]', '')
                            ELSE NULL
                        END USING utf8mb4
                    ) COLLATE utf8mb4_unicode_ci AS suffix
                FROM nifi.smartsheet_master_data
                WHERE site_id IS NOT NULL AND site_id <> ''
            ) s

            UNION ALL

            SELECT
                CONVERT('ikea_vendor_corrections' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS source_table,
                CONVERT('site_id' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS source_column,
                s.raw_id,
                s.normalized_site_id,
                s.prefix,
                s.suffix,
                s.original_site_name,
                s.site_label,
                s.country
            FROM (
                SELECT
                    CONVERT(CAST(site_id AS CHAR(255)) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS raw_id,
                            UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REGEXP_REPLACE(CONVERT(CAST(site_id AS CHAR(255)) USING utf8mb4), '[[:space:]]', ''), '+', ''), '(', ''), ')', ''), '-', ''))
                    CONVERT(CAST(city AS CHAR(255)) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS original_site_name,
                    CONVERT(TRIM(REGEXP_REPLACE(REGEXP_REPLACE(COALESCE(city, ''), '(?i)\\b(IKEAStore|IKEA|Store)\\b', ''), '\\s+', ' ')) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS site_label,
                    CONVERT(CAST(country AS CHAR(255)) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS country,
                    CONVERT(
                            THEN SUBSTRING_INDEX(REGEXP_REPLACE(CONVERT(CAST(site_id AS CHAR(255)) USING utf8mb4), '[[:space:]]', ''), '-', 1)
                            REGEXP_SUBSTR(REGEXP_REPLACE(CONVERT(CAST(site_id AS CHAR(255)) USING utf8mb4), '[[:space:]]', ''), 'R[A-Za-z]+[0-9]+'),
                            UPPER(REGEXP_REPLACE(REGEXP_REPLACE(CONVERT(CAST(site_id AS CHAR(255)) USING utf8mb4), '[[:space:]]', ''), '[+()\\-]', ''))
                        ) USING utf8mb4
                    ) COLLATE utf8mb4_unicode_ci AS normalized_site_id,
                    CONVERT(
                        CASE WHEN INSTR(REGEXP_REPLACE(CONVERT(CAST(site_id AS CHAR(255)) USING utf8mb4), '[[:space:]]', ''), '-') > 0
                                THEN SUBSTRING_INDEX(REGEXP_REPLACE(CONVERT(CAST(site_id AS CHAR(255)) USING utf8mb4), '[[:space:]]', ''), '+', -1)
                            WHEN INSTR(REGEXP_REPLACE(CONVERT(CAST(site_id AS CHAR(255)) USING utf8mb4), '[[:space:]]', ''), '(') > 0
                                THEN SUBSTRING_INDEX(SUBSTRING_INDEX(REGEXP_REPLACE(CONVERT(CAST(site_id AS CHAR(255)) USING utf8mb4), '[[:space:]]', ''), '(', -1), ')', 1)
                    ) COLLATE utf8mb4_unicode_ci AS prefix,
                    CONVERT(
                        CASE
                            WHEN INSTR(REGEXP_REPLACE(CONVERT(CAST(site_id AS CHAR(255)) USING utf8mb4), '[[:space:]]', ''), '+') > 0
                                THEN REGEXP_REPLACE(REGEXP_SUBSTR(REGEXP_REPLACE(CONVERT(CAST(site_id AS CHAR(255)) USING utf8mb4), '[[:space:]]', ''), '\\+[A-Za-z0-9]+'), '^\\+', '')
                            WHEN REGEXP_REPLACE(CONVERT(CAST(site_id AS CHAR(255)) USING utf8mb4), '[[:space:]]', '') REGEXP '\\([A-Za-z0-9]+\\)'
                                THEN REGEXP_REPLACE(REGEXP_SUBSTR(REGEXP_REPLACE(CONVERT(CAST(site_id AS CHAR(255)) USING utf8mb4), '[[:space:]]', ''), '\\([A-Za-z0-9]+\\)'), '[\\(\\)]', '')
                            ELSE NULL
                        END USING utf8mb4
                    ) COLLATE utf8mb4_unicode_ci AS suffix
                FROM nifi.ikea_vendor_corrections
                WHERE site_id IS NOT NULL AND site_id <> ''
            ) s

            UNION ALL

            SELECT
                CONVERT('ikea_issue_risk_log' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS source_table,
                CONVERT('store_id' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS source_column,
                s.raw_id,
                s.normalized_site_id,
                s.prefix,
                s.suffix,
                s.original_site_name,
                s.site_label,
                s.country
            FROM (
                SELECT
                    CONVERT(CAST(store_id AS CHAR(255)) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS raw_id,
                            UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REGEXP_REPLACE(CONVERT(CAST(store_id AS CHAR(255)) USING utf8mb4), '[[:space:]]', ''), '+', ''), '(', ''), ')', ''), '-', ''))
                    CONVERT(CAST(store_name AS CHAR(255)) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS original_site_name,
                    CONVERT(TRIM(REGEXP_REPLACE(REGEXP_REPLACE(COALESCE(store_name, ''), '(?i)\\b(IKEAStore|IKEA|Store)\\b', ''), '\\s+', ' ')) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS site_label,
                    CONVERT(CAST(country AS CHAR(255)) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS country,
                    CONVERT(
                            THEN SUBSTRING_INDEX(REGEXP_REPLACE(CONVERT(CAST(store_id AS CHAR(255)) USING utf8mb4), '[[:space:]]', ''), '-', 1)
                            REGEXP_SUBSTR(REGEXP_REPLACE(CONVERT(CAST(store_id AS CHAR(255)) USING utf8mb4), '[[:space:]]', ''), 'R[A-Za-z]+[0-9]+'),
                            UPPER(REGEXP_REPLACE(REGEXP_REPLACE(CONVERT(CAST(store_id AS CHAR(255)) USING utf8mb4), '[[:space:]]', ''), '[+()\\-]', ''))
                        ) USING utf8mb4
                    ) COLLATE utf8mb4_unicode_ci AS normalized_site_id,
                    CONVERT(
                        CASE WHEN INSTR(REGEXP_REPLACE(CONVERT(CAST(store_id AS CHAR(255)) USING utf8mb4), '[[:space:]]', ''), '-') > 0
                                THEN SUBSTRING_INDEX(REGEXP_REPLACE(CONVERT(CAST(store_id AS CHAR(255)) USING utf8mb4), '[[:space:]]', ''), '+', -1)
                            WHEN INSTR(REGEXP_REPLACE(CONVERT(CAST(store_id AS CHAR(255)) USING utf8mb4), '[[:space:]]', ''), '(') > 0
                                THEN SUBSTRING_INDEX(SUBSTRING_INDEX(REGEXP_REPLACE(CONVERT(CAST(store_id AS CHAR(255)) USING utf8mb4), '[[:space:]]', ''), '(', -1), ')', 1)
                    ) COLLATE utf8mb4_unicode_ci AS prefix,
                    CONVERT(
                        CASE
                            WHEN INSTR(REGEXP_REPLACE(CONVERT(CAST(store_id AS CHAR(255)) USING utf8mb4), '[[:space:]]', ''), '+') > 0
                                THEN REGEXP_REPLACE(REGEXP_SUBSTR(REGEXP_REPLACE(CONVERT(CAST(store_id AS CHAR(255)) USING utf8mb4), '[[:space:]]', ''), '\\+[A-Za-z0-9]+'), '^\\+', '')
                            WHEN REGEXP_REPLACE(CONVERT(CAST(store_id AS CHAR(255)) USING utf8mb4), '[[:space:]]', '') REGEXP '\\([A-Za-z0-9]+\\)'
                                THEN REGEXP_REPLACE(REGEXP_SUBSTR(REGEXP_REPLACE(CONVERT(CAST(store_id AS CHAR(255)) USING utf8mb4), '[[:space:]]', ''), '\\([A-Za-z0-9]+\\)'), '[\\(\\)]', '')
                            ELSE NULL
                        END USING utf8mb4
                    ) COLLATE utf8mb4_unicode_ci AS suffix
                FROM nifi.ikea_issue_risk_log
                WHERE store_id IS NOT NULL AND store_id <> ''
            ) s"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP VIEW IF EXISTS nifi.smartsheet_site_normalization');
    }
}
