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
    base.raw_id,
    base.original_site_name,
    base.site_label,
    base.country,
    CASE
      WHEN base.clean_id REGEXP 'RET[A-Z]{2}[0-9]{3,}'
        THEN NULLIF(REGEXP_REPLACE(base.clean_id, 'RET[A-Z]{2}[0-9]{3,}.*$', ''), '')
      ELSE NULL
    END AS prefix,
    CASE
      WHEN base.clean_id REGEXP 'RET[A-Z]{2}[0-9]{3,}' THEN NULLIF(
        REGEXP_REPLACE(
          REGEXP_REPLACE(base.clean_id, '^.*RET[A-Z]{2}[0-9]{3,}', ''),
          '^[+()\-]+',
          ''
        ),
        ''
      )
      ELSE NULL
    END AS suffix,
    CASE
      WHEN base.clean_id REGEXP 'RET[A-Z]{2}[0-9]{3,}'
        THEN UPPER(REGEXP_SUBSTR(base.clean_id, 'RET[A-Z]{2}[0-9]{3,}'))
      ELSE NULL
    END AS normalized_site_id
  FROM (
    SELECT
      CAST(siteid AS CHAR(255)) AS raw_id,
      REGEXP_REPLACE(CAST(siteid AS CHAR(255)), '[[:space:]]+', '') AS clean_id,
      CAST(city AS CHAR(255)) AS original_site_name,
      REGEXP_REPLACE(
        TRIM(
          REGEXP_REPLACE(
            REGEXP_REPLACE(
              REGEXP_REPLACE(
                REGEXP_REPLACE(COALESCE(city,''), '[Ii][Kk][Ee][Aa][Ss][Tt][Oo][Rr][Ee]', ''),
                '[Ii][Kk][Ee][Aa]', ''
              ),
              '[Ss][Tt][Oo][Rr][Ee]', ''
            ),
            '[[:space:]]+',
            ' '
          )
        ),
        '^[-[:space:]]+',
        ''
      ) AS site_label,
      CAST(country AS CHAR(255)) AS country
    FROM nifi.wifi_master_plan
    WHERE siteid IS NOT NULL AND siteid <> ''
  ) base
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
    base.raw_id,
    base.original_site_name,
    base.site_label,
    base.country,
    CASE
      WHEN base.clean_id REGEXP 'RET[A-Z]{2}[0-9]{3,}'
        THEN NULLIF(REGEXP_REPLACE(base.clean_id, 'RET[A-Z]{2}[0-9]{3,}.*$', ''), '')
      ELSE NULL
    END AS prefix,
    CASE
      WHEN base.clean_id REGEXP 'RET[A-Z]{2}[0-9]{3,}' THEN NULLIF(
        REGEXP_REPLACE(
          REGEXP_REPLACE(base.clean_id, '^.*RET[A-Z]{2}[0-9]{3,}', ''),
          '^[+()\-]+',
          ''
        ),
        ''
      )
      ELSE NULL
    END AS suffix,
    CASE
      WHEN base.clean_id REGEXP 'RET[A-Z]{2}[0-9]{3,}'
        THEN UPPER(REGEXP_SUBSTR(base.clean_id, 'RET[A-Z]{2}[0-9]{3,}'))
      ELSE NULL
    END AS normalized_site_id
  FROM (
    SELECT
      CAST(site_id AS CHAR(255)) AS raw_id,
      REGEXP_REPLACE(CAST(site_id AS CHAR(255)), '[[:space:]]+', '') AS clean_id,
      CAST(site_name AS CHAR(255)) AS original_site_name,
      REGEXP_REPLACE(
        TRIM(
          REGEXP_REPLACE(
            REGEXP_REPLACE(
              REGEXP_REPLACE(
                REGEXP_REPLACE(COALESCE(site_name,''), '[Ii][Kk][Ee][Aa][Ss][Tt][Oo][Rr][Ee]', ''),
                '[Ii][Kk][Ee][Aa]', ''
              ),
              '[Ss][Tt][Oo][Rr][Ee]', ''
            ),
            '[[:space:]]+',
            ' '
          )
        ),
        '^[-[:space:]]+',
        ''
      ) AS site_label,
      CAST(country AS CHAR(255)) AS country
    FROM nifi.smartsheet_master_data
    WHERE site_id IS NOT NULL AND site_id <> ''
  ) base
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
    base.raw_id,
    base.original_site_name,
    base.site_label,
    base.country,
    CASE
      WHEN base.clean_id REGEXP 'RET[A-Z]{2}[0-9]{3,}'
        THEN NULLIF(REGEXP_REPLACE(base.clean_id, 'RET[A-Z]{2}[0-9]{3,}.*$', ''), '')
      ELSE NULL
    END AS prefix,
    CASE
      WHEN base.clean_id REGEXP 'RET[A-Z]{2}[0-9]{3,}' THEN NULLIF(
        REGEXP_REPLACE(
          REGEXP_REPLACE(base.clean_id, '^.*RET[A-Z]{2}[0-9]{3,}', ''),
          '^[+()\-]+',
          ''
        ),
        ''
      )
      ELSE NULL
    END AS suffix,
    CASE
      WHEN base.clean_id REGEXP 'RET[A-Z]{2}[0-9]{3,}'
        THEN UPPER(REGEXP_SUBSTR(base.clean_id, 'RET[A-Z]{2}[0-9]{3,}'))
      ELSE NULL
    END AS normalized_site_id
  FROM (
    SELECT
      CAST(site_id AS CHAR(255)) AS raw_id,
      CASE
        WHEN REGEXP_REPLACE(CAST(site_id AS CHAR(255)), '[[:space:]]+', '') REGEXP 'RET[A-Z]{2}[0-9]{3,}'
          THEN REGEXP_REPLACE(CAST(site_id AS CHAR(255)), '[[:space:]]+', '')
        ELSE CONCAT('RET', REGEXP_REPLACE(CAST(site_id AS CHAR(255)), '[[:space:]]+', ''))
      END AS clean_id,
      CAST(city AS CHAR(255)) AS original_site_name,
      REGEXP_REPLACE(
        TRIM(
          REGEXP_REPLACE(
            REGEXP_REPLACE(
              REGEXP_REPLACE(
                REGEXP_REPLACE(COALESCE(city,''), '[Ii][Kk][Ee][Aa][Ss][Tt][Oo][Rr][Ee]', ''),
                '[Ii][Kk][Ee][Aa]', ''
              ),
              '[Ss][Tt][Oo][Rr][Ee]', ''
            ),
            '[[:space:]]+',
            ' '
          )
        ),
        '^[-[:space:]]+',
        ''
      ) AS site_label,
      CAST(country AS CHAR(255)) AS country
    FROM nifi.ikea_vendor_corrections
    WHERE site_id IS NOT NULL AND site_id <> ''
  ) base
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
    base.raw_id,
    base.original_site_name,
    base.site_label,
    base.country,
    CASE
      WHEN base.clean_id REGEXP 'RET[A-Z]{2}[0-9]{3,}'
        THEN NULLIF(REGEXP_REPLACE(base.clean_id, 'RET[A-Z]{2}[0-9]{3,}.*$', ''), '')
      ELSE NULL
    END AS prefix,
    CASE
      WHEN base.clean_id REGEXP 'RET[A-Z]{2}[0-9]{3,}' THEN NULLIF(
        REGEXP_REPLACE(
          REGEXP_REPLACE(base.clean_id, '^.*RET[A-Z]{2}[0-9]{3,}', ''),
          '^[+()\-]+',
          ''
        ),
        ''
      )
      ELSE NULL
    END AS suffix,
    CASE
      WHEN base.clean_id REGEXP 'RET[A-Z]{2}[0-9]{3,}'
        THEN UPPER(REGEXP_SUBSTR(base.clean_id, 'RET[A-Z]{2}[0-9]{3,}'))
      ELSE NULL
    END AS normalized_site_id
  FROM (
    SELECT
      CAST(store_id AS CHAR(255)) AS raw_id,
      REGEXP_REPLACE(CAST(store_id AS CHAR(255)), '[[:space:]]+', '') AS clean_id,
      CAST(store_name AS CHAR(255)) AS original_site_name,
      REGEXP_REPLACE(
        TRIM(
          REGEXP_REPLACE(
            REGEXP_REPLACE(
              REGEXP_REPLACE(
                REGEXP_REPLACE(COALESCE(store_name,''), '[Ii][Kk][Ee][Aa][Ss][Tt][Oo][Rr][Ee]', ''),
                '[Ii][Kk][Ee][Aa]', ''
              ),
              '[Ss][Tt][Oo][Rr][Ee]', ''
            ),
            '[[:space:]]+',
            ' '
          )
        ),
        '^[-[:space:]]+',
        ''
      ) AS site_label,
      CAST(country AS CHAR(255)) AS country
    FROM nifi.ikea_issue_risk_log
    WHERE store_id IS NOT NULL AND store_id <> ''
  ) base
) v;
