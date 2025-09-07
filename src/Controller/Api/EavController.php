<?php
// File: src/Controller/Api/EavController.php
namespace App\Controller\Api;

use App\Service\Eav\ExtEavPivotService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/eav', name: 'api_eav_')]
class EavController extends AbstractController
{
    public function __construct(private ExtEavPivotService $svc) {}

    // --- Meta discovery endpoints for dropdowns ---
    #[Route('/meta/tenants', name: 'tenants', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function tenants(): Response
    {
        return $this->json($this->svc->listTenants());
    }

    #[Route('/meta/{tenant}/types', name: 'types', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function types(string $tenant): Response
    {
        return $this->json($this->svc->listEntityTypes($tenant));
    }

    // Column metadata for a given tenant/entityType
    #[Route('/{tenant}/{entityType}/columns', name: 'columns', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function columns(string $tenant, string $entityType): Response
    {
        return $this->json($this->svc->listAttributes($tenant, $entityType));
    }

    // Pivoted rows with optional search/limit/offset
    #[Route('/{tenant}/{entityType}/rows', name: 'rows', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function rows(string $tenant, string $entityType, Request $r): Response
    {
        $limit  = (int)($r->query->get('limit') ?? 200);
        $offset = (int)($r->query->get('offset') ?? 0);
        $search = $r->query->get('search') ?: null;
        return $this->json($this->svc->fetchRows($tenant, $entityType, $limit, $offset, $search));
    }

    // Apply edits (inline cell edits grouped per CI)
    #[Route('/{tenant}/{entityType}', name: 'patch', methods: ['PATCH'])]
    #[IsGranted('ROLE_USER')] // was ROLE_EDITOR
    public function patch(string $tenant, string $entityType, Request $r): Response
    {
        $payload = json_decode($r->getContent() ?: '[]', true) ?? [];
        $changes = $payload['changes'] ?? $payload; // allow direct array

        $who = method_exists($this->getUser() ?? null, 'getUserIdentifier')
            ? $this->getUser()->getUserIdentifier()
            : 'repweb';

        return $this->json($this->svc->applyChanges($tenant, $entityType, $changes, $who));
    }
}
