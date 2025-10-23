<?php
namespace App\Mig\Controller;

use App\Mig\Http\ApiResponse;
use App\Mig\Service\FeatureToggle;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/mig/migrations')]
class MigrationController extends AbstractController
{
    public function __construct(private readonly FeatureToggle $ft = new FeatureToggle()) {}

    #[Route('/{id}', name: 'mig_get', methods: ['GET'])]
    public function getOne(string $id): \Symfony\Component\HttpFoundation\JsonResponse
    {
        if (!$this->ft->mig()) return ApiResponse::err('Migration Manager disabled', 503);
        return ApiResponse::ok([
            'id' => $id,
            'state' => 'Draft',
            'title' => 'Placeholder until DB is applied'
        ]);
    }

    #[Route('/{id}', name: 'mig_patch', methods: ['PATCH'])]
    public function patch(string $id, Request $req): \Symfony\Component\HttpFoundation\JsonResponse
    {
        if (!$this->ft->mig()) return ApiResponse::err('Migration Manager disabled', 503);
        $payload = json_decode($req->getContent() ?: '{}', true);
        return ApiResponse::ok(['id' => $id, 'patched' => $payload, 'note' => 'No DB writes until SQL applied.']);
    }
}
