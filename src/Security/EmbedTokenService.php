<?php

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

class EmbedTokenService
{
    public const COOKIE_NAME = 'smartsheet_embed';
    public const AUDIENCE_RAMSEIER = 'https://www.ramseier.com';
    public const SCOPE_SMARTSHEET = 'smartsheet:read';

    private string $secret;

    public function __construct(
        #[Autowire('%env(EMBED_TOKEN_SECRET)%')]
        string $secret
    ) {
        $secret = trim($secret);
        if ($secret === '') {
            throw new \RuntimeException('EMBED_TOKEN_SECRET is not configured.');
        }
        $this->secret = $secret;
    }

    /**
     * @return array{token: string, expiresAt: string, exp: int}
     */
    public function createToken(string $audience, string $scope, int $expiresInSeconds): array
    {
        $expiresInSeconds = max(60, min($expiresInSeconds, 900));
        $exp = time() + $expiresInSeconds;

        $payload = [
            'sub' => 'embed',
            'aud' => $audience,
            'scope' => $scope,
            'iss' => 'repweb',
            'exp' => $exp,
        ];

        $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($payloadJson === false) {
            throw new \RuntimeException('Failed to encode embed token payload.');
        }

        $payloadB64 = $this->base64UrlEncode($payloadJson);
        $signature = hash_hmac('sha256', $payloadB64, $this->secret, true);
        $signatureB64 = $this->base64UrlEncode($signature);

        return [
            'token' => $payloadB64 . '.' . $signatureB64,
            'expiresAt' => gmdate('c', $exp),
            'exp' => $exp,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function validateToken(string $token, string $audience, string $scope): ?array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$payloadB64, $signatureB64] = $parts;
        $payloadJson = $this->base64UrlDecode($payloadB64);
        if ($payloadJson === null) {
            return null;
        }

        $expectedSignature = hash_hmac('sha256', $payloadB64, $this->secret, true);
        $expectedSignatureB64 = $this->base64UrlEncode($expectedSignature);
        if (!hash_equals($expectedSignatureB64, $signatureB64)) {
            return null;
        }

        try {
            $payload = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        if (!is_array($payload)) {
            return null;
        }

        if (!isset($payload['exp']) || !is_numeric($payload['exp'])) {
            return null;
        }

        if ((int) $payload['exp'] < time()) {
            return null;
        }

        if (($payload['aud'] ?? null) !== $audience) {
            return null;
        }

        if (($payload['scope'] ?? null) !== $scope) {
            return null;
        }

        return $payload;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): ?string
    {
        $remainder = strlen($value) % 4;
        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode(strtr($value, '-_', '+/'));
        return $decoded === false ? null : $decoded;
    }
}
