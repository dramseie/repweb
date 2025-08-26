<?php
// src/Controller/ReportListController.php
namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class ReportListController extends AbstractController
{
    public function __construct(private Connection $db) {}

    #[Route('/api/reports', name: 'reports_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $rows = $this->db->fetchAllAssociative("
            SELECT repid, repshort, reptitle, reptype
            FROM report
            ORDER BY reptype, reptitle
        ");
        return $this->json(['reports' => $rows]);
    }
}
