<?php
namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class CanvasLayoutStore
{
    private string $s;
    public function __construct(
      private Connection $db,
      #[Autowire(env: 'CMDB_SCHEMA')] ?string $schema = null
    ) { $this->s = $schema ? trim($schema,'` ') : 'ext_eav'; }

    private function t(string $table): string { return sprintf('`%s`.%s', $this->s, $table); }

    public function load(int $tenantId, string $userRef, string $name='default'): array
    {
        $row = $this->db->fetchAssociative("SELECT nodes FROM ".$this->t('canvas_layouts')." WHERE tenant_id=? AND user_ref=? AND name=?", [$tenantId, $userRef, $name]);
        return $row ? json_decode($row['nodes'], true) : [];
    }

    public function save(int $tenantId, string $userRef, string $name, array $nodes): void
    {
        $payload = json_encode($nodes, JSON_UNESCAPED_UNICODE);
        $this->db->executeStatement(
          "INSERT INTO ".$this->t('canvas_layouts')." (tenant_id, user_ref, name, nodes) VALUES (?,?,?,?)
           ON DUPLICATE KEY UPDATE nodes=VALUES(nodes)",
          [$tenantId, $userRef, $name, $payload]
        );
    }
}
