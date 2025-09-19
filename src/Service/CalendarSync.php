<?php
namespace App\Service;

use Doctrine\DBAL\Connection;

final class CalendarSync
{
    public function __construct(
        private Connection $db,
        private GoogleCalendarService $gcal,
    ) {}

    private int $userId = 1;            // single-tenant for now
    private string $calendarId = 'primary';

    public function upsertForAppointment(int $appointmentId): void
    {
        $a = $this->db->fetchAssociative(
            "SELECT a.id, a.customer_id, a.start_at, a.end_at, a.notes_public, a.gcal_event_id,
                    c.first_name, c.last_name, c.phone, c.email
             FROM ongleri.appointments a
             LEFT JOIN ongleri.customers c ON c.id = a.customer_id
             WHERE a.id = ?",
            [$appointmentId]
        );
        if (!$a) return;

        // Build title & description
        $name = trim(($a['last_name'] ?? '') . ' ' . ($a['first_name'] ?? ''));
        $summary = $name !== '' ? "RDV – $name" : 'RDV';
        $contact = trim(($a['phone'] ?? '') . ' ' . ($a['email'] ?? ''));
        $descParts = array_filter([
            $a['notes_public'] ?: null,
            $contact !== '' ? "Contact: $contact" : null,
            "Appoint. ID: {$a['id']}"
        ]);
        $description = implode("\n", $descParts);

        $start = new \DateTimeImmutable($a['start_at']);
        $end   = new \DateTimeImmutable($a['end_at']);

        $eventId = $this->gcal->upsertEvent(
            $this->userId,
            $this->calendarId,
            (string)($a['gcal_event_id'] ?? ''),
            $summary,
            $start,
            $end,
            $description
        );

        if ($eventId && $eventId !== ($a['gcal_event_id'] ?? null)) {
            $this->db->update('ongleri.appointments', ['gcal_event_id' => $eventId], ['id' => $appointmentId]);
        }
    }

    public function deleteForAppointment(int $appointmentId): void
    {
        $eventId = $this->db->fetchOne(
            "SELECT gcal_event_id FROM ongleri.appointments WHERE id = ?",
            [$appointmentId]
        );
        if ($eventId) {
            $this->gcal->deleteEvent($this->userId, $this->calendarId, (string)$eventId);
            $this->db->update('ongleri.appointments', ['gcal_event_id' => null], ['id' => $appointmentId]);
        }
    }
}
