<?php
namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/pos', name: 'api_pos_')]
class PosApiController extends AbstractController
{
    public function __construct(private Connection $conn) {}

    #[Route('/items', name: 'items', methods: ['GET'])]
    public function items(): JsonResponse
    {
        $sql = <<<SQL
SELECT i.id, i.name, i.code, i.type, i.price_cents, i.tax_rate, i.color_hex,
       c.id AS category_id, c.name AS category_name, c.sort_order
FROM ongleri.items i
LEFT JOIN ongleri.categories c ON c.id = i.category_id
WHERE i.is_active = 1
ORDER BY c.sort_order, i.name
SQL;
        $rows = $this->conn->fetchAllAssociative($sql);

        $grouped = [];
        foreach ($rows as $r) {
            $cat = $r['category_name'] ?? 'Divers';
            $grouped[$cat][] = [
                'id' => (int)$r['id'],
                'name' => $r['name'],
                'code' => $r['code'],
                'type' => $r['type'],
                'price_cents' => (int)$r['price_cents'],
                'tax_rate' => (float)$r['tax_rate'],
                'color_hex' => $r['color_hex'],
            ];
        }
        return $this->json(['categories' => $grouped]);
    }

    #[Route('/orders', name: 'orders_create', methods: ['POST'])]
    public function createOrder(Request $req): JsonResponse
    {
        $payload = json_decode($req->getContent(), true) ?? [];
        $items = $payload['items'] ?? [];
        if (!$items) return $this->json(['error' => 'Empty cart'], 400);

        $ids = array_map(fn($i) => (int)$i['item_id'], $items);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $rows = $this->conn->fetchAllAssociative(
            "SELECT id, name, price_cents, tax_rate FROM ongleri.items WHERE id IN ($placeholders)",
            $ids
        );
        $byId = [];
        foreach ($rows as $r) $byId[(int)$r['id']] = $r;

        $total = 0; $totalTax = 0; $lines = [];
        foreach ($items as $i) {
            $iid = (int)$i['item_id'];
            $qty = max(1, (int)($i['qty'] ?? 1));
            if (!isset($byId[$iid])) continue;

            $unit = (int)$byId[$iid]['price_cents'];
            $rate = (float)$byId[$iid]['tax_rate'];
            $lineTotal = $unit * $qty;
            $taxAmount = (int) round($lineTotal * ($rate / 100.0));

            $total += $lineTotal;
            $totalTax += $taxAmount;
            $lines[] = [
                'item_id' => $iid,
                'name' => $byId[$iid]['name'],
                'unit_price_cents' => $unit,
                'tax_rate' => $rate,
                'qty' => $qty,
                'line_total_cents' => $lineTotal,
            ];
        }
        if (!$lines) return $this->json(['error' => 'No valid items'], 400);

        $this->conn->beginTransaction();
        try {
            $this->conn->insert('ongleri.orders', [
                'total_cents' => $total,
                'total_tax_cents' => $totalTax,
                'note' => $payload['note'] ?? null,
            ]);
            $orderId = (int)$this->conn->lastInsertId();

            foreach ($lines as $L) {
                $this->conn->insert('ongleri.order_items', [
                    'order_id' => $orderId,
                    'item_id' => $L['item_id'],
                    'name_snapshot' => $L['name'],
                    'unit_price_cents' => $L['unit_price_cents'],
                    'tax_rate' => $L['tax_rate'],
                    'qty' => $L['qty'],
                    'line_total_cents' => $L['line_total_cents'],
                ]);
            }

            $this->conn->commit();
            return $this->json(['ok' => true, 'order_id' => $orderId, 'total_cents' => $total, 'total_tax_cents' => $totalTax]);
        } catch (\Throwable $e) {
            $this->conn->rollBack();
            return $this->json(['error' => 'DB error', 'detail' => $e->getMessage()], 500);
        }
    }
}
