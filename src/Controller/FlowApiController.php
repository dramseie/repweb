<?php
namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/flows', name: 'api_flows_')]
class FlowApiController extends AbstractController
{
    public function __construct(private Connection $conn) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $rows = $this->conn->fetchAllAssociative(
            "SELECT id, name, created_at, updated_at FROM flows ORDER BY updated_at DESC, created_at DESC"
        );
        return $this->json($rows);
    }

    #[Route('/{id<\\d+>}', name: 'read', methods: ['GET'])]
    public function read(int $id): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $row = $this->conn->fetchAssociative("SELECT id, name, json FROM flows WHERE id = ?", [$id]);
        if (!$row) return $this->json(['error' => 'Not found'], 404);
        return new JsonResponse(json_decode($row['json'], true) + ['id' => (int)$row['id'], 'name' => $row['name']]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $req): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $payload = json_decode($req->getContent(), true) ?: [];
        $name = $payload['name'] ?? 'New Flow';
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $this->conn->insert('flows', [
            'name' => $name,
            'json' => $json,
            'owner_email' => $this->getUser()?->getUserIdentifier(),
        ]);
        return $this->json(['id' => (int)$this->conn->lastInsertId()], 201);
    }

    #[Route('/{id<\\d+>}', name: 'update', methods: ['PUT','PATCH'])]
    public function update(int $id, Request $req): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $payload = json_decode($req->getContent(), true) ?: [];
        $name = $payload['name'] ?? 'Flow';
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $n = $this->conn->update('flows', ['name' => $name, 'json' => $json], ['id' => $id]);
        if (!$n) return $this->json(['error' => 'Not found'], 404);
        return $this->json(['ok' => true]);
    }
}
