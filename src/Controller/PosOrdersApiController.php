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
        // Try with custom_items_json; fall back if column does not exist
        try {
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
                        o.status,
                        o.revoked_at,
                        o.revoked_reason,
                        o.replacement_appointment_id,
                        o.payment_method,
                        o.payments_json,
                        o.custom_items_json
                   FROM ongleri.orders o
                  WHERE o.id = ?",
                [$id]
            );
            $hasCustomJson = true;
        } catch (\Throwable $e) {
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
                        o.status,
                        o.revoked_at,
                        o.revoked_reason,
                        o.replacement_appointment_id,
                        o.payment_method,
                        o.payments_json
                   FROM ongleri.orders o
                  WHERE o.id = ?",
                [$id]
            );
            $hasCustomJson = false;
        }

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

        // Optional parsed custom items (Divers)
        $customItems = null;
        if ($hasCustomJson && array_key_exists('custom_items_json', $o) && $o['custom_items_json'] !== null) {
            try {
                $parsed = json_decode((string)$o['custom_items_json'], true);
                $customItems = is_array($parsed) ? $parsed : null;
            } catch (\Throwable $e) {
                $customItems = null;
            }
        }

        // Customer (optional) — WITHOUT private notes
        $customer = null;
        if (!empty($o['customer_id'])) {
            $customer = $this->conn->fetchAssociative(
                "SELECT id, first_name, last_name, phone, email, notes_public
                   FROM ongleri.customers
                  WHERE id = ?",
                [(int)$o['customer_id']]
            );
        }

        // Appointment (optional) — WITHOUT private notes
        $appointment = null;
        if (!empty($o['appointment_id'])) {
            $appointment = $this->conn->fetchAssociative(
                "SELECT id, customer_id, start_at, end_at, real_start_at, real_end_at,
                        status, notes_public
                   FROM ongleri.appointments
                  WHERE id = ?",
                [(int)$o['appointment_id']]
            );
        }

        return $this->json([
            'ok'            => true,
            'order'         => $o,
            'items'         => $items,
            'custom_items'  => $customItems,     // 👈 for Divers display in UI
            'customer'      => $customer,
            'appointment'   => $appointment,
        ]);
    }

    // =========================
    // CREATE ORDER  (supports custom_items a.k.a. “Divers”)
    // =========================
    #[Route('/orders', name: 'orders_create', methods: ['POST'])]
    public function create(Request $req): JsonResponse
    {
        $p = json_decode($req->getContent(), true) ?? [];

        $items   = (array)($p['items'] ?? []);          // [{item_id, qty}]
        $customs = (array)($p['custom_items'] ?? []);   // [{label, unit_cents, qty, tax_rate}]

        if (!$items && !$customs) {
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

        // Price lookup for regular items (only if we have some)
        $byId = [];
        if ($items) {
            $ids = array_values(array_unique(array_map(fn($r) => (int)($r['item_id'] ?? 0), $items)));
            $ids = array_filter($ids, fn($v) => $v > 0);
            if ($ids) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $rows = $this->conn->fetchAllAssociative(
                    "SELECT id, name, price_cents, tax_rate
                       FROM ongleri.items
                      WHERE id IN ($placeholders)",
                    $ids
                );
                foreach ($rows as $r) $byId[(int)$r['id']] = $r;
            }
        }

        $lines   = [];            // regular items only go to order_items
        $net     = 0;
        $taxOnly = 0;

        // Regular items
        foreach ($items as $it) {
            $iid = (int)($it['item_id'] ?? 0);
            $qty = max(1, (int)($it['qty'] ?? 1));
            if (!isset($byId[$iid])) continue;

            $unit = (int)$byId[$iid]['price_cents'];
            $rate = (float)$byId[$iid]['tax_rate'];
            $line = $unit * $qty;
            $tax  = (int)round($line * ($rate / 100));

            $net     += $line;
            $taxOnly += $tax;

            $lines[] = [
                'item_id'          => $iid,
                'name_snapshot'    => $byId[$iid]['name'],
                'unit_price_cents' => $unit,
                'tax_rate'         => $rate,
                'qty'              => $qty,
                'line_total_cents' => $line,
            ];
        }

        // 🔹 Custom items (Divers) — affect totals; persisted as JSON if column exists
        $customsClean = [];
        foreach ($customs as $c) {
            if (!is_array($c)) continue;
            $label = trim((string)($c['label'] ?? 'Divers'));
            $qty   = max(1, (int)($c['qty'] ?? 1));
            $unit  = max(0, (int)($c['unit_cents'] ?? 0));
            $rate  = (float)($c['tax_rate'] ?? 0);
            $line  = $unit * $qty;
            $tax   = (int)round($line * $rate / 100);

            $net     += $line;
            $taxOnly += $tax;

            $customsClean[] = [
                'label'       => $label,
                'unit_cents'  => $unit,
                'qty'         => $qty,
                'tax_rate'    => $rate,
                'line_cents'  => $line,
                'tax_cents'   => $tax,
            ];
        }

        if (!$lines && !$customsClean) {
            return $this->json(['ok' => false, 'error' => 'No valid items'], 400);
        }

        $total = $net + $taxOnly;

        $this->conn->beginTransaction();
        try {
            // Build base order insert
            $orderInsert = [
                'total_cents'       => $total,
                'total_tax_cents'   => $taxOnly,
                'note'              => $note,
                'customer_id'       => $customerId ?: null,
                'appointment_id'    => $appointmentId ?: null,
                'realizations_json' => $realizationsJson,
                'custom_items_json' => $customsClean ? json_encode($customsClean, JSON_UNESCAPED_UNICODE) : null,
            ];

            // Try insert with custom_items_json; if the column does not exist, retry without it
            try {
                $this->conn->insert('ongleri.orders', $orderInsert);
            } catch (\Throwable $e) {
                if (stripos($e->getMessage(), 'Unknown column') !== false && stripos($e->getMessage(), 'custom_items_json') !== false) {
                    unset($orderInsert['custom_items_json']);
                    $this->conn->insert('ongleri.orders', $orderInsert);
                } else {
                    throw $e;
                }
            }

            $orderId = (int)$this->conn->lastInsertId();

            // Insert regular item lines
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
                'total_tax_cents'  => $taxOnly,
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
    // ENCAISSER (persist payments_json + payment_method, set status=done)
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

        // Provided values from UI
        $amountReceivedCents = max(0, (int)($p['amountReceivedCents'] ?? 0));
        $tipFromUi           = max(0, (int)($p['tipCents'] ?? 0));
        $payments            = is_array($p['payments'] ?? null) ? $p['payments'] : [];
        $method              = $payments[0]['method'] ?? ($p['method'] ?? null);

        // Fallbacks
        $amountDueCents  = (int)$order['total_cents'];
        $tipComputed     = max(0, $amountReceivedCents - $amountDueCents);
        $tipCents        = $tipFromUi > 0 ? $tipFromUi : $tipComputed;

        $encaisseAtIso = (string)($p['encaisseAtIso'] ?? (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM));
        $encaisseAt    = new \DateTimeImmutable($encaisseAtIso);

        // Elapsed minutes — honor UI; else compute from appointment start if available
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

        // Persist
        $this->conn->executeStatement(
            "UPDATE ongleri.orders
                SET amount_received_cents = :ar,
                    tip_cents             = :tip,
                    encaisse_at           = :dt,
                    elapsed_minutes       = :em,
                    status                = 'done',
                    payments_json         = :pjson,
                    payment_method        = :pmethod
              WHERE id = :id",
            [
                'ar'     => $amountReceivedCents,
                'tip'    => $tipCents,
                'dt'     => $encaisseAt->format('Y-m-d H:i:s'),
                'em'     => $elapsedMinutes,
                'pjson'  => $payments ? json_encode($payments, JSON_UNESCAPED_UNICODE) : null,
                'pmethod'=> $method,
                'id'     => $id,
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
                    realizations_json,
                    payments_json,
                    payment_method,
                    status
               FROM ongleri.orders
              WHERE id = ?",
            [$id]
        );

        return $this->json(['ok' => true, 'item' => $row]);
    }

    // =========================
    // Update note + réalisations + (NEW) payment_method (allowed anytime)
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

        // (NEW) payment_method update
        $set = [];
        if (array_key_exists('note', $data)) {
            $set['note'] = $note;
        }
        if (array_key_exists('realisations', $data) || array_key_exists('realizations', $data)) {
            $set['realizations_json'] = $realizationsJson;
        }
        if (array_key_exists('payment_method', $data)) {
            $pm = $data['payment_method'];
            if (is_string($pm)) {
                $pm = strtolower(trim($pm));
                if (!in_array($pm, ['cash','card','other'], true)) {
                    $pm = null; // normalize invalid -> null
                }
            } else {
                $pm = null;
            }
            $set['payment_method'] = $pm;
        }

        if (!$set) {
            return $this->json(['ok' => true, 'changed' => 0]);
        }

        $changed = $this->conn->update('ongleri.orders', $set, ['id' => $id]);
        return $this->json(['ok' => true, 'changed' => $changed]);
    }

    // =========================
    // Save public notes (order + customer + appointment)
    // =========================
    #[Route('/orders/{id<\d+>}/notes', name: 'orders_update_notes', methods: ['PATCH','POST'])]
    public function updateNotes(int $id, Request $req): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $payload = json_decode($req->getContent(), true) ?? [];

        $orderNote = array_key_exists('order_note', $payload) ? (string)($payload['order_note'] ?? '') : null;
        $custPub   = array_key_exists('customer_notes_public', $payload) ? (string)($payload['customer_notes_public'] ?? '') : null;
        $apptPub   = array_key_exists('appointment_notes_public', $payload) ? (string)($payload['appointment_notes_public'] ?? '') : null;

        // Load customer_id & appointment_id from the order
        $o = $this->conn->fetchAssociative(
            "SELECT customer_id, appointment_id FROM ongleri.orders WHERE id = ?",
            [$id]
        );
        if (!$o) {
            return new JsonResponse(['error' => 'Order not found'], 404);
        }

        $this->conn->beginTransaction();
        try {
            // Helper to try UPDATE with updated_at, then fallback without it
            $tryUpdate = function (string $sqlWithUpdatedAt, array $argsWithUpdatedAt, string $sqlNoUpdatedAt, array $argsNoUpdatedAt) {
                try {
                    $this->conn->executeStatement($sqlWithUpdatedAt, $argsWithUpdatedAt);
                } catch (\Throwable $e) {
                    // Retry without updated_at
                    $this->conn->executeStatement($sqlNoUpdatedAt, $argsNoUpdatedAt);
                }
            };

            // Order note
            if ($orderNote !== null) {
                $tryUpdate(
                    "UPDATE ongleri.orders SET note = ?, updated_at = NOW() WHERE id = ?",
                    [$orderNote, $id],
                    "UPDATE ongleri.orders SET note = ? WHERE id = ?",
                    [$orderNote, $id]
                );
            }

            // Customer public notes
            if ($custPub !== null && !empty($o['customer_id'])) {
                $cid = (int)$o['customer_id'];
                $tryUpdate(
                    "UPDATE ongleri.customers SET notes_public = ?, updated_at = NOW() WHERE id = ?",
                    [$custPub, $cid],
                    "UPDATE ongleri.customers SET notes_public = ? WHERE id = ?",
                    [$custPub, $cid]
                );
            }

            // Appointment public notes
            if ($apptPub !== null && !empty($o['appointment_id'])) {
                $aid = (int)$o['appointment_id'];
                $tryUpdate(
                    "UPDATE ongleri.appointments SET notes_public = ?, updated_at = NOW() WHERE id = ?",
                    [$apptPub, $aid],
                    "UPDATE ongleri.appointments SET notes_public = ? WHERE id = ?",
                    [$apptPub, $aid]
                );
            }

            $this->conn->commit();
            return new JsonResponse(['ok' => true]);
        } catch (\Throwable $e) {
            $this->conn->rollBack();
            return new JsonResponse(['error' => 'Failed to save notes', 'detail' => $e->getMessage()], 500);
        }
    }

    // =========================
    // DELETE order (and its items)
    // =========================
    #[Route('/orders/{id<\d+>}', name: 'orders_delete', methods: ['DELETE','POST'])]
    public function deleteOrder(int $id, Request $req): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $force = (string)$req->query->get('force', '0') === '1';

        $o = $this->conn->fetchAssociative(
            "SELECT id, encaisse_at, appointment_id FROM ongleri.orders WHERE id = ?",
            [$id]
        );
        if (!$o) {
            return $this->json(['ok' => false, 'error' => 'ORDER_NOT_FOUND'], 404);
        }

        if (!$force && !empty($o['encaisse_at'])) {
            return $this->json([
                'ok'    => false,
                'error' => 'ORDER_ENCAISSED',
                'hint'  => 'Use ?force=1 to delete anyway.'
            ], 409);
        }

        $this->conn->beginTransaction();
        try {
            // Delete children first
            $this->conn->executeStatement(
                "DELETE FROM ongleri.order_items WHERE order_id = ?",
                [$id]
            );

            // Delete the order itself
            $this->conn->executeStatement(
                "DELETE FROM ongleri.orders WHERE id = ?",
                [$id]
            );

            // Reset appointment status to booked (if linked)
            if (!empty($o['appointment_id'])) {
                $this->conn->executeStatement(
                    "UPDATE ongleri.appointments
                        SET status = 'booked'
                      WHERE id = ?",
                    [(int)$o['appointment_id']]
                );
            }

            $this->conn->commit();

            return $this->json([
                'ok'         => true,
                'deleted_id' => $id,
                'appointment_reset' => $o['appointment_id'] ?? null,
            ]);
        } catch (\Throwable $e) {
            $this->conn->rollBack();
            return $this->json([
                'ok'    => false,
                'error' => 'TX_FAILED',
                'detail'=> $e->getMessage(),
            ], 500);
        }
    }

    // =========================
    // Revoke (annuler) an order and create a new rendez-vous
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

            // Mark order as revoked
            $update = [
                'revoked_at'                 => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'revoked_reason'             => $reason ?: null,
                'replacement_appointment_id' => $newApptId,
            ];
            try {
                $update['status'] = 'revoked';
                $this->conn->update('ongleri.orders', $update, ['id' => $id]);
            } catch (\Throwable $e) {
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
