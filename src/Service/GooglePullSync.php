<?php
namespace App\Service;

use Doctrine\DBAL\Connection;
use Google\Service\Calendar;

final class GooglePullSync
{
    public function __construct(
        private GoogleCalendarService $gcal,   // your existing service (already authorized)
        private Connection $db,
        private string $defaultTz = 'Europe/Zurich'
    ) {}

    /**
     * Pull events from Google Calendar and upsert into local appointments.
     * - Never modifies Google (read-only)
     * - Creates/updates local rows
     */
    public function syncUserFromGoogle(
        int $userId,
        string $calendarId = 'primary',
        ?\DateTimeInterface $timeMin = null,
        ?\DateTimeInterface $timeMax = null
    ): array {
        [$client, $svc] = $this->gcal->authorized($userId); // add a small helper in GoogleCalendarService (below)
        assert($svc instanceof Calendar);

        $params = [
            'singleEvents' => true,
            'orderBy'      => 'startTime',
            'showDeleted'  => true,           // so we can mark local as cancelled if needed
            'maxResults'   => 2500,
        ];
        if ($timeMin) $params['timeMin'] = $timeMin->format(\DateTimeInterface::RFC3339);
        if ($timeMax) $params['timeMax'] = $timeMax->format(\DateTimeInterface::RFC3339);

        $created = 0; $updated = 0; $cancelled = 0; $skipped = 0;

        $pageToken = null;
        do {
            if ($pageToken) $params['pageToken'] = $pageToken;
            $list = $svc->events->listEvents($calendarId, $params);

            foreach ($list->getItems() as $ev) {
                $gId   = $ev->getId();
                $etag  = $ev->getEtag();
                $stat  = $ev->getStatus(); // confirmed | tentative | cancelled

                // Start/end can be date (all-day) or dateTime
                [$start, $end] = $this->extractTimes($ev);

                if (!$start || !$end) { $skipped++; continue; }

                // Find existing mapping
                $map = $this->db->fetchAssociative(
                    "SELECT * FROM google_events_map
                     WHERE user_id=? AND calendar_id=? AND google_event_id=?",
                    [$userId, $calendarId, $gId]
                );

                if (!$map) {
					$defaultCustomerId = 1;
                    // Create local appointment
                    $this->db->insert('ongleri.appointments', [
                        'customer_id'  => $defaultCustomerId,
                        'start_at'     => $start->format('Y-m-d H:i:s'),
                        'end_at'       => $end->format('Y-m-d H:i:s'),
                        'status'       => $stat === 'cancelled' ? 'cancelled' : 'booked',
                        'notes_public' => trim((string)$ev->getSummary() ?: (string)$ev->getDescription()) ?: null,
                    ]);
                    $apptId = (int)$this->db->lastInsertId();

                    // Store mapping
                    $this->db->insert('google_events_map', [
                        'user_id'         => $userId,
                        'calendar_id'     => $calendarId,
                        'google_event_id' => $gId,
                        'etag'            => $etag,
                        'updated_at_utc'  => $this->toUtc($ev->getUpdated()),
                        'appointment_id'  => $apptId,
                    ]);

                    $created++;
                    continue;
                }

                $apptId = (int)$map['appointment_id'];

                // Update local if etag/updated changed (or if we have no mapped appointment)
                $needUpdate = ($map['etag'] !== $etag);

                if ($apptId && $needUpdate) {
                    $this->db->update('ongleri.appointments', [
                        'start_at'     => $start->format('Y-m-d H:i:s'),
                        'end_at'       => $end->format('Y-m-d H:i:s'),
                        'status'       => $stat === 'cancelled' ? 'cancelled' : 'booked',
                        'notes_public' => trim((string)$ev->getSummary() ?: (string)$ev->getDescription()) ?: null,
                    ], ['id' => $apptId]);

                    $this->db->update('google_events_map', [
                        'etag'           => $etag,
                        'updated_at_utc' => $this->toUtc($ev->getUpdated()),
                    ], ['id' => $map['id']]);

                    if ($stat === 'cancelled') $cancelled++; else $updated++;
                } else {
                    $skipped++;
                }
            }

            $pageToken = $list->getNextPageToken();
        } while ($pageToken);

        return compact('created','updated','cancelled','skipped');
    }

    private function extractTimes(\Google\Service\Calendar\Event $ev): array
    {
        $s = $ev->getStart();
        $e = $ev->getEnd();
        if (!$s || !$e) return [null, null];

        // all-day => date (YYYY-MM-DD)
        if ($s->getDate()) {
            $start = new \DateTimeImmutable($s->getDate() . ' 00:00:00', new \DateTimeZone($this->defaultTz));
            // Google’s all-day end is exclusive; subtract one second or set 23:59:59
            $end   = (new \DateTimeImmutable($e->getDate() . ' 00:00:00', new \DateTimeZone($this->defaultTz)))
                        ->modify('-1 second')->setTime(23,59,59);
            return [$start, $end];
        }

        // datetime case
        $start = new \DateTimeImmutable($s->getDateTime());
        $end   = new \DateTimeImmutable($e->getDateTime());
        return [$start, $end];
    }

    private function toUtc(?string $rfc3339): ?string
    {
        if (!$rfc3339) return null;
        $d = new \DateTimeImmutable($rfc3339);
        return $d->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
