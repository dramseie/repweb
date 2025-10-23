<?php
namespace App\Mig\Controller;

use App\Mig\Http\ApiResponse;
use App\Mig\Service\FeatureToggle;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/mig/checklists')]
class ChecklistController extends AbstractController
{
    public function __construct(private readonly FeatureToggle $ft = new FeatureToggle()) {}

    #[Route('', name: 'mig_checklist_add', methods: ['POST'])]
    public function add(Request $req)
    {
        if (!$this->ft->mig()) return ApiResponse::err('Migration Manager disabled', 503);
        $payload = json_decode($req->getContent() ?: '{}', true);
        return ApiResponse::ok(['received' => $payload]);
    }

    #[Route('/{id}', name: 'mig_checklist_update', methods: ['PATCH'])]
    public function update(string $id, Request $req)
    {
        if (!$this->ft->mig()) return ApiResponse::err('Migration Manager disabled', 503);
        $payload = json_decode($req->getContent() ?: '{}', true);
        return ApiResponse::ok(['id'=> $id,'patched'=> $payload]);
    }
}
