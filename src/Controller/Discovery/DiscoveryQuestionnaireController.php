<?php

namespace App\Controller\Discovery;

use App\Service\Discovery\DiscoveryQuestionnaireRuntimeService;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/discovery/questionnaires')]
class DiscoveryQuestionnaireController extends AbstractController
{
    public function __construct(
        private readonly DiscoveryQuestionnaireRuntimeService $runtime,
    ) {
    }

    #[Route('/ci/{ciKey}', name: 'discovery_questionnaire_runtime_show', methods: ['GET'], requirements: ['ciKey' => '[A-Za-z0-9_.:-]+'])]
    public function show(string $ciKey, Request $request): JsonResponse
    {
        $questionnaireId = $request->query->getInt('questionnaire_id', 0) ?: null;
        $payload = $this->runtime->load($ciKey, $questionnaireId);
        return $this->json($payload);
    }

    #[Route('/ci/{ciKey}', name: 'discovery_questionnaire_runtime_save', methods: ['POST'], requirements: ['ciKey' => '[A-Za-z0-9_.:-]+'])]
    public function save(string $ciKey, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent() ?: '[]', true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return $this->json(['error' => 'Invalid JSON payload'], 400);
        }

        $answers = $data['answers'] ?? [];
        if (!is_array($answers)) {
            return $this->json(['error' => 'answers must be an array'], 400);
        }

        $status = (string) ($data['status'] ?? 'in_progress');
        $questionnaireId = $request->query->getInt('questionnaire_id', 0) ?: null;
        $payload = $this->runtime->save($ciKey, $answers, $status, $questionnaireId);
        return $this->json($payload);
    }
}
