<?php

namespace App\Controller\Api;

use App\Service\Ai\DependencyReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/ai/dependency-report', name: 'api_ai_dependency_report', methods: ['POST'])]
final class AiDependencyReportController extends AbstractController
{
    public function __invoke(Request $request, DependencyReportService $service): JsonResponse
    {
        $content = $request->getContent() ?: '{}';
        $decoded = json_decode($content, true);

        if (!\is_array($decoded)) {
            return $this->json([
                'error' => 'Invalid JSON payload.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $question = isset($decoded['question']) ? trim((string) $decoded['question']) : '';
        if ($question === '') {
            return $this->json([
                'error' => 'Provide a non-empty "question" field.',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $service->generateReport($question);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return $this->json([
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $e) {
            return $this->json([
                'error' => 'Unexpected error: '.$e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json($result);
    }
}
