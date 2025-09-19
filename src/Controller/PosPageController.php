<?php
namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PosPageController extends AbstractController
{
    public function __construct(private Connection $conn) {}

    #[Route('/pos', name: 'pos_page', methods: ['GET'])]
    public function index(): Response
    {
        // Read from ongleri.pos_realisation (enabled only)
        $realisations = $this->conn->fetchAllAssociative(
            "SELECT code, label, sort_order, colour_code, color_hex
               FROM ongleri.pos_realisation
              WHERE enabled = 1
              ORDER BY sort_order ASC"
        );

        return $this->render('pos/index.html.twig', [
            'pos_realisations' => $realisations, // array of ['code'=>..., 'label'=>...]
        ]);
    }
}
