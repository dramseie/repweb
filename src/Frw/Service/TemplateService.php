<?php
// src/Frw/Service/TemplateService.php
namespace App\Frw\Service;

use Doctrine\DBAL\Connection;

final class TemplateService
{
    public function __construct(private Connection $db) {}

    public function listActive(): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT code, name, version FROM frw_template WHERE is_active=1 ORDER BY name'
        );
    }

    public function findByCode(string $code): array
    {
        $row = $this->db->fetchAssociative('SELECT * FROM frw_template WHERE code=?', [$code]);
        if (!$row) throw new \RuntimeException('Template not found');

        $schema = json_decode($row['schema_json'] ?? '', true);

        // Double-decode if schema_json is itself a JSON string
        if (is_string($schema)) {
            $s2 = json_decode($schema, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($s2)) {
                $schema = $s2;
            }
        }

        // Normalize common shapes to a flat object with steps
        if (is_array($schema)) {
            // Case A: { "schema": { "steps": [...] } }
            if (!isset($schema['steps']) && isset($schema['schema']) && is_array($schema['schema'])) {
                $schema = $schema['schema'];
            }
            // Case B: { "data": { "steps": [...] } }
            if (!isset($schema['steps']) && isset($schema['data']) && is_array($schema['data'])) {
                $schema = $schema['data'];
            }
            // Case C: "Steps" capitalized
            if (!isset($schema['steps']) && isset($schema['Steps']) && is_array($schema['Steps'])) {
                $schema['steps'] = $schema['Steps'];
            }
            // Case D: wrapped in a one-element array: [ { steps: [...] } ]
            if (!isset($schema['steps']) && isset($schema[0]['steps']) && is_array($schema[0]['steps'])) {
                $schema = $schema[0];
            }
        } else {
            $schema = [];
        }

        $row['schema'] = $schema;
        return $row;
    }
}
