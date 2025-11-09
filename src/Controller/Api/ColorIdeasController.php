<?php

namespace App\Controller\Api;

use App\Service\Ai\MistralClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/ai/color-ideas', name: 'api_ai_color_ideas', methods: ['POST'])]
final class ColorIdeasController extends AbstractController
{
    public function __invoke(Request $request, MistralClient $client): JsonResponse
    {
        $payload = $request->getContent();
        $decoded = [];

        if ($payload !== '') {
            $decoded = json_decode($payload, true);
            if (!\is_array($decoded)) {
                return $this->json([
                    'error' => 'Invalid JSON payload.',
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        $baseColor = isset($decoded['baseColor']) ? trim((string) $decoded['baseColor']) : '';
        $palette = [];
        if (!empty($decoded['palette']) && \is_array($decoded['palette'])) {
            $palette = array_values(array_filter(array_map(static fn ($item) => is_string($item) ? trim($item) : '', $decoded['palette']), static fn ($item) => $item !== ''));
        }

        if ($baseColor === '') {
            return $this->json([
                'error' => 'Provide a baseColor value.',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Normalise HEX for the prompt (strip leading #, uppercase).
        $normaliseHex = static function (string $value): string {
            $value = strtoupper(ltrim($value, '#'));
            if (preg_match('/^[0-9A-F]{3}$|^[0-9A-F]{6}$/', $value)) {
                return '#'.$value;
            }

            return $value;
        };

        $baseColorHex = $normaliseHex($baseColor);
        $paletteHex = array_map($normaliseHex, $palette);

        $messages = [
            [
                'role' => 'system',
                'content' => 'Tu es une assistante design pour un salon de beauté. Propose des idées brèves et concrètes en français pour utiliser la couleur capturée (onglerie, pédicure, ambiance, merchandising).
Réponds UNIQUEMENT en JSON avec la structure suivante: {"suggestions": [{"idea": "texte en français", "color": "#HEX"}, ...]}.
Chaque idée ≤ 120 caractères, sans listes à puces ni code. La clé "color" doit être l\'une des couleurs fournies (base ou palette) ou, à défaut, la couleur de base.',
            ],
            [
                'role' => 'user',
                'content' => json_encode([
                    'base_color' => $baseColorHex,
                    'palette' => $paletteHex,
                ], JSON_THROW_ON_ERROR),
            ],
        ];

        try {
            $result = $client->chatJson($messages, [
                'temperature' => 0.6,
                'top_p' => 0.95,
                'max_output_tokens' => 400,
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        $availableColors = $paletteHex;
        array_unshift($availableColors, $baseColorHex);
        $availableColors = array_values(array_unique($availableColors));

        $suggestions = [];
        if (isset($result['suggestions']) && \is_array($result['suggestions'])) {
            foreach ($result['suggestions'] as $item) {
                if (is_string($item)) {
                    $suggestions[] = [
                        'idea' => trim($item),
                        'color' => $availableColors[0] ?? $baseColorHex,
                    ];
                    continue;
                }

                if (\is_array($item)) {
                    $idea = isset($item['idea']) ? trim((string) $item['idea']) : (isset($item['texte']) ? trim((string) $item['texte']) : '');
                    if ($idea === '') {
                        continue;
                    }

                    $color = '';
                    if (isset($item['color'])) {
                        $color = $normaliseHex((string) $item['color']);
                    } elseif (isset($item['couleur'])) {
                        $color = $normaliseHex((string) $item['couleur']);
                    }

                    if (!in_array($color, $availableColors, true)) {
                        $color = $availableColors[0] ?? $baseColorHex;
                    }

                    $suggestions[] = [
                        'idea' => $idea,
                        'color' => $color,
                    ];
                }
            }
        }

        if ($suggestions === []) {
            $message = isset($result['message']) && is_string($result['message']) ? trim($result['message']) : null;
            if ($message !== null && $message !== '') {
                $suggestions[] = [
                    'idea' => $message,
                    'color' => $availableColors[0] ?? $baseColorHex,
                ];
            }
        }

        if ($suggestions === []) {
            $suggestions[] = [
                'idea' => 'Aucune suggestion générée. Essayez d\'ajouter d\'autres teintes ou de recapturer la couleur.',
                'color' => $availableColors[0] ?? $baseColorHex,
            ];
        }

        return $this->json([
            'suggestions' => $suggestions,
            'baseColor' => $baseColorHex,
            'palette' => $paletteHex,
        ]);
    }
}
