<?php
namespace App\Frw\Controller;

use App\Frw\Service\RunService;
use App\Frw\Service\PricingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

#[Route('/runs', name: 'frw_runs_')]
class RunController extends AbstractController
{
    public function __construct(private RunService $runs, private PricingService $pricing) {}

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $req): JsonResponse
    {
        $code = $req->toArray()['templateCode'] ?? null;
        if (!$code) return $this->json(['error' => 'templateCode is required'], 400);
        return $this->json($this->runs->createRun($code, $this->getUser()), 201);
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function getOne(int $id): JsonResponse
    { try { return $this->json($this->runs->get($id)); } catch (\Throwable) { return $this->json(['error'=>'Run not found'], 404); } }

    #[Route('/{id}', name: 'patch', methods: ['PATCH'])]
    public function patch(int $id, Request $req): JsonResponse
    { return $this->json($this->runs->patchAnswers($id, $req->toArray()['answers'] ?? [])); }

    #[Route('/{id}/price', name: 'price', methods: ['POST'])]
    public function price(int $id): JsonResponse
    { return $this->json($this->pricing->priceRun($id)); }

    #[Route('/{id}/submit', name: 'submit', methods: ['POST'])]
    public function submit(int $id): JsonResponse
    { return $this->json($this->runs->submit($id, $this->getUser())); }
}