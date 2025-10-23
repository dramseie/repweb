<?php
namespace App\Mig\Controller;

use App\Mig\Http\ApiResponse;
use App\Mig\Service\FeatureToggle;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/mig/windows')]
class WindowController extends AbstractController
{
    public function __construct(private readonly FeatureToggle $ft) {}

    #[Route('', name: 'mig_windows_list', methods: ['GET'])]
    public function list(): \Symfony\Component\HttpFoundation\JsonResponse
    {
        if (!$this->ft->mig()) return ApiResponse::err('Migration Manager disabled', 503);
        $now = new \DateTimeImmutable('next saturday 00:00');
        $w1s = $now->format(DATE_ATOM);
        $w1e = $now->modify('+2 days')->format(DATE_ATOM);
        $w2s = $now->modify('+7 days')->format(DATE_ATOM);
        $w2e = $now->modify('+9 days')->format(DATE_ATOM);

        return ApiResponse::ok([
            'items' => [
                ['id'=>1,'program_id'=>1,'kind'=>'Maintenance','starts_at'=>$w1s,'ends_at'=>$w1e,'capacity_total'=>5,'capacity_used'=>2,'resource_matrix'=>['DBA'=>2,'Network'=>1]],
                ['id'=>2,'program_id'=>1,'kind'=>'Maintenance','starts_at'=>$w2s,'ends_at'=>$w2e,'capacity_total'=>5,'capacity_used'=>4,'resource_matrix'=>['DBA'=>1,'Network'=>1]],
            ]
        ]);
    }

    #[Route('', name: 'mig_windows_create', methods: ['POST'])]
    public function create(Request $req): \Symfony\Component\HttpFoundation\JsonResponse
    {
        if (!$this->ft->mig()) return ApiResponse::err('Migration Manager disabled', 503);
        $payload = json_decode($req->getContent() ?: '{}', true);
        return ApiResponse::ok(['received'=>$payload,'note'=>'Apply SQL later; this is a stub create.']);
    }
}
