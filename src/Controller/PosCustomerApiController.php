<?php
namespace App\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType; // 👈 keep this
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/pos', name: 'api_pos_')]
class PosCustomerApiController extends AbstractController
{
    public function __construct(private Connection $conn) {}

    /** Small helper: trim value, return NULL if blank */
    private function tnull(?string $v): ?string
    {
        $v = trim((string)($v ?? ''));
        return $v === '' ? null : $v;
    }

    #[Route('/customers', name: 'customers_search', methods: ['GET'])]
    public function search(Request $req): JsonResponse
    {
        $q = trim((string)$req->query->get('q', ''));

        // No query -> last updated
        if ($q === '') {
            $rows = $this->conn->fetchAllAssociative(
                "SELECT id, first_name, last_name, phone, email
                   FROM ongleri.customers
                  ORDER BY COALESCE(updated_at, created_at) DESC, id DESC
                  LIMIT 20"
            );
            return $this->json(['items' => $rows]);
        }

        // Try to detect direct ID search
        $maybeId = ctype_digit($q) ? (int)$q : null;
        $like    = '%'.$q.'%';

        // ✅ Positional placeholders + DBAL types
        $sql = "
            SELECT id, first_name, last_name, phone, email
              FROM ongleri.customers
             WHERE (? IS NOT NULL AND id = ?)
                OR first_name LIKE ?
                OR last_name  LIKE ?
                OR phone      LIKE ?
                OR email      LIKE ?
             ORDER BY
                CASE WHEN (? IS NOT NULL AND id = ?) THEN 0 ELSE 1 END,
                last_name, first_name
             LIMIT 25
        ";

        $params = [
            $maybeId, $maybeId,
            $like, $like, $like, $like,
            $maybeId, $maybeId,
        ];
        $types = [
            ParameterType::INTEGER, ParameterType::INTEGER,
            ParameterType::STRING,  ParameterType::STRING,
            ParameterType::STRING,  ParameterType::STRING,
            ParameterType::INTEGER, ParameterType::INTEGER,
        ];

        $rows = $this->conn->fetchAllAssociative($sql, $params, $types);
        return $this->json(['items' => $rows]);
    }

