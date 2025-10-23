<?php
namespace App\Service;

use Firebase\JWT\JWT;

class GrafanaJwtService
{
    private string $privateKey;

    public function __construct(string $privateKeyPath)
    {
        $this->privateKey = file_get_contents($privateKeyPath);
    }

    public function createToken(string $username, string $email, array $roles = []): string
    {
        $now = time();

        $payload = [
            'sub'   => $username,
            'email' => $email,
            'roles' => $roles,
            'iss'   => 'repweb',          // optional: match Grafana expect_claims
            'aud'   => 'grafana',
            'iat'   => $now,
            'exp'   => $now + 3600        // 1h validity
        ];

        return JWT::encode($payload, $this->privateKey, 'RS256');
    }
}
