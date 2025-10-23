<?php
namespace App\Mig\Controller;

use App\Mig\Http\ApiResponse;
use App\Mig\Service\FeatureToggle;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/mig/reports')]
class ReportController extends AbstractController
{
    public function __construct(private readonly FeatureToggle $ft = new FeatureToggle()) {}

    #[Route('/kpis', name: 'mig_report_kpis', methods: ['GET'])]
    public function kpis()
    {
        if (!$this->ft->mig()) return ApiResponse::err('Migration Manager disabled', 503);
			return ApiResponse::ok([
				'completion_rate' => ['value'=>0.74, 'unit'=>'ratio'],
				'rollback_rate'   => ['value'=>0.023, 'unit'=>'ratio'],
				'data_confidence' => ['value'=>0.92, 'unit'=>'ratio'],
			]);

    }
}
