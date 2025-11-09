<?php
namespace App\Controller;

use App\Service\Discovery\DiscoveryQuestionnaireRuntimeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request};
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/qw')]
class QwController extends AbstractController
{
    // --- Lightweight PDO bootstrap (reads DATABASE_URL or QW_* envs)
    private function pdo(): \PDO
    {
        $dsn = $_ENV['QW_DSN'] ?? ($_ENV['DATABASE_URL'] ?? '');
        $user = $_ENV['QW_DB_USER'] ?? ($_ENV['DB_USER'] ?? null);
        $pass = $_ENV['QW_DB_PASS'] ?? ($_ENV['DB_PASSWORD'] ?? null);

        if (str_starts_with((string)$dsn, 'mysql://')) {
            $u = parse_url($dsn);
            $db = ltrim($u['path'] ?? '', '/');
            $host = $u['host'] ?? '127.0.0.1';
            $port = $u['port'] ?? 3306;
            $user = $u['user'] ?? $user;
            $pass = $u['pass'] ?? $pass;
            $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
        }
        $pdo = new \PDO($dsn, $user, $pass, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    }

    public function __construct(
        private readonly \App\Service\QwOutlineNumberingService $numbering,
        private readonly DiscoveryQuestionnaireRuntimeService $runtime,
    ) {}

    #[Route('/questionnaires', methods: ['GET'])]
    public function listQuestionnaires(Request $req): JsonResponse
    {
        $tenantId = $req->query->getInt('tenant_id', 1);
        $ciId = $req->query->has('ci_id') ? $req->query->getInt('ci_id') : null;
        $search = trim((string) $req->query->get('q', ''));

        $pdo = $this->pdo();
        $sql = 'SELECT q.id, q.title, q.status, q.ci_id, q.version, q.updated_at, c.ci_name, c.ci_key
                  FROM qw_questionnaire q
             LEFT JOIN qw_ci c ON c.id = q.ci_id
                 WHERE q.tenant_id = :tenant_id';
        $bind = [':tenant_id' => $tenantId];

        if ($ciId) {
            $sql .= ' AND q.ci_id = :ci_id';
            $bind[':ci_id'] = $ciId;
        }

        if ($search !== '') {
            $sql .= ' AND (q.title LIKE :needle OR q.code LIKE :needle)';
            $bind[':needle'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY q.updated_at DESC, q.id DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($bind);
        $rows = $stmt->fetchAll();

        $normalized = array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'title' => $row['title'],
                'status' => $row['status'],
                'ciId' => $row['ci_id'] !== null ? (int) $row['ci_id'] : null,
                'ciName' => $row['ci_name'] ?? null,
                'ciKey' => $row['ci_key'] ?? null,
                'version' => isset($row['version']) ? (int) $row['version'] : null,
                'updatedAt' => $row['updated_at'],
            ];
        }, $rows);

        return $this->json($normalized);
    }

