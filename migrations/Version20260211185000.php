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
        $sql = <<<'SQL'
CREATE OR REPLACE VIEW nifi.smartsheet_site_normalization AS
SELECT
  'wifi_master_plan' AS source_table,
  'siteid' AS source_column,
  v.raw_id,
  v.normalized_site_id,
  v.prefix,
  v.suffix,
  v.original_site_name,
  v.site_label,
  v.country
FROM (
  SELECT
    CAST(siteid AS CHAR(255)) AS raw_id,
    REGEXP_REPLACE(CAST(siteid AS CHAR(255)), '[[:space:]]+', '') AS clean_id,
    CAST(city AS CHAR(255)) AS original_site_name,
    TRIM(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(city,''), 'IKEAStore',''), 'IKEA',''), 'Store',''), '  ',' '), '  ',' ')) AS site_label,
    CAST(country AS CHAR(255)) AS country,
    CASE
      WHEN clean_id REGEXP '[- ]' THEN REGEXP_SUBSTR(clean_id, '^[^\- ]+')
      ELSE NULL
    END AS prefix,
    CASE
      WHEN clean_id REGEXP '\\+[^+]+$' THEN REGEXP_REPLACE(REGEXP_SUBSTR(clean_id, '\\+[^+]+$'), '^\\+', '')
      WHEN clean_id REGEXP '\\([^\\)]+\\)$' THEN REGEXP_REPLACE(REGEXP_SUBSTR(clean_id, '\\([^\\)]+\\)$'), '[\\(\\)]', '')
      ELSE NULL
    END AS suffix,
    UPPER(
      REGEXP_REPLACE(
        REGEXP_REPLACE(
          REGEXP_REPLACE(clean_id, '^[^\\- ]+[\\- ]', ''),
          '\\+.*$',
          ''
        ),
        '\\(.*\\)$',
        ''
      )
    ) AS normalized_site_id
  FROM nifi.wifi_master_plan
  WHERE siteid IS NOT NULL AND siteid <> ''
) v

UNION ALL

SELECT
  'smartsheet_master_data' AS source_table,
  'site_id' AS source_column,
  v.raw_id,
  v.normalized_site_id,
  v.prefix,
  v.suffix,
  v.original_site_name,
  v.site_label,
  v.country
FROM (
  SELECT
    CAST(site_id AS CHAR(255)) AS raw_id,
    REGEXP_REPLACE(CAST(site_id AS CHAR(255)), '[[:space:]]+', '') AS clean_id,
    CAST(site_name AS CHAR(255)) AS original_site_name,
    TRIM(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(site_name,''), 'IKEAStore',''), 'IKEA',''), 'Store',''), '  ',' '), '  ',' ')) AS site_label,
    CAST(country AS CHAR(255)) AS country,
    CASE
      WHEN clean_id REGEXP '[- ]' THEN REGEXP_SUBSTR(clean_id, '^[^\- ]+')
      ELSE NULL
    END AS prefix,
    CASE
      WHEN clean_id REGEXP '\\+[^+]+$' THEN REGEXP_REPLACE(REGEXP_SUBSTR(clean_id, '\\+[^+]+$'), '^\\+', '')
      WHEN clean_id REGEXP '\\([^\\)]+\\)$' THEN REGEXP_REPLACE(REGEXP_SUBSTR(clean_id, '\\([^\\)]+\\)$'), '[\\(\\)]', '')
      ELSE NULL
    END AS suffix,
    UPPER(
      REGEXP_REPLACE(
        REGEXP_REPLACE(
          REGEXP_REPLACE(clean_id, '^[^\\- ]+[\\- ]', ''),
          '\\+.*$',
          ''
        ),
        '\\(.*\\)$',
        ''
      )
    ) AS normalized_site_id
  FROM nifi.smartsheet_master_data
  WHERE site_id IS NOT NULL AND site_id <> ''
) v

