<?php
// src/Repository/ExtEavServicesRepository.php
namespace App\Repository;

use Doctrine\DBAL\Connection;

final class ExtEavServicesRepository
{
    public function __construct(private Connection $db) {}

    public function listServices(string $tenantCode = 'cmdb'): array
    {
        $sql = <<<SQL
        SELECT e.ci AS service_ci, e.name
        FROM ext_eav.entities e
        JOIN ext_eav.tenants t ON t.id = e.tenant_id
        WHERE t.code = :tenant
          AND e.entity_type_id = (
              SELECT et.id FROM ext_eav.entity_types et
              WHERE et.tenant_id = t.id AND et.code = 'service' LIMIT 1
          )
        ORDER BY e.ci
        SQL;
        return $this->db->fetchAllAssociative($sql, ['tenant' => $tenantCode]);
    }

    /** Load base attributes of a service (no calculations, just values) */
    public function getServiceDetails(string $tenantCode, string $serviceCi): ?array
    {
        $sql = <<<SQL
        SELECT e.ci AS service_ci, e.name,
               base_ccy.value   AS base_ccy,
               base_amt.value   AS base_amt,
               unit.value       AS unit,
               vat_cat.value    AS vat_category,
               level_rule.target_ci   AS level_rule,
               country_rule.target_ci AS country_rule,
               term_rule.target_ci    AS term_rule,
               usage_rule.target_ci   AS usage_rule
        FROM ext_eav.entities e
        JOIN ext_eav.tenants t ON t.id = e.tenant_id
        LEFT JOIN ext_eav.eav_values_string base_ccy
          ON base_ccy.entity_ci=e.ci AND base_ccy.tenant_id=t.id
         AND base_ccy.attribute_id=(SELECT id FROM ext_eav.attributes WHERE tenant_id=t.id AND code='pricing.base_currency')
        LEFT JOIN ext_eav.eav_values_decimal base_amt
          ON base_amt.entity_ci=e.ci AND base_amt.tenant_id=t.id
         AND base_amt.attribute_id=(SELECT id FROM ext_eav.attributes WHERE tenant_id=t.id AND code='pricing.base_amount')
        LEFT JOIN ext_eav.eav_values_string unit
          ON unit.entity_ci=e.ci AND unit.tenant_id=t.id
         AND unit.attribute_id=(SELECT id FROM ext_eav.attributes WHERE tenant_id=t.id AND code='pricing.unit')
        LEFT JOIN ext_eav.eav_values_string vat_cat
          ON vat_cat.entity_ci=e.ci AND vat_cat.tenant_id=t.id
         AND vat_cat.attribute_id=(SELECT id FROM ext_eav.attributes WHERE tenant_id=t.id AND code='pricing.vat_category')
        LEFT JOIN ext_eav.eav_values_reference level_rule
          ON level_rule.entity_ci=e.ci AND level_rule.tenant_id=t.id
         AND level_rule.attribute_id=(SELECT id FROM ext_eav.attributes WHERE tenant_id=t.id AND code='pricing.level_rule')
        LEFT JOIN ext_eav.eav_values_reference country_rule
          ON country_rule.entity_ci=e.ci AND country_rule.tenant_id=t.id
         AND country_rule.attribute_id=(SELECT id FROM ext_eav.attributes WHERE tenant_id=t.id AND code='pricing.country_rule')
        LEFT JOIN ext_eav.eav_values_reference term_rule
          ON term_rule.entity_ci=e.ci AND term_rule.tenant_id=t.id
         AND term_rule.attribute_id=(SELECT id FROM ext_eav.attributes WHERE tenant_id=t.id AND code='pricing.term_rule')
        LEFT JOIN ext_eav.eav_values_reference usage_rule
          ON usage_rule.entity_ci=e.ci AND usage_rule.tenant_id=t.id
         AND usage_rule.attribute_id=(SELECT id FROM ext_eav.attributes WHERE tenant_id=t.id AND code='pricing.usage_rule')
        WHERE t.code=:tenant AND e.ci=:ci
        SQL;

        return $this->db->fetchAssociative($sql, ['tenant' => $tenantCode, 'ci' => $serviceCi]);
    }
	
