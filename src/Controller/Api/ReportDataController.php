<?php
// src/Controller/Api/ReportDataController.php
namespace App\Controller\Api;

use App\Service\SearchBuilderSql;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Minimal DataTables endpoint to show how to use SearchBuilderSql.
 * Adjust $baseSql, $columnMap, and $columnTypes to your dataset.
 */
#[Route('/api/report_data')]
final class ReportDataController extends AbstractController
{
    public function __construct(
        private Connection $conn,
        private SearchBuilderSql $sb
    ) {}

    #[Route('', name: 'api_report_data', methods: ['POST'])]
    public function __invoke(Request $req): JsonResponse
    {
        // DataTables can send JSON or form-data; support both
        $payload = $this->decodeRequest($req);

        // --- Paging & ordering (very basic example; adapt to your needs) ---
        $start  = max(0, (int)($payload['start'] ?? 0));
        $length = (int)($payload['length'] ?? 25);
        if ($length <= 0 || $length > 5000) $length = 25;

        // --- Base dataset (example). Replace with your view or query.
        // IMPORTANT: The SELECT aliases must match the keys you expose to DataTables "data"
        $baseSql = <<<SQL
            SELECT
              o.id            AS id,
              o.created_at    AS created_at,
              o.encaisse_at   AS encaisse_at,
              o.total_cents   AS total_cents,
              o.customer_id   AS customer_id
            FROM ongleri.orders o
        SQL;

        // Whitelist: UI 'data' key => SQL expression
        $columnMap = [
            'id'           => 'o.id',
            'created_at'   => 'o.created_at',
            'encaisse_at'  => 'o.encaisse_at',
            'total_cents'  => 'o.total_cents',
            'customer_id'  => 'o.customer_id',
        ];

        // Types for date handling
        $columnTypes = [
            'created_at'  => 'datetime',
            'encaisse_at' => 'datetime',
            // 'some_date' => 'date', // example of DATE-only column
        ];

        // --- Build filtered query
        $qb = $this->conn->createQueryBuilder();
        $qb->select('*')->from("({$baseSql})", 't'); // wrap base SQL as subquery

        // Apply SearchBuilder filters
        $sb = $payload['searchBuilder'] ?? [];
        $this->sb->normalize($sb);
        $this->sb->apply($qb, $sb, $columnMap, $columnTypes, 'sb');

        // Apply ordering (basic — from DataTables 'order[0][column]' indexes)
        $order = $payload['order'][0] ?? null;
        if ($order && isset($payload['columns'][$order['column']]['data'])) {
            $dataKey = $payload['columns'][$order['column']]['data'];
            if (isset($columnMap[$dataKey])) {
                $dir = strtoupper($order['dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
                $qb->add('orderBy', $columnMap[$dataKey] . ' ' . $dir);
            }
        }

        // Count total / filtered
        $countTotal = (int)$this->conn->fetchOne("SELECT COUNT(*) FROM ({$baseSql}) t");
        $countQb = clone $qb;
        $countFiltered = (int)$this->conn->fetchOne("SELECT COUNT(*) FROM ({$countQb->getSQL()}) x", $countQb->getParameters(), $countQb->getParameterTypes());

        // Paging
        $qb->setFirstResult($start)->setMaxResults($length);

        // Fetch data
        $rows = $this->conn->fetchAllAssociative($qb->getSQL(), $qb->getParameters(), $qb->getParameterTypes());

        // DataTables response
        return new JsonResponse([
            'draw'            => (int)($payload['draw'] ?? 0),
            'recordsTotal'    => $countTotal,
            'recordsFiltered' => $countFiltered,
            'data'            => $rows,
        ]);
    }

    private function decodeRequest(Request $req): array
    {
        $json = json_decode($req->getContent() ?: 'null', true);
        if (is_array($json)) return $json;

        // Fallback to form data (DataTables default)
        $payload = $req->request->all();
        // DataTables nests things like columns[0][data], normalize a bit:
        if (empty($payload['columns']) && !empty($payload['columns'][0]['data'])) {
            // Already normalized by PHP; do nothing
        }
        return $payload;
    }
}
