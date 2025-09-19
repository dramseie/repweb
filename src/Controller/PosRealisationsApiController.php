<?php
// src/Controller/PosRealisationsApiController.php
namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class PosRealisationsApiController extends AbstractController
{
    #[Route('/api/pos/realisations', name: 'pos_realisations', methods: ['GET'])]
    public function list(Connection $db): JsonResponse
    {
        $rows = $db->fetchAllAssociative(
            "SELECT code, label
             FROM pos_realisation
             WHERE enabled = 1
             ORDER BY sort_order ASC, label ASC"
        );
        // shape: [{code, label}, ...]
        return $this->json(['items' => $rows]);
    }
}
