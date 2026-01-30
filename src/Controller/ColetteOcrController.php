<?php

namespace App\Controller;

use App\Service\ColetteOcrService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ColetteOcrController extends AbstractController
{
    #[Route('/colette/ocr', name: 'app_colette_ocr', methods: ['POST'])]
    public function __invoke(Request $request, ColetteOcrService $ocrService): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent() ?: '[]', true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->json(['message' => 'JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        if (!isset($payload['imageData']) || !\is_string($payload['imageData'])) {
            return $this->json(['message' => 'Image non fournie.'], Response::HTTP_BAD_REQUEST);
        }

        $mimeType = $payload['mimeType'] ?? null;

        try {
            $text = $ocrService->extractText($payload['imageData'], \is_string($mimeType) ? $mimeType : null);
        } catch (\Throwable $throwable) {
            return $this->json([
                'message' => $throwable->getMessage(),
                'text' => '',
            ]);
        }

        if ($text === '') {
            return $this->json([
                'message' => 'Aucun texte détecté.',
                'text' => '',
            ]);
        }

        return $this->json([
            'text' => $text,
        ]);
    }
}