	/** Fetch rule kind + JSON payload for a given rule CI */
    public function getRuleDetails(string $tenantCode, string $ruleCi): ?array
    {
        $sql = <<<SQL
        SELECT kind.value AS kind, payload.value AS payload
        FROM ext_eav.entities e
        JOIN ext_eav.tenants t ON t.id = e.tenant_id
        LEFT JOIN ext_eav.eav_values_string kind
          ON kind.entity_ci=e.ci AND kind.tenant_id=t.id
         AND kind.attribute_id=(SELECT id FROM ext_eav.attributes WHERE tenant_id=t.id AND code='rule.kind')
        LEFT JOIN ext_eav.eav_values_json payload
          ON payload.entity_ci=e.ci AND payload.tenant_id=t.id
         AND payload.attribute_id=(SELECT id FROM ext_eav.attributes WHERE tenant_id=t.id AND code='rule.payload')
        WHERE t.code=:tenant AND e.ci=:ci
        SQL;

        $row = $this->db->fetchAssociative($sql, ['tenant' => $tenantCode, 'ci' => $ruleCi]);
        if (!$row) {
            return null;
        }
        return [
            'rule_ci' => $ruleCi,
            'kind'    => $row['kind'],
            'payload' => json_decode((string)$row['payload'], true),
        ];
    }
	
	
	// src/Repository/ExtEavServicesRepository.php
	public function listCountries(string $tenantCode = 'loc'): array
	{
		$sql = <<<SQL
		SELECT e.ci AS ci,
			   iso2.value     AS iso2,
			   name.value     AS name,
			   ccy.value      AS currency,
			   tz.value       AS timezone,
			   JSON_EXTRACT(lang.value, '$') AS languages_json,
			   eu.value       AS eu_member
		FROM ext_eav.entities e
		JOIN ext_eav.tenants t ON t.id = e.tenant_id
		LEFT JOIN ext_eav.eav_values_string iso2
		  ON iso2.entity_ci=e.ci AND iso2.tenant_id=t.id
		 AND iso2.attribute_id=(SELECT id FROM ext_eav.attributes WHERE tenant_id=t.id AND code='country.iso2')
		LEFT JOIN ext_eav.eav_values_string name
		  ON name.entity_ci=e.ci AND name.tenant_id=t.id
		 AND name.attribute_id=(SELECT id FROM ext_eav.attributes WHERE tenant_id=t.id AND code='country.name')
		LEFT JOIN ext_eav.eav_values_string ccy
		  ON ccy.entity_ci=e.ci AND ccy.tenant_id=t.id
		 AND ccy.attribute_id=(SELECT id FROM ext_eav.attributes WHERE tenant_id=t.id AND code='country.currency')
		LEFT JOIN ext_eav.eav_values_string tz
		  ON tz.entity_ci=e.ci AND tz.tenant_id=t.id
		 AND tz.attribute_id=(SELECT id FROM ext_eav.attributes WHERE tenant_id=t.id AND code='country.timezone')
		LEFT JOIN ext_eav.eav_values_json lang
		  ON lang.entity_ci=e.ci AND lang.tenant_id=t.id
		 AND lang.attribute_id=(SELECT id FROM ext_eav.attributes WHERE tenant_id=t.id AND code='country.languages')
		LEFT JOIN ext_eav.eav_values_boolean eu
		  ON eu.entity_ci=e.ci AND eu.tenant_id=t.id
		 AND eu.attribute_id=(SELECT id FROM ext_eav.attributes WHERE tenant_id=t.id AND code='country.eu_member')
		WHERE t.code=:tenant
		  AND e.entity_type_id = (
			SELECT et.id FROM ext_eav.entity_types et
			WHERE et.tenant_id=t.id AND et.code='country' LIMIT 1
		  )
		ORDER BY iso2.value
		SQL;

		$rows = $this->db->fetchAllAssociative($sql, ['tenant'=>$tenantCode]);
		return array_map(static function(array $r) {
			return [
				'iso2'      => $r['iso2'],
				'name'      => $r['name'],
				'currency'  => $r['currency'],
				'timezone'  => $r['timezone'],
				'languages' => $r['languages_json'] ? json_decode($r['languages_json'], true) : [],
				'eu_member' => (bool)$r['eu_member'],
			];
		}, $rows);
	}
	
}
