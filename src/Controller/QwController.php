<?php
namespace App\Controller;

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

    public function __construct(private readonly \App\Service\QwOutlineNumberingService $numbering) {}

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

    #[Route('/questionnaires/{id}', methods: ['GET'])]
    public function getQuestionnaire(int $id): JsonResponse
    {
        $pdo = $this->pdo();
        $qh = $pdo->prepare('SELECT id, title, status FROM qw_questionnaire WHERE id=:id');
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
            'items' => $rows,
        ]);
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



}
