<?php
namespace App\Controller;

use App\Service\CalendarSync;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/pos', name: 'api_pos_')]
class PosAppointmentsApiController extends AbstractController
{
    public function __construct(
        private Connection $conn,
        // Make CalendarSync optional so the API still works if not wired
        private ?CalendarSync $sync = null
    ) {}

    #[Route('/appointments', name: 'appointments_create', methods: ['POST'])]
    public function create(Request $req): JsonResponse
    {
        $p = json_decode((string)$req->getContent(), true) ?? [];

        // --- Basic validation ---
        $customerId = $p['customer_id'] ?? null;
        $startAt    = $this->normalizeDateTime($p['start_at'] ?? null);
        $endAt      = $this->normalizeDateTime($p['end_at']   ?? null);

        if (!$customerId) return $this->json(['ok' => false, 'error' => 'customer_id is required'], 400);
        if (!$startAt || !$endAt) return $this->json(['ok' => false, 'error' => 'start_at and end_at are required'], 400);

        $status      = $p['status'] ?? 'booked';
        $notesPublic = $p['notes_public'] ?? null;

        // --- Insert appointment ---
        $this->conn->insert('ongleri.appointments', [
            'customer_id'  => $customerId,
            'start_at'     => $startAt,
            'end_at'       => $endAt,
            'status'       => $status,
            'notes_public' => $notesPublic,
        ]);
        $id = (int)$this->conn->lastInsertId();

        // --- Mirror to Google (best-effort) ---
        $syncInfo = ['enabled' => (bool)$this->sync, 'action' => 'none'];
        if ($this->sync) {
            try {
                $eventId = $this->sync->upsertForAppointment($id);
                $syncInfo = ['enabled' => true, 'action' => 'upsert', 'event_id' => $eventId];
            } catch (\Throwable $e) {
                // Don’t fail the API; just report the issue
                $syncInfo = ['enabled' => true, 'action' => 'error', 'message' => $e->getMessage()];
            }
        }

        return $this->json(['ok' => true, 'id' => $id, 'google_sync' => $syncInfo], 201);
    }

    #[Route('/appointments/{id<\d+>}', name: 'appointments_patch', methods: ['PATCH'])]
    public function patch(int $id, Request $req): JsonResponse
    {
        $p = json_decode((string)$req->getContent(), true) ?? [];

        // Only allow specific fields; normalize datetimes if present
        $allowed = ['start_at','end_at','status','notes_public','real_start_at','real_end_at'];
        $upd = array_intersect_key($p, array_flip($allowed));

        if (isset($upd['start_at']))    $upd['start_at']    = $this->normalizeDateTime($upd['start_at']);
        if (isset($upd['end_at']))      $upd['end_at']      = $this->normalizeDateTime($upd['end_at']);
        if (isset($upd['real_start_at'])) $upd['real_start_at'] = $this->normalizeDateTime($upd['real_start_at']);
        if (isset($upd['real_end_at']))   $upd['real_end_at']   = $this->normalizeDateTime($upd['real_end_at']);

        if (!$upd) {
            return $this->json(['ok' => true, 'google_sync' => ['enabled' => (bool)$this->sync, 'action' => 'skipped']]);
        }

        // Update DB
        $this->conn->update('ongleri.appointments', $upd, ['id' => $id]);

        // Mirror to Google
        $syncInfo = ['enabled' => (bool)$this->sync, 'action' => 'none'];
        $shouldDelete =
            isset($upd['status']) && in_array(strtolower((string)$upd['status']), ['cancelled', 'canceled', 'deleted'], true);

        if ($this->sync) {
            try {
                if ($shouldDelete) {
                    $this->sync->deleteForAppointment($id);
                    $syncInfo = ['enabled' => true, 'action' => 'deleted'];
                } else {
                    $eventId = $this->sync->upsertForAppointment($id);
                    $syncInfo = ['enabled' => true, 'action' => 'upsert', 'event_id' => $eventId];
                }
            } catch (\Throwable $e) {
                $syncInfo = ['enabled' => true, 'action' => 'error', 'message' => $e->getMessage()];
            }
        }

        return $this->json(['ok' => true, 'google_sync' => $syncInfo]);
    }

    #[Route('/appointments/{id<\d+>}', name: 'appointments_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        // Best-effort Google delete first (so we can still read google_event_id if your sync needs it)
        $syncInfo = ['enabled' => (bool)$this->sync, 'action' => 'none'];
        if ($this->sync) {
            try {
                $this->sync->deleteForAppointment($id);
                $syncInfo = ['enabled' => true, 'action' => 'deleted'];
            } catch (\Throwable $e) {
                $syncInfo = ['enabled' => true, 'action' => 'error', 'message' => $e->getMessage()];
            }
        }

        // Then remove from DB
        $this->conn->delete('ongleri.appointments', ['id' => $id]);

        return $this->json(['ok' => true, 'google_sync' => $syncInfo]);
    }

    /**
     * Accepts:
     *  - "YYYY-MM-DD HH:MM:SS"
     *  - ISO-8601 ("YYYY-MM-DDTHH:MM[:SS][Z]")
     * Returns normalized "YYYY-MM-DD HH:MM:SS" in server TZ, or null if invalid.
     */
    private function normalizeDateTime(?string $value): ?string
    {
        if (!$value) return null;

        // Accept both SQL and ISO; replace single space with 'T' so DateTime can parse consistently.
        $raw = str_replace(' ', 'T', trim($value));
        try {
            $dt = new \DateTimeImmutable($raw);
            return $dt->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }
}
