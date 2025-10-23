<?php
// src/Frw/Service/RunService.php
namespace App\Frw\Service;

use Doctrine\DBAL\Connection;

final class RunService
{
    public function __construct(private Connection $db, private TemplateService $tpls) {}

    public function createRun(string $templateCode, $user): array
    {
        $tpl = $this->tpls->findByCode($templateCode);
        $this->db->insert('frw_run', [
            'template_id' => $tpl['id'],
            'tenant_id' => null,
            'user_id' => method_exists($user,'getId') ? $user->getId() : null,
            'answers_json' => json_encode([]),
            'status' => 'draft',
        ]);
        $id = (int)$this->db->lastInsertId();
        return $this->get($id);
    }

    public function patchAnswers(int $id, array $answers): array
    {
        $this->db->update('frw_run', ['answers_json' => json_encode($answers)], ['id' => $id]);
        return $this->get($id);
    }

    public function submit(int $id, $user): array
    {
        $this->db->update('frw_run', ['status' => 'submitted', 'submitted_at' => date('Y-m-d H:i:s')], ['id' => $id]);
        // emit event (optional)
        return $this->get($id);
    }

    public function get(int $id): array
    {
        $row = $this->db->fetchAssociative('SELECT * FROM frw_run WHERE id=?', [$id]);
        if (!$row) throw new \RuntimeException('Run not found');
        $row['answers'] = json_decode($row['answers_json'], true) ?? [];
        $row['pricing_breakdown'] = $row['pricing_breakdown_json'] ? json_decode($row['pricing_breakdown_json'], true) : null;
        return $row;
    }
}