    #[Route('/customers', name: 'customers_create', methods: ['POST'])]
    public function create(Request $req): JsonResponse
    {
        $p  = json_decode($req->getContent(), true) ?? [];
        $fn = trim($p['first_name'] ?? '');
        $ln = trim($p['last_name'] ?? '');
        if ($fn === '' || $ln === '') {
            return $this->json(['error' => 'Missing name'], 400);
        }

        // normalize blanks -> NULL (so UNIQUE(email) allows many NULLs)
        $phone = $this->tnull($p['phone'] ?? null);
        $email = $this->tnull($p['email'] ?? null);

        try {
            $this->conn->insert('ongleri.customers', [
                'first_name'    => $fn,
                'last_name'     => $ln,
                'phone'         => $phone,
                'email'         => $email,
                'notes_public'  => $this->tnull($p['notes_public']  ?? null),
                'notes_private' => $this->tnull($p['notes_private'] ?? null),
                'gdpr_ok'       => !empty($p['gdpr_ok']) ? 1 : 0,
            ]);
            $id = (int)$this->conn->lastInsertId();
            return $this->json(['ok' => true, 'id' => $id]);
        } catch (UniqueConstraintViolationException $e) {
            return $this->json(['error' => 'Email déjà utilisé'], 409);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'DB error', 'detail' => $e->getMessage()], 500);
        }
    }

    #[Route('/customers/{id}', name: 'customers_get', methods: ['GET'])]
    public function getOne(int $id): JsonResponse
    {
        // Customer
        $c = $this->conn->fetchAssociative(
            "SELECT id, first_name, last_name, phone, email, notes_public, notes_private, gdpr_ok, created_at, updated_at
               FROM ongleri.customers
              WHERE id = ?",
            [$id],
            [ParameterType::INTEGER]
        );
        if (!$c) {
            return $this->json(['error' => 'Not found'], 404);
        }

        // Appointments (planned vs real + durations)
        $appts = $this->conn->fetchAllAssociative(
            "SELECT
                 id,
                 start_at,
                 end_at,
                 real_start_at,
                 real_end_at,
                 status,
                 notes_public,
                 notes_private,
                 TIMESTAMPDIFF(MINUTE, start_at, end_at) AS planned_minutes,
                 CASE
                   WHEN real_start_at IS NOT NULL AND real_end_at IS NOT NULL
                     THEN TIMESTAMPDIFF(MINUTE, real_start_at, real_end_at)
                   ELSE NULL
                 END AS real_minutes
             FROM ongleri.appointments
             WHERE customer_id = ?
             ORDER BY start_at DESC
             LIMIT 50",
            [$id],
            [ParameterType::INTEGER]
        );

        // Orders
        $orders = $this->conn->fetchAllAssociative(
            "SELECT id, created_at, total_cents, total_tax_cents, note, appointment_id, elapsed_minutes, encaisse_at, tip_cents, realizations_json
               FROM ongleri.orders
              WHERE customer_id = ?
              ORDER BY created_at DESC
              LIMIT 50",
            [$id],
            [ParameterType::INTEGER]
        );

        // Current active appointment: booked and not finished
        $activeId = null;
        foreach ($appts as $a) {
            if ($a['status'] === 'booked' && empty($a['real_end_at'])) {
                $activeId = (int)$a['id'];
                break;
            }
        }

        return $this->json([
            'customer'               => $c,
            'appointments'           => $appts,
            'orders'                 => $orders,
            'active_appointment_id'  => $activeId,
        ]);
    }

    /**
     * Partial update for inline edit (PATCH) or full replace (PUT).
     * Accepts JSON fields: first_name, last_name, phone, email, notes_public, notes_private, gdpr_ok.
     */
    #[Route('/customers/{id}', name: 'customers_update', methods: ['PATCH','PUT'])]
    public function update(int $id, Request $req): JsonResponse
    {
        // Ensure customer exists
        $exists = (int)$this->conn->fetchOne(
            "SELECT COUNT(*) FROM ongleri.customers WHERE id = ?",
            [$id],
            [ParameterType::INTEGER]
        );
        if ($exists === 0) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $data = json_decode($req->getContent(), true) ?? [];

        // Whitelist updatable fields
        $allowed = [
            'first_name', 'last_name', 'phone', 'email',
            'notes_public', 'notes_private', 'gdpr_ok'
        ];

        $setParts = [];
        $params   = [];
        $types    = [];

        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $val = $data[$f];
                if (in_array($f, ['phone','email','notes_public','notes_private'], true)) {
                    $val = $this->tnull($val);
                }
                if ($f === 'gdpr_ok') {
                    $val = !empty($val) ? 1 : 0;
                    $types[] = ParameterType::INTEGER;
                } elseif ($f === 'first_name' || $f === 'last_name') {
                    $val = trim((string)$val);
                    $types[] = ParameterType::STRING;
                } else {
                    $types[] = $val === null ? ParameterType::STRING : ParameterType::STRING;
                }
                $setParts[] = "$f = ?";
                $params[]   = $val;
            }
        }

        if (!$setParts) {
            return $this->json(['error' => 'No fields to update'], 400);
        }

        // Append updated_at and id
        $sql = "UPDATE ongleri.customers SET ".implode(', ', $setParts).", updated_at = NOW() WHERE id = ?";
        $params[] = $id;
        $types[]  = ParameterType::INTEGER;

        try {
            $this->conn->executeStatement($sql, $params, $types);

            // Return the fresh row
            $row = $this->conn->fetchAssociative(
                "SELECT id, first_name, last_name, phone, email, notes_public, notes_private, gdpr_ok, created_at, updated_at
                   FROM ongleri.customers
                  WHERE id = ?",
                [$id],
                [ParameterType::INTEGER]
            );

            return $this->json($row);
        } catch (UniqueConstraintViolationException $e) {
            return $this->json(['error' => 'Email déjà utilisé'], 409);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'DB error', 'detail' => $e->getMessage()], 500);
        }
    }
}
