<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260211184000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Recreate nifi.smartsheet_site_normalization view with original_site_name.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "CREATE OR REPLACE VIEW nifi.smartsheet_site_normalization AS
            SELECT
                CONVERT('wifi_master_plan' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS source_table,
                CONVERT('siteid' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS source_column,
                CONVERT(CAST(siteid AS CHAR(255)) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS raw_id,
                CONVERT(UPPER(REGEXP_REPLACE(CAST(siteid AS CHAR(255)), '[[:space:]+()\\-]', '')) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS normalized_site_id,
                CONVERT(CAST(city AS CHAR(255)) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS original_site_name,
                CONVERT(TRIM(REGEXP_REPLACE(REGEXP_REPLACE(COALESCE(city, ''), '(?i)\\b(IKEAStore|IKEA|Store)\\b', ''), '\\s+', ' ')) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS site_label,
                CONVERT(CAST(country AS CHAR(255)) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS country
            FROM nifi.wifi_master_plan
            WHERE siteid IS NOT NULL AND siteid <> ''

            UNION ALL

            SELECT
                CONVERT('smartsheet_master_data' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS source_table,
                CONVERT('site_id' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS source_column,
                CONVERT(CAST(site_id AS CHAR(255)) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS raw_id,
                CONVERT(UPPER(REGEXP_REPLACE(CAST(site_id AS CHAR(255)), '[[:space:]+()\\-]', '')) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS normalized_site_id,
                CONVERT(CAST(site_name AS CHAR(255)) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS original_site_name,
                CONVERT(TRIM(REGEXP_REPLACE(REGEXP_REPLACE(COALESCE(site_name, ''), '(?i)\\b(IKEAStore|IKEA|Store)\\b', ''), '\\s+', ' ')) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS site_label,
                CONVERT(CAST(country AS CHAR(255)) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS country
            FROM nifi.smartsheet_master_data
            WHERE site_id IS NOT NULL AND site_id <> ''

            UNION ALL

            SELECT
                CONVERT('ikea_vendor_corrections' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS source_table,
                CONVERT('site_id' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS source_column,
                CONVERT(CAST(site_id AS CHAR(255)) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS raw_id,
                CONVERT(UPPER(REGEXP_REPLACE(CAST(site_id AS CHAR(255)), '[[:space:]+()\\-]', '')) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS normalized_site_id,
                CONVERT(CAST(city AS CHAR(255)) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS original_site_name,
                CONVERT(TRIM(REGEXP_REPLACE(REGEXP_REPLACE(COALESCE(city, ''), '(?i)\\b(IKEAStore|IKEA|Store)\\b', ''), '\\s+', ' ')) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS site_label,
                CONVERT(CAST(country AS CHAR(255)) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS country
            FROM nifi.ikea_vendor_corrections
            WHERE site_id IS NOT NULL AND site_id <> ''

            UNION ALL

            SELECT
                CONVERT('ikea_issue_risk_log' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS source_table,
                CONVERT('store_id' USING utf8mb4) COLLATE utf8mb4_unicode_ci AS source_column,
                CONVERT(CAST(store_id AS CHAR(255)) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS raw_id,
                CONVERT(UPPER(REGEXP_REPLACE(CAST(store_id AS CHAR(255)), '[[:space:]+()\\-]', '')) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS normalized_site_id,
                CONVERT(CAST(store_name AS CHAR(255)) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS original_site_name,
                CONVERT(TRIM(REGEXP_REPLACE(REGEXP_REPLACE(COALESCE(store_name, ''), '(?i)\\b(IKEAStore|IKEA|Store)\\b', ''), '\\s+', ' ')) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS site_label,
                CONVERT(CAST(country AS CHAR(255)) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS country
            FROM nifi.ikea_issue_risk_log
            WHERE store_id IS NOT NULL AND store_id <> ''"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP VIEW IF EXISTS nifi.smartsheet_site_normalization');
    }
}
