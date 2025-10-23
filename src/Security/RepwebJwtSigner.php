<?php

namespace App\Security;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class RepwebJwtSigner
{
    public function __construct(private string $privateKeyPath = '/etc/repweb/grafana-jwt-private.pem') {}

    public function mint(array $claims, int $ttlSeconds = 300): string
    {
        $now = time();
        $payload = array_merge($claims, [
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $ttlSeconds,
        ]);

        $privateKey = file_get_contents($this->privateKeyPath);
        return JWT::encode($payload, $privateKey, 'RS256');
    }
}
