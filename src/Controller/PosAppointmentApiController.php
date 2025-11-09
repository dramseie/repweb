<?php
namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/pos', name: 'api_pos_')]
class PosAppointmentApiController extends AbstractController
{
    public function __construct(private Connection $conn) {}

    #[Route('/appointments', name: 'appointments_list', methods: ['GET'])]
    public function list(Request $req): JsonResponse
    {
        $from = $req->query->get('from'); // 'YYYY-MM-DD HH:MM:SS' (or ISO)
        $to   = $req->query->get('to');

        // normalize ISO "T" to space if needed
        $from = $from ? str_replace('T', ' ', substr($from, 0, 19)) : null;
        $to   = $to   ? str_replace('T', ' ', substr($to,   0, 19)) : null;

    $sql = "SELECT a.id, a.customer_id, c.first_name, c.last_name,
               COALESCE(c.status, 'active') AS customer_status,
                       a.start_at, a.end_at, a.real_start_at, a.real_end_at,
                       a.status, a.notes_public
                  FROM ongleri.appointments a
                  JOIN ongleri.customers c ON c.id = a.customer_id
                 WHERE 1=1";
        $params = [];
        if ($from) { $sql .= " AND a.start_at >= ?"; $params[] = $from; }
        if ($to)   { $sql .= " AND a.start_at <  ?"; $params[] = $to; }
        $sql .= " ORDER BY a.start_at";

        return $this->json(['items' => $this->conn->fetchAllAssociative($sql, $params)]);
    }

    #[Route('/appointments', name: 'appointments_create', methods: ['POST'])]
    public function create(Request $req): JsonResponse
    {
        $p = json_decode($req->getContent(), true) ?? [];
        if (empty($p['customer_id']) || empty($p['start_at']) || empty($p['end_at'])) {
            return $this->json(['error' => 'Missing fields'], 400);
        }

        // normalize date strings
        $start = str_replace('T', ' ', substr((string)$p['start_at'], 0, 19));
        $end   = str_replace('T', ' ', substr((string)$p['end_at'],   0, 19));

        // optional: basic status whitelist
        $status = $p['status'] ?? 'booked';
        if (!in_array($status, ['booked','done','cancelled','no_show'], true)) {
            $status = 'booked';
        }

        $this->conn->insert('ongleri.appointments', [
            'customer_id'   => (int)$p['customer_id'],
            'start_at'      => $start,
            'end_at'        => $end,
            'status'        => $status,
            'notes_public'  => $p['notes_public']  ?? null,
            'notes_private' => $p['notes_private'] ?? null,
        ]);
        $id = (int)$this->conn->lastInsertId();

        // Optional: planned items
        foreach (($p['items'] ?? []) as $it) {
            $this->conn->insert('ongleri.appointment_items', [
                'appointment_id' => $id,
                'item_id' => (int)($it['item_id'] ?? 0),
                'qty'     => max(1,(int)($it['qty'] ?? 1)),
            ]);
        }

        return $this->json(['ok' => true, 'id' => $id]);
    }

    #[Route('/appointments/{id}', name: 'appointments_patch', methods: ['PATCH'])]
    public function patch(int $id, Request $req): JsonResponse
    {
        $p = json_decode($req->getContent(), true) ?? [];
        $fields = [];

        foreach (['status','notes_public','notes_private','start_at','end_at','real_start_at','real_end_at'] as $k) {
            if (array_key_exists($k, $p)) {
                $v = $p[$k];
                if (in_array($k, ['start_at','end_at','real_start_at','real_end_at'], true) && is_string($v)) {
                    $v = str_replace('T', ' ', substr($v, 0, 19)); // normalize
                }
                if ($k === 'status' && !in_array($v, ['booked','done','cancelled','no_show'], true)) {
                    continue; // ignore bad status
                }
                $fields[$k] = $v;
            }
        }

        if ($fields) {
            $this->conn->update('ongleri.appointments', $fields, ['id' => $id]);
        }
        return $this->json(['ok' => true]);
    }

    #[Route('/appointments/{id}', name: 'appointments_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        // If you have FK from orders.appointment_id, DB will prevent delete when linked.
        // You can soft-delete or check first if needed.
        $this->conn->delete('ongleri.appointments', ['id' => $id]);
        return $this->json(['ok' => true]);
    }
}
