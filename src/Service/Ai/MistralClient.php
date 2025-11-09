<?php

namespace App\Service\Ai;

use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Lightweight helper around the Mistral chat completions API.
 */
final class MistralClient
{
    private readonly HttpClientInterface $http;
    private readonly string $apiKey;
    private readonly string $baseUrl;
    private readonly string $defaultModel;

    public function __construct(HttpClientInterface $http, string $apiKey, string $baseUrl, string $model)
    {
        $this->http = $http;
        $this->apiKey = trim($apiKey);
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->defaultModel = trim($model) ?: 'mistral-large-latest';
    }

    /**
     * Run a chat completion request and expect plain text back.
     */
    public function chat(array $messages, array $options = []): string
    {
        return $this->request($messages, $options);
    }

    /**
     * Run a chat completion request and decode the JSON returned by the model.
     *
     * @return array<string,mixed>
     */
    public function chatJson(array $messages, array $options = []): array
    {
        $options['response_format'] = $options['response_format'] ?? ['type' => 'json_object'];
        $content = $this->request($messages, $options);

        try {
            /** @var array<string,mixed> $decoded */
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException('Mistral returned invalid JSON: '.$e->getMessage(), previous: $e);
        }

        return $decoded;
    }

    private function request(array $messages, array $options): string
    {
        if ($this->apiKey === '') {
            throw new \RuntimeException('Configure MISTRAL_API_KEY before using the AI features.');
        }

        $payload = $this->buildPayload($messages, $options);
        $endpoint = $this->baseUrl.'/v1/chat/completions';

        try {
            $response = $this->http->request('POST', $endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $raw = $response->getContent(false);
            if ($raw === '') {
                throw new \RuntimeException('Mistral returned an empty HTTP body.');
            }

            $data = json_decode($raw, true);
            if (!\is_array($data)) {
                throw new \RuntimeException('Mistral returned non-JSON response: '.$raw);
            }
        } catch (TransportExceptionInterface | ClientExceptionInterface | RedirectionExceptionInterface | ServerExceptionInterface $e) {
            throw new \RuntimeException('Mistral API request failed: '.$e->getMessage(), previous: $e);
        }

        if (($data['error'] ?? null) !== null) {
            throw new \RuntimeException('Mistral API error: '.json_encode($data['error']));
        }

        $choices = $data['choices'] ?? null;
        if (!\is_array($choices) || $choices === []) {
            throw new \RuntimeException('Mistral response did not include choices: '.$raw);
        }

        $message = $choices[0]['message'] ?? null;
        if (!\is_array($message)) {
            throw new \RuntimeException('Mistral response missing message field: '.$raw);
        }

        $content = $message['content'] ?? null;
        $parsed = $this->extractContent($content);

        if ($parsed === '') {
            $type = get_debug_type($content);
            throw new \RuntimeException(sprintf('Mistral response did not contain any content (got %s): %s', $type, $raw));
        }

        return $parsed;
    }

    /**
     * @return array<string,mixed>
     */
    private function buildPayload(array $messages, array $options): array
    {
        $payload = [
            'model' => $options['model'] ?? $this->defaultModel,
            'messages' => $messages,
        ];

        $payload['temperature'] = $options['temperature'] ?? 0.2;

        if (isset($options['top_p'])) {
            $payload['top_p'] = $options['top_p'];
        }

        if (isset($options['max_output_tokens'])) {
            $payload['max_tokens'] = (int) $options['max_output_tokens'];
        } elseif (isset($options['max_tokens'])) {
            $payload['max_tokens'] = (int) $options['max_tokens'];
        }

        if (isset($options['response_format'])) {
            $payload['response_format'] = $options['response_format'];
        }

        return $payload;
    }

    private function extractContent(mixed $raw): string
    {
        if (\is_string($raw)) {
            return trim($raw);
        }

        if (\is_array($raw)) {
            $segments = [];
            foreach ($raw as $item) {
                if (\is_string($item)) {
                    $segments[] = $item;
                    continue;
                }

                if (\is_array($item)) {
                    $type = $item['type'] ?? null;
                    if ($type === 'text' && isset($item['text'])) {
                        $segments[] = (string) $item['text'];
                        continue;
                    }

                    if ($type === 'json' && isset($item['json'])) {
                        $segments[] = (string) $item['json'];
                        continue;
                    }

                    if (isset($item['content'])) {
                        $segments[] = $this->extractContent($item['content']);
                        continue;
                    }
                }
            }

            $joined = trim(implode("\n", array_map(static fn ($segment) => trim((string) $segment), array_filter($segments, static fn ($segment) => $segment !== null && $segment !== ''))));
            if ($joined !== '') {
                return $joined;
            }
        }

        return '';
    }
}
