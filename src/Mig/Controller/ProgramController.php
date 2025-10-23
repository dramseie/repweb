<?php
namespace App\Mig\Controller;

use App\Mig\Http\ApiResponse;
use App\Mig\Service\FeatureToggle;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/mig/programs')]
class ProgramController extends AbstractController
{
    public function __construct(private readonly FeatureToggle $ft = new FeatureToggle()) {}

    #[Route('', name: 'mig_program_list', methods: ['GET'])]
    public function list(): \Symfony\Component\HttpFoundation\JsonResponse
    {
        if (!$this->ft->mig()) return ApiResponse::err('Migration Manager disabled', 503);
        return ApiResponse::ok(['items' => []]);
    }

    #[Route('', name: 'mig_program_create', methods: ['POST'])]
    public function create(Request $req): \Symfony\Component\HttpFoundation\JsonResponse
    {
        if (!$this->ft->mig()) return ApiResponse::err('Migration Manager disabled', 503);
        $payload = json_decode($req->getContent() ?: '{}', true);
        return ApiResponse::ok(['received' => $payload, 'note' => 'Apply SQL, then implement persistence.']);
    }
}
