<?php
namespace App\Service;

use Doctrine\DBAL\Connection;
use Google\Client;
use Google\Service\Calendar;

final class GoogleCalendarService
{
    public function __construct(
        private string $appUrl,
        private string $clientId,
        private string $clientSecret,
        private string $tz,
        private Connection $db,
    ) {}

    private function newClient(): Client
    {
        $client = new Client();
        $client->setClientId($this->clientId);
        $client->setClientSecret($this->clientSecret);
        $client->setRedirectUri(rtrim($this->appUrl, '/') . '/api/google/oauth/callback');
        $client->setAccessType('offline');      // get refresh_token
        $client->setPrompt('consent');          // force refresh_token on first connect
        $client->setScopes([Calendar::CALENDAR]);
        return $client;
    }

    public function buildAuthUrl(int $userId): string
    {
        $client = $this->newClient();
        $client->setState((string)$userId); // round-trip the user id
        return $client->createAuthUrl();
    }

    public function exchangeCodeForToken(string $code): array
    {
        $client = $this->newClient();
        $token = $client->fetchAccessTokenWithAuthCode($code);
        if (isset($token['error'])) {
            throw new \RuntimeException('Google OAuth error: ' . $token['error']);
        }
        return $token;
    }

    /**
     * NEW: controller-friendly helper that exchanges the code and stores tokens.
     */
    public function exchangeCodeAndStore(string $code, int $userId): void
    {
        $token = $this->exchangeCodeForToken($code);
        $this->storeTokenRow($userId, $token);
    }

    /**
     * Persist/Update tokens.
     */
    private function storeTokenRow(int $userId, array $token): void
    {
        $this->db->executeStatement(
            "INSERT INTO google_oauth_tokens (user_id, access_token, refresh_token, expires_at, scope, token_type)
             VALUES (:uid, :acc, :ref, :exp, :s, :t)
             ON DUPLICATE KEY UPDATE
               access_token = VALUES(access_token),
               refresh_token = IFNULL(VALUES(refresh_token), refresh_token),
               expires_at = VALUES(expires_at),
               scope = VALUES(scope),
               token_type = VALUES(token_type)",
            [
                'uid' => $userId,
                'acc' => json_encode($token),
                'ref' => $token['refresh_token'] ?? null,
                'exp' => time() + (int)($token['expires_in'] ?? 0),
                's'   => $token['scope'] ?? null,
                't'   => $token['token_type'] ?? null,
            ]
        );
    }

    private function withAuthorizedClient(int $userId): array
    {
        $row = $this->db->fetchAssociative("SELECT * FROM google_oauth_tokens WHERE user_id = ?", [$userId]);
        if (!$row) {
            throw new \RuntimeException('Google not connected for user ' . $userId);
        }

        $client = $this->newClient();
        $token  = json_decode($row['access_token'], true) ?: [];
        $client->setAccessToken($token);

        if ($client->isAccessTokenExpired()) {
            if (empty($row['refresh_token'])) {
                throw new \RuntimeException('No refresh token stored');
            }
            $client->fetchAccessTokenWithRefreshToken($row['refresh_token']);
            $newToken = $client->getAccessToken();

            $this->db->update('google_oauth_tokens', [
                'access_token' => json_encode($newToken),
                'expires_at'   => time() + (int)($newToken['expires_in'] ?? 0),
            ], ['id' => $row['id']]);
        }

        return [$client, new Calendar($client)];
    }

    // === Basic CRUD on events ===

    public function upsertEvent(
        int $userId,
        string $calendarId,           // 'primary' is fine
        string $eventIdOrEmpty,       // our own mapping if we have one; else ''
        string $summary,
        \DateTimeInterface $start,
        \DateTimeInterface $end,
        ?string $description = null
    ): string {
        [, $svc] = $this->withAuthorizedClient($userId);

        $eventData = [
            'summary'     => $summary,
            'description' => $description,
            'start'       => ['dateTime' => $start->format(\DateTimeInterface::RFC3339), 'timeZone' => $this->tz],
            'end'         => ['dateTime' => $end->format(\DateTimeInterface::RFC3339), 'timeZone' => $this->tz],
        ];

        if ($eventIdOrEmpty) {
            $event = $svc->events->get($calendarId, $eventIdOrEmpty);
            foreach ($eventData as $k => $v) {
                $event->$k = $v;
            }
            $updated = $svc->events->update($calendarId, $event->getId(), $event);
            return $updated->getId();
        }

        $event = new \Google\Service\Calendar\Event($eventData);
        $created = $svc->events->insert($calendarId, $event, ['sendUpdates' => 'none']);
        return $created->getId();
    }

    public function deleteEvent(int $userId, string $calendarId, string $eventId): void
    {
        [, $svc] = $this->withAuthorizedClient($userId);
        $svc->events->delete($calendarId, $eventId);
    }
	

	public function authorized(int $userId): array
	{
		// same logic as your private withAuthorizedClient(), but public and returning [Client, Calendar]
		$row = $this->db->fetchAssociative("SELECT * FROM google_oauth_tokens WHERE user_id=?", [$userId]);
		if (!$row) throw new \RuntimeException('Google not connected for user ' . $userId);

		$client = $this->newClient();
		$token  = json_decode($row['access_token'], true) ?: [];
		$client->setAccessToken($token);

		if ($client->isAccessTokenExpired()) {
			if (empty($row['refresh_token'])) throw new \RuntimeException('No refresh token stored');
			$client->fetchAccessTokenWithRefreshToken($row['refresh_token']);
			$newToken = $client->getAccessToken();
			$this->db->update('google_oauth_tokens', [
				'access_token' => json_encode($newToken),
				'expires_at'   => time() + (int)($newToken['expires_in'] ?? 0),
			], ['id' => $row['id']]);
		}
		return [$client, new \Google\Service\Calendar($client)];
	}
	
}