UNION ALL

SELECT
  'ikea_vendor_corrections' AS source_table,
  'site_id' AS source_column,
  v.raw_id,
  v.normalized_site_id,
  v.prefix,
  v.suffix,
  v.original_site_name,
  v.site_label,
  v.country
FROM (
  SELECT
    CAST(site_id AS CHAR(255)) AS raw_id,
    REGEXP_REPLACE(CAST(site_id AS CHAR(255)), '[[:space:]]+', '') AS clean_id,
    CAST(city AS CHAR(255)) AS original_site_name,
    TRIM(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(city,''), 'IKEAStore',''), 'IKEA',''), 'Store',''), '  ',' '), '  ',' ')) AS site_label,
    CAST(country AS CHAR(255)) AS country,
    CASE
      WHEN clean_id REGEXP '[- ]' THEN REGEXP_SUBSTR(clean_id, '^[^\- ]+')
      ELSE NULL
    END AS prefix,
    CASE
      WHEN clean_id REGEXP '\\+[^+]+$' THEN REGEXP_REPLACE(REGEXP_SUBSTR(clean_id, '\\+[^+]+$'), '^\\+', '')
      WHEN clean_id REGEXP '\\([^\\)]+\\)$' THEN REGEXP_REPLACE(REGEXP_SUBSTR(clean_id, '\\([^\\)]+\\)$'), '[\\(\\)]', '')
      ELSE NULL
    END AS suffix,
    UPPER(
      REGEXP_REPLACE(
        REGEXP_REPLACE(
          REGEXP_REPLACE(clean_id, '^[^\\- ]+[\\- ]', ''),
          '\\+.*$',
          ''
        ),
        '\\(.*\\)$',
        ''
      )
    ) AS normalized_site_id
  FROM nifi.ikea_vendor_corrections
  WHERE site_id IS NOT NULL AND site_id <> ''
) v

UNION ALL

SELECT
  'ikea_issue_risk_log' AS source_table,
  'store_id' AS source_column,
  v.raw_id,
  v.normalized_site_id,
  v.prefix,
  v.suffix,
  v.original_site_name,
  v.site_label,
  v.country
FROM (
  SELECT
    CAST(store_id AS CHAR(255)) AS raw_id,
    REGEXP_REPLACE(CAST(store_id AS CHAR(255)), '[[:space:]]+', '') AS clean_id,
    CAST(store_name AS CHAR(255)) AS original_site_name,
    TRIM(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(store_name,''), 'IKEAStore',''), 'IKEA',''), 'Store',''), '  ',' '), '  ',' ')) AS site_label,
    CAST(country AS CHAR(255)) AS country,
    CASE
      WHEN clean_id REGEXP '[- ]' THEN REGEXP_SUBSTR(clean_id, '^[^\- ]+')
      ELSE NULL
    END AS prefix,
    CASE
      WHEN clean_id REGEXP '\\+[^+]+$' THEN REGEXP_REPLACE(REGEXP_SUBSTR(clean_id, '\\+[^+]+$'), '^\\+', '')
      WHEN clean_id REGEXP '\\([^\\)]+\\)$' THEN REGEXP_REPLACE(REGEXP_SUBSTR(clean_id, '\\([^\\)]+\\)$'), '[\\(\\)]', '')
      ELSE NULL
    END AS suffix,
    UPPER(
      REGEXP_REPLACE(
        REGEXP_REPLACE(
          REGEXP_REPLACE(clean_id, '^[^\\- ]+[\\- ]', ''),
          '\\+.*$',
          ''
        ),
        '\\(.*\\)$',
        ''
      )
    ) AS normalized_site_id
  FROM nifi.ikea_issue_risk_log
  WHERE store_id IS NOT NULL AND store_id <> ''
) v
SQL;

        $this->addSql($sql);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP VIEW IF EXISTS nifi.smartsheet_site_normalization');
    }
}
