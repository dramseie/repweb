<?php
namespace App\Mig\Controller;

use App\Mig\Http\ApiResponse;
use App\Mig\Service\FeatureToggle;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/mig/approvals')]
class ApprovalController extends AbstractController
{
    public function __construct(private readonly FeatureToggle $ft = new FeatureToggle()) {}

    #[Route('', name: 'mig_approval_request', methods: ['POST'])]
    public function requestApproval(Request $req)
    {
        if (!$this->ft->mig()) return ApiResponse::err('Migration Manager disabled', 503);
        $payload = json_decode($req->getContent() ?: '{}', true);
        return ApiResponse::ok(['received' => $payload, 'note' => 'Approvals recorded once DB schema exists.']);
    }

    #[Route('/{id}', name: 'mig_approval_decide', methods: ['PATCH'])]
    public function decide(string $id, Request $req)
    {
        if (!$this->ft->mig()) return ApiResponse::err('Migration Manager disabled', 503);
        $payload = json_decode($req->getContent() ?: '{}', true);
        return ApiResponse::ok(['id' => $id, 'decision' => $payload]);
    }
}
