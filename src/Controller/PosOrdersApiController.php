<?php
// src/Controller/PosOrdersApiController.php
namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/pos', name: 'api_pos_')]
class PosOrdersApiController extends AbstractController
{
    public function __construct(private Connection $conn) {}

    // =========================
    // GET /api/pos/orders/{id} — full details (order + items + customer + appointment)
    // =========================
    #[Route('/orders/{id<\d+>}', name: 'orders_read', methods: ['GET'])]
    public function read(int $id): JsonResponse
    {
        // Base order
        $o = $this->conn->fetchAssociative(
            "SELECT o.id,
                    o.created_at,
                    o.total_cents,
                    o.total_tax_cents,
                    o.amount_received_cents,
                    o.tip_cents,
                    o.encaisse_at,
                    o.elapsed_minutes,
                    o.note,
                    o.realizations_json,
                    o.customer_id,
                    o.appointment_id,
                    /* may not exist until you run the DDL, so use IFNULL via select aliasing if needed */
                    /* if columns exist, include them; otherwise SELECT will ignore */
                    o.status,
                    o.revoked_at,
                    o.revoked_reason,
                    o.replacement_appointment_id
               FROM ongleri.orders o
              WHERE o.id = ?",
            [$id]
        );
        if (!$o) {
            return $this->json(['ok' => false, 'error' => 'Order not found'], 404);
        }

        // Items
        $items = $this->conn->fetchAllAssociative(
            "SELECT oi.id,
                    oi.item_id,
                    oi.name_snapshot,
                    oi.unit_price_cents,
                    oi.tax_rate,
                    oi.qty,
                    oi.line_total_cents
               FROM ongleri.order_items oi
              WHERE oi.order_id = ?
              ORDER BY oi.id ASC",
            [$id]
        );

        // Customer (optional)
        $customer = null;
        if (!empty($o['customer_id'])) {
            $customer = $this->conn->fetchAssociative(
                "SELECT id, first_name, last_name, phone, email, notes_public, notes_private
                   FROM ongleri.customers
                  WHERE id = ?",
                [(int)$o['customer_id']]
            );
        }

        // Appointment (optional)
        $appointment = null;
        if (!empty($o['appointment_id'])) {
            $appointment = $this->conn->fetchAssociative(
                "SELECT id, customer_id, start_at, end_at, real_start_at, real_end_at,
                        status, notes_public, notes_private
                   FROM ongleri.appointments
                  WHERE id = ?",
                [(int)$o['appointment_id']]
            );
        }

        return $this->json([
            'ok'          => true,
            'order'       => $o,
            'items'       => $items,
            'customer'    => $customer,
            'appointment' => $appointment,
        ]);
    }

