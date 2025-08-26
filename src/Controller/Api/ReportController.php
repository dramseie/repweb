<?php
namespace App\Controller\Api;

use App\Entity\Report;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class ReportController extends AbstractController
{
    #[Route('/api/reports', name: 'api_reports', methods: ['GET'])]
    public function listReports(EntityManagerInterface $em): JsonResponse
    {
        $reports = $em->getRepository(Report::class)->findAll();
        
        $data = array_map(function (Report $r) {
            return [
                'repid'    => $r->getRepid(),
                'reptype'  => $r->getReptype(),
                'repshort' => $r->getRepshort(),
                'reptitle' => $r->getReptitle(),
                'repdesc'  => $r->getRepdesc(),
                'repsql'   => $r->getRepsql(),
                'repparam' => $r->getRepparam(),
                'repowner' => $r->getRepowner(),
                'repts'    => $r->getRepts()?->format('Y-m-d H:i:s'),
            ];
        }, $reports);

        return $this->json($data);
    }
}