    #[Route('/questionnaires', methods: ['POST'])]
    public function createQuestionnaire(Request $req): JsonResponse
    {
        $p = json_decode($req->getContent(), true) ?? [];
        $pdo = $this->pdo();
        $stmt = $pdo->prepare("INSERT INTO qw_questionnaire (tenant_id, ci_id, code, title, description, status, version, is_locked, created_at, updated_at)
                               VALUES (:tenant_id, :ci_id, :code, :title, :description, 'draft', 1, 0, NOW(), NOW())");
        $stmt->execute([
            ':tenant_id' => (int)($p['tenant_id'] ?? 1),
            ':ci_id'     => (int)($p['ci_id'] ?? 1),
            ':code'      => $p['code'] ?? ('qw_'.time()),
            ':title'     => $p['title'] ?? 'Untitled',
            ':description' => $p['description'] ?? null,
        ]);
        return $this->json(['id' => (int)$pdo->lastInsertId()]);
    }

    #[Route('/questionnaires/{id}/responses', methods: ['GET'])]
    public function listQuestionnaireResponses(int $id): JsonResponse
    {
        $pdo = $this->pdo();
        $stmt = $pdo->prepare('SELECT id, status, started_at, submitted_at, approved_at, rejected_at FROM qw_response WHERE questionnaire_id = :id ORDER BY id DESC');
        $stmt->execute([':id' => $id]);
        $rows = $stmt->fetchAll();
        $normalized = array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'status' => $row['status'],
                'startedAt' => $row['started_at'],
                'submittedAt' => $row['submitted_at'],
                'approvedAt' => $row['approved_at'],
                'rejectedAt' => $row['rejected_at'],
            ];
        }, $rows);

        return $this->json($normalized);
    }

    #[Route('/questionnaires/{id}/responses', methods: ['POST'])]
    public function createQuestionnaireResponse(int $id, Request $req): JsonResponse
    {
        $payload = json_decode($req->getContent() ?: '[]', true) ?? [];
        $cloneFrom = isset($payload['cloneFrom']) ? (int) $payload['cloneFrom'] : null;

        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            if ($cloneFrom) {
                $belongs = $pdo->prepare('SELECT questionnaire_id FROM qw_response WHERE id = :rid');
                $belongs->execute([':rid' => $cloneFrom]);
                $sourceQid = $belongs->fetchColumn();
                if (!$sourceQid) {
                    $pdo->rollBack();
                    return $this->json(['error' => 'source_not_found'], 404);
                }
                if ((int) $sourceQid !== $id) {
                    $pdo->rollBack();
                    return $this->json(['error' => 'source_mismatch'], 400);
                }
            }

            $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
            $stmt = $pdo->prepare('INSERT INTO qw_response (questionnaire_id, status, started_at, submitted_by_user_id, submitted_at, approved_at, rejected_at) VALUES (:qid, :status, :started_at, NULL, NULL, NULL, NULL)');
            $stmt->execute([
                ':qid' => $id,
                ':status' => 'in_progress',
                ':started_at' => $now,
            ]);

            $newId = (int) $pdo->lastInsertId();

            if ($cloneFrom) {
                $answers = $pdo->prepare('INSERT INTO qw_answer (response_id, item_id, field_id, value_text, value_json, created_at, updated_at)
                                          SELECT :target, item_id, field_id, value_text, value_json, NOW(), NOW()
                                            FROM qw_answer WHERE response_id = :source');
                $answers->execute([
                    ':target' => $newId,
                    ':source' => $cloneFrom,
                ]);

                $attachments = $pdo->prepare('INSERT INTO qw_attachment (response_id, item_id, field_id, storage_path, original_name, mime_type, size_bytes, created_at)
                                               SELECT :target, item_id, field_id, storage_path, original_name, mime_type, size_bytes, created_at
                                                 FROM qw_attachment WHERE response_id = :source');
                $attachments->execute([
                    ':target' => $newId,
                    ':source' => $cloneFrom,
                ]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return $this->json(['id' => $newId]);
    }

    #[Route('/questionnaires/{id}', methods: ['GET'])]
    public function getQuestionnaire(int $id): JsonResponse
    {
        $pdo = $this->pdo();
        $qh = $pdo->prepare('SELECT q.id, q.title, q.status, q.tenant_id AS tenant_id, q.ci_id AS ci_id, c.ci_name AS ci_name, c.ci_key AS ci_key
                               FROM qw_questionnaire q
                          LEFT JOIN qw_ci c ON c.id = q.ci_id
                              WHERE q.id = :id');
        $qh->execute([':id' => $id]);
        $q = $qh->fetch();
        if (!$q) return $this->json(['error' => 'not_found'], 404);

        $items = $pdo->prepare("SELECT id, parent_id AS parentId, type, title, help, sort, outline, required, visible_when AS visibleWhen
                                 FROM qw_item WHERE questionnaire_id=:id ORDER BY parent_id, sort, id");
        $items->execute([':id' => $id]);
        $rows = $items->fetchAll();
        foreach ($rows as &$r) {
            $r['required'] = (bool)$r['required'];
            $r['visibleWhen'] = $r['visibleWhen'] ? json_decode($r['visibleWhen'], true) : null;
        }
        return $this->json([
            'id' => (int)$q['id'],
            'title' => $q['title'],
            'status' => $q['status'],
            'tenantId' => isset($q['tenant_id']) ? (int)$q['tenant_id'] : null,
            'ciId' => isset($q['ci_id']) ? (int)$q['ci_id'] : null,
            'ciName' => $q['ci_name'] ?? null,
            'ciKey' => $q['ci_key'] ?? null,
            'items' => $rows,
        ]);
    }

    #[Route('/questionnaires/{id}', methods: ['PATCH'])]
    public function patchQuestionnaire(int $id, Request $req): JsonResponse
    {
        $payload = json_decode($req->getContent(), true) ?? [];
        if (!is_array($payload) || $payload === []) {
            return $this->json(['error' => 'empty_payload'], 400);
        }

        $pdo = $this->pdo();
        $sets = [];
        $bind = [':id' => $id];

        $map = [
            'title' => 'title',
            'description' => 'description',
            'status' => 'status',
            'ci_id' => 'ci_id',
        ];

        foreach ($map as $jsonKey => $column) {
            if (!array_key_exists($jsonKey, $payload)) {
                continue;
            }
            $placeholder = ':' . $column;
            if ($column === 'ci_id') {
                $value = $payload[$jsonKey];
                $bind[$placeholder] = $value === null || $value === '' ? null : (int)$value;
            } else {
                $bind[$placeholder] = $payload[$jsonKey];
            }
            $sets[] = sprintf('%s = %s', $column, $placeholder);
        }

        if (!$sets) {
            return $this->json(['error' => 'no_fields'], 400);
        }

        $sql = 'UPDATE qw_questionnaire SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = :id';
        $pdo->prepare($sql)->execute($bind);

        return $this->getQuestionnaire($id);
    }

    #[Route('/questionnaires/{id}/items', methods: ['POST'])]
    public function addItem(int $id, Request $req): JsonResponse
    {
        $pdo = $this->pdo();
        $p = json_decode($req->getContent(), true) ?? [];
        $stmt = $pdo->prepare("INSERT INTO qw_item (questionnaire_id, parent_id, type, title, help, sort, required, created_at, updated_at)
                               VALUES (:qid, :parent, :type, :title, :help, :sort, :required, NOW(), NOW())");
        $stmt->execute([
            ':qid' => $id,
            ':parent' => $p['parentId'] ?? null,
            ':type' => $p['type'] ?? 'header',
            ':title' => $p['title'] ?? 'New',
            ':help' => $p['help'] ?? null,
            ':sort' => (int)($p['sort'] ?? 0),
            ':required' => !empty($p['required']) ? 1 : 0,
        ]);
        $newId = (int)$pdo->lastInsertId();
        $this->numbering->rebuild($id);
        return $this->json(['id' => $newId]);
    }

    #[Route('/items/{id}', methods: ['PATCH'])]
    public function updateItem(int $id, Request $req): JsonResponse
    {
        $pdo = $this->pdo();
        $p = json_decode($req->getContent(), true) ?? [];
        $sets = []; $bind = [':id' => $id];
        foreach (['title','help'] as $f) if (array_key_exists($f,$p)) { $sets[] = "$f = :$f"; $bind[":$f"] = $p[$f]; }
        if (array_key_exists('parentId',$p)) { $sets[] = 'parent_id = :parent_id'; $bind[':parent_id']=$p['parentId']; }
        if (array_key_exists('sort',$p)) { $sets[] = 'sort = :sort'; $bind[':sort']=(int)$p['sort']; }
        if (array_key_exists('visibleWhen',$p)) { $sets[]='visible_when = :visible_when'; $bind[':visible_when'] = $p['visibleWhen']? json_encode($p['visibleWhen']) : null; }
        if (array_key_exists('required',$p)) { $sets[] = 'required = :required'; $bind[':required'] = $p['required']?1:0; }
        if ($sets) {
            $sql = 'UPDATE qw_item SET '.implode(', ',$sets).', updated_at=NOW() WHERE id=:id';
            $pdo->prepare($sql)->execute($bind);
            $qid = $pdo->query('SELECT questionnaire_id FROM qw_item WHERE id='.(int)$id)->fetchColumn();
            if ($qid) $this->numbering->rebuild((int)$qid);
        }
        return $this->json(['ok'=>true]);
    }

    // ---------- Functional Fields (qw_field) ----------

    #[Route('/items/{id}/fields', methods: ['POST'])]
    public function addField(int $id, Request $req): JsonResponse
    {
        $pdo = $this->pdo();
        $p = json_decode($req->getContent(), true) ?? [];

        $stmt = $pdo->prepare("INSERT INTO qw_field (item_id, ui_type, placeholder, default_value, min_value, max_value, step_value, validation_regex, options_json, options_sql, chain_sql, accept_mime, max_size_mb, unique_key)
                               VALUES (:item_id,:ui_type,:placeholder,:default_value,:min_value,:max_value,:step_value,:validation_regex,:options_json,:options_sql,:chain_sql,:accept_mime,:max_size_mb,:unique_key)");
        $stmt->execute([
            ':item_id'=>$id,
            ':ui_type'=>$p['ui_type'] ?? 'input',
            ':placeholder'=>$p['placeholder'] ?? null,
            ':default_value'=>$p['default_value'] ?? null,
            ':min_value'=>$p['min_value'] ?? null,
            ':max_value'=>$p['max_value'] ?? null,
            ':step_value'=>$p['step_value'] ?? null,
            ':validation_regex'=>$p['validation_regex'] ?? null,
            ':options_json'=> isset($p['options_json']) ? json_encode($p['options_json']) : null,
            ':options_sql'=>$p['options_sql'] ?? null, // TIP: whitelist/prepare server-side in production
            ':chain_sql'=> isset($p['chain_sql']) ? json_encode($p['chain_sql']) : null,
            ':accept_mime'=>$p['accept_mime'] ?? null,
            ':max_size_mb'=>$p['max_size_mb'] ?? null,
            ':unique_key'=>$p['unique_key'] ?? null,
        ]);
        return $this->json(['id'=>(int)$pdo->lastInsertId()]);
    }

    #[Route('/items/{id}/fields', methods: ['GET'])]
    public function listFields(int $id): JsonResponse
    {
        $pdo = $this->pdo();
        $stmt = $pdo->prepare("
            SELECT id, ui_type, placeholder, default_value, min_value, max_value, step_value, validation_regex,
                   options_json, options_sql, chain_sql, accept_mime, max_size_mb, unique_key
            FROM qw_field WHERE item_id = :id ORDER BY id
        ");
        $stmt->execute([':id' => $id]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['options_json'] = $r['options_json'] ? json_decode($r['options_json'], true) : null;
            $r['chain_sql']    = $r['chain_sql']    ? json_decode($r['chain_sql'], true)    : null;
        }
        return $this->json($rows);
    }

    #[Route('/fields/{fieldId}', methods: ['DELETE'])]
    public function deleteField(int $fieldId): JsonResponse
    {
        $pdo = $this->pdo();
        $stmt = $pdo->prepare('DELETE FROM qw_field WHERE id = :id');
        $stmt->execute([':id' => $fieldId]);
        return $this->json(['ok' => true]);
    }

    // --------------------------------------------------

    #[Route('/rebuild-outline/{qid}', methods: ['POST'])]
    public function rebuild(int $qid): JsonResponse
    {
        $this->numbering->rebuild($qid);
        return $this->json(['ok'=>true]);
    }

    #[Route('/cis', methods: ['GET'])]
    public function listCis(Request $req): JsonResponse
    {
        $tenantId = $req->query->getInt('tenant_id', 1);
        $search = trim((string)$req->query->get('q', ''));

        $pdo = $this->pdo();
        $sql = 'SELECT id, tenant_id AS tenantId, ci_key AS ciKey, ci_name AS ciName FROM qw_ci WHERE tenant_id = :tenant_id';
        $bind = [':tenant_id' => $tenantId];

        if ($search !== '') {
            $sql .= ' AND (ci_key LIKE :needle OR ci_name LIKE :needle)';
            $bind[':needle'] = '%'.$search.'%';
        }

        $sql .= ' ORDER BY ci_name ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($bind);
        $rows = $stmt->fetchAll();
        $normalized = array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'tenantId' => isset($row['tenantId']) ? (int) $row['tenantId'] : null,
                'ciKey' => $row['ciKey'] ?? null,
                'ciName' => $row['ciName'] ?? null,
            ];
        }, $rows);

        return $this->json($normalized);
    }

    #[Route('/responses/{id}', methods: ['GET'])]
    public function getResponse(int $id): JsonResponse
    {
        $payload = $this->runtime->loadResponse($id);
        return $this->json($payload);
    }

    #[Route('/responses/{id}', methods: ['POST'])]
    public function saveResponse(int $id, Request $req): JsonResponse
    {
        $payload = json_decode($req->getContent() ?: '[]', true) ?? [];
        $answers = $payload['answers'] ?? [];
        if (!is_array($answers)) {
            return $this->json(['error' => 'answers_must_be_array'], 400);
        }
        $status = (string) ($payload['status'] ?? 'in_progress');
        $data = $this->runtime->saveResponse($id, $answers, $status);
        return $this->json($data);
    }



}