    // =========================
    // CREATE ORDER
    // =========================
    #[Route('/orders', name: 'orders_create', methods: ['POST'])]
    public function create(Request $req): JsonResponse
    {
        $p = json_decode($req->getContent(), true) ?? [];

        $items = (array)($p['items'] ?? []); // [{item_id, qty}]
        if (!$items) {
            return $this->json(['ok' => false, 'error' => 'No items'], 400);
        }

        $customerId    = isset($p['customer_id']) ? (int)$p['customer_id'] : null;
        $appointmentId = isset($p['appointment_id']) ? (int)$p['appointment_id'] : null;
        $note          = trim((string)($p['note'] ?? '')) ?: null;

        // ✅ Réalisations (array of {code,label}) — metadata only
        $realizationsInput = $p['realizations'] ?? [];
        if (!is_array($realizationsInput)) {
            $realizationsInput = [];
        }
        $realizations = [];
        foreach ($realizationsInput as $r) {
            if (!is_array($r)) { continue; }
            $code  = isset($r['code'])  ? trim((string)$r['code'])  : '';
            $label = isset($r['label']) ? trim((string)$r['label']) : '';
            if ($code === '' && $label === '') { continue; }
            $realizations[] = [
                'code'  => $code !== '' ? $code : null,
                'label' => $label !== '' ? $label : null,
            ];
        }
        $realizationsJson = $realizations ? json_encode($realizations, JSON_UNESCAPED_UNICODE) : null;

        // Price lookup
        $ids = array_values(array_unique(array_map(fn($r) => (int)($r['item_id'] ?? 0), $items)));
        $ids = array_filter($ids, fn($v) => $v > 0);
        if (!$ids) return $this->json(['ok' => false, 'error' => 'No valid items'], 400);

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->conn->fetchAllAssociative(
            "SELECT id, name, price_cents, tax_rate
               FROM ongleri.items
              WHERE id IN ($placeholders)",
            $ids
        );
        $byId = [];
        foreach ($rows as $r) $byId[(int)$r['id']] = $r;

        $lines = [];
        $total = 0; $taxTotal = 0;

        foreach ($items as $it) {
            $iid = (int)($it['item_id'] ?? 0);
            $qty = max(1, (int)($it['qty'] ?? 1));
            if (!isset($byId[$iid])) continue;

            $unit = (int)$byId[$iid]['price_cents'];
            $rate = (float)$byId[$iid]['tax_rate'];
            $line = $unit * $qty;
            $tax  = (int)round($line * ($rate / 100));

            $total    += $line;
            $taxTotal += $tax;

            $lines[] = [
                'item_id'          => $iid,
                'name_snapshot'    => $byId[$iid]['name'],
                'unit_price_cents' => $unit,
                'tax_rate'         => $rate,
                'qty'              => $qty,
                'line_total_cents' => $line,
            ];
        }

        if (!$lines) return $this->json(['ok' => false, 'error' => 'No valid items'], 400);

        $this->conn->beginTransaction();
        try {
            // Insert order (✅ with realizations_json)
            $this->conn->insert('ongleri.orders', [
                'total_cents'         => $total,
                'total_tax_cents'     => $taxTotal,
                'note'                => $note,
                'customer_id'         => $customerId ?: null,
                'appointment_id'      => $appointmentId ?: null,
                'realizations_json'   => $realizationsJson,
            ]);
            $orderId = (int)$this->conn->lastInsertId();

            // Insert order lines
            foreach ($lines as $ln) {
                $this->conn->insert('ongleri.order_items', [
                    'order_id'          => $orderId,
                    'item_id'           => $ln['item_id'],
                    'name_snapshot'     => $ln['name_snapshot'],
                    'unit_price_cents'  => $ln['unit_price_cents'],
                    'tax_rate'          => $ln['tax_rate'],
                    'qty'               => $ln['qty'],
                    'line_total_cents'  => $ln['line_total_cents'],
                ]);
            }

            $this->conn->commit();

            return $this->json([
                'ok'               => true,
                'order_id'         => $orderId,
                'total_cents'      => $total,
                'total_tax_cents'  => $taxTotal,
                'customer_id'      => $customerId,
                'appointment_id'   => $appointmentId,
                'realizations'     => $realizations,
            ]);
        } catch (\Throwable $e) {
            $this->conn->rollBack();
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // =========================
    // ENCAISSER
    // =========================
    #[Route('/orders/{id<\d+>}/encaisser', name: 'orders_encaisser', methods: ['POST'])]
    public function encaisser(int $id, Request $req): JsonResponse
    {
        $p = json_decode($req->getContent(), true) ?? [];

        $order = $this->conn->fetchAssociative(
            "SELECT id, total_cents, appointment_id
               FROM ongleri.orders
              WHERE id = ?",
            [$id]
        );
        if (!$order) {
            return $this->json(['error' => 'Order not found'], 404);
        }

        $amountDueCents      = (int)$order['total_cents'];
        $amountReceivedCents = max(0, (int)($p['amountReceivedCents'] ?? 0));
        $tipCents            = max(0, $amountReceivedCents - $amountDueCents);

        $encaisseAtIso = (string)($p['encaisseAtIso'] ?? (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM));
        $encaisseAt    = new \DateTimeImmutable($encaisseAtIso);

        $elapsedMinutes = $p['elapsedMinutes'] ?? null;
        if ($elapsedMinutes === null && !empty($order['appointment_id'])) {
            $startStr = $this->conn->fetchOne(
                "SELECT start_at FROM ongleri.appointments WHERE id = ?",
                [(int)$order['appointment_id']]
            );
            if ($startStr) {
                $start = new \DateTimeImmutable($startStr);
                $elapsedMinutes = max(0, (int) floor(($encaisseAt->getTimestamp() - $start->getTimestamp()) / 60));
            }
        }
        $elapsedMinutes = (int) max(0, (int)($elapsedMinutes ?? 0));

        $this->conn->executeStatement(
            "UPDATE ongleri.orders
                SET amount_received_cents = :ar,
                    tip_cents             = :tip,
                    encaisse_at           = :dt,
                    elapsed_minutes       = :em
              WHERE id = :id",
            [
                'ar' => $amountReceivedCents,
                'tip'=> $tipCents,
                'dt' => $encaisseAt->format('Y-m-d H:i:s'),
                'em' => $elapsedMinutes,
                'id' => $id,
            ]
        );

        $row = $this->conn->fetchAssociative(
            "SELECT id,
                    total_cents,
                    amount_received_cents,
                    tip_cents,
                    encaisse_at,
                    elapsed_minutes,
                    appointment_id,
                    customer_id,
                    realizations_json
               FROM ongleri.orders
              WHERE id = ?",
            [$id]
        );

        return $this->json(['item' => $row]);
    }

    // =========================
    // NEW: Update only note + réalisations (allowed anytime)
    // =========================
    #[Route('/orders/{id<\d+>}/meta', name: 'orders_update_meta', methods: ['PATCH'])]
    public function updateMeta(int $id, Request $req): JsonResponse
    {
        $data = json_decode($req->getContent(), true) ?? [];

        $note = array_key_exists('note', $data) ? (trim((string)$data['note']) ?: null) : null;

        // Accept both spellings: realisations / realizations
        $realisationsInput = $data['realisations'] ?? $data['realizations'] ?? null;
        $realizationsJson = null;

        if ($realisationsInput !== null) {
            if (!is_array($realisationsInput)) {
                $realisationsInput = [];
            }
            $clean = [];
            foreach ($realisationsInput as $r) {
                if (!is_array($r)) continue;
                $code  = isset($r['code'])  ? trim((string)$r['code'])  : '';
                $label = isset($r['label']) ? trim((string)$r['label']) : '';
                if ($code === '' && $label === '') continue;
                $clean[] = [
                    'code'  => $code !== '' ? $code : null,
                    'label' => $label !== '' ? $label : null,
                ];
            }
            $realizationsJson = $clean ? json_encode($clean, JSON_UNESCAPED_UNICODE) : null;
        }

        // Build update set
        $set = [];
        if (array_key_exists('note', $data)) {
            $set['note'] = $note;
        }
        if (array_key_exists('realisations', $data) || array_key_exists('realizations', $data)) {
            $set['realizations_json'] = $realizationsJson;
        }

        if (!$set) {
            return $this->json(['ok' => true, 'changed' => 0]);
        }

        $changed = $this->conn->update('ongleri.orders', $set, ['id' => $id]);
        return $this->json(['ok' => true, 'changed' => $changed]);
    }

    // =========================
    // NEW: Revoke (annuler) an order and create a new rendez-vous
    // =========================
    #[Route('/orders/{id<\d+>}/revoke', name: 'orders_revoke', methods: ['POST'])]
    public function revoke(int $id, Request $req): JsonResponse
    {
        $data = json_decode($req->getContent(), true) ?? [];
        $reason = trim((string)($data['reason'] ?? ''));

        // Load order
        $order = $this->conn->fetchAssociative(
            "SELECT id, customer_id, appointment_id, status, note
               FROM ongleri.orders
              WHERE id = ?",
            [$id]
        );
        if (!$order) {
            return $this->json(['ok' => false, 'error' => 'ORDER_NOT_FOUND'], 404);
        }
        if (isset($order['status']) && $order['status'] === 'revoked') {
            return $this->json(['ok' => false, 'error' => 'ALREADY_REVOKED'], 409);
        }

        $customerId = (int)($order['customer_id'] ?? 0);

        // Optional explicit appointment payload
        $apptIn   = $data['appointment'] ?? [];
        $startAt  = $apptIn['start_at'] ?? null;
        $endAt    = $apptIn['end_at']   ?? null;
        $apptNote = $apptIn['note']     ?? null;

        $this->conn->beginTransaction();
        try {
            // If not provided, try to clone the original appointment
            if ((!$startAt || !$endAt) && !empty($order['appointment_id'])) {
                $src = $this->conn->fetchAssociative(
                    "SELECT start_at, end_at, note
                       FROM ongleri.appointments
                      WHERE id = ?",
                    [(int)$order['appointment_id']]
                );
                if ($src) {
                    $startAt  = $startAt ?: $src['start_at'];
                    $endAt    = $endAt   ?: $src['end_at'];
                    $apptNote = $apptNote ?? $src['note'];
                }
            }

            // Fallback: tomorrow 14:00–15:00
            if (!$startAt || !$endAt) {
                $startAt = (new \DateTimeImmutable('tomorrow 14:00'))->format('Y-m-d H:i:s');
                $endAt   = (new \DateTimeImmutable('tomorrow 15:00'))->format('Y-m-d H:i:s');
            }

            // Compose note for the new rendez-vous
            $rvNote = trim(
                ($apptNote ? ($apptNote . ' | ') : '') .
                "Créé depuis annulation commande #{$id}" .
                ($reason ? " — Raison: {$reason}" : '')
            );

            // Create new appointment
            $this->conn->insert('ongleri.appointments', [
                'customer_id' => $customerId ?: null,
                'start_at'    => $startAt,
                'end_at'      => $endAt,
                'status'      => 'planned',
                'note'        => $rvNote,
                'created_at'  => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
            $newApptId = (int)$this->conn->lastInsertId();

            // Mark order as revoked (requires DDL above)
            $update = [
                'revoked_at'                 => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'revoked_reason'             => $reason ?: null,
                'replacement_appointment_id' => $newApptId,
            ];
            // If column exists, set status=revoked
            try {
                $update['status'] = 'revoked';
                $this->conn->update('ongleri.orders', $update, ['id' => $id]);
            } catch (\Throwable $e) {
                // If status column doesn't exist yet, update without it
                unset($update['status']);
                $this->conn->update('ongleri.orders', $update, ['id' => $id]);
            }

            $this->conn->commit();

            return $this->json([
                'ok' => true,
                'order_id' => $id,
                'revoked' => true,
                'replacement_appointment_id' => $newApptId,
                'appointment' => [
                    'start_at' => $startAt,
                    'end_at'   => $endAt,
                    'note'     => $rvNote,
                ],
            ]);
        } catch (\Throwable $e) {
            $this->conn->rollBack();
            return $this->json(['ok' => false, 'error' => 'TX_FAILED', 'detail' => $e->getMessage()], 500);
        }
    }
}
