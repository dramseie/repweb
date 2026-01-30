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

    #[Route('/api/reports/tenant/{tenant}', name: 'reports_list_tenant', methods: ['GET'])]
    public function listByTenant(string $tenant): JsonResponse
    {
        $tenant = trim($tenant);
        if ($tenant === '') {
            return $this->json(['reports' => []]);
        }

        $rows = $this->db->fetchAllAssociative(
            "SELECT repid, repshort, reptitle, reptype
             FROM report
             WHERE JSON_UNQUOTE(JSON_EXTRACT(reptenant, '$.tenant')) = :tenant
             ORDER BY reptype, reptitle",
            ['tenant' => $tenant]
        );

        return $this->json(['reports' => $rows]);
    }

    #[Route('/api/report/{repid}/meta', name: 'report_meta', methods: ['GET'])]
    public function meta(int $repid): JsonResponse
    {
        $row = $this->db->fetchAssociative(
            'SELECT repid, reptitle, repdesc, repparam FROM report WHERE repid = :id',
            ['id' => $repid]
        );

        if (!$row) {
            return $this->json(['message' => 'Report not found'], 404);
        }

        return $this->json([
            'report' => $row,
        ]);
    }
}
