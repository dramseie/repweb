<?php
namespace App\Service;

class QwOutlineNumberingService
{
    // Same simple PDO bootstrap as controller; duplicate for copy/paste convenience.
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
        return new \PDO($dsn, $user, $pass, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
    }

    /**
     * Rebuild hierarchical outline numbers (e.g. 1, 1.1, 1.2, 2, 2.1, ...)
     * based on current parent_id + sort values for a questionnaire.
     */
    public function rebuild(int $questionnaireId): void
    {
        $pdo = $this->pdo();
        $stmt = $pdo->prepare("
            SELECT id, parent_id, sort
            FROM qw_item
            WHERE questionnaire_id = :qid
            ORDER BY parent_id IS NOT NULL, parent_id, sort, id
        ");
        $stmt->execute([':qid' => $questionnaireId]);
        $rows = $stmt->fetchAll();

        $byParent = [];
        foreach ($rows as $r) {
            $pid = $r['parent_id'] ?? 'root';
            $byParent[$pid][] = $r;
        }

        $updates = [];
        $walk = function($pid, $prefix) use (&$walk, &$byParent, &$updates) {
            $i = 1;
            foreach (($byParent[$pid] ?? []) as $node) {
                $outline = implode('.', array_merge($prefix, [$i]));
                $updates[] = ['id' => (int)$node['id'], 'outline' => $outline];
                $walk($node['id'], array_merge($prefix, [$i]));
                $i++;
            }
        };
        $walk('root', []);

        if ($updates) {
            $upd = $pdo->prepare('UPDATE qw_item SET outline = :outline WHERE id = :id');
            foreach ($updates as $u) {
                $upd->execute($u);
            }
        }
    }
}
