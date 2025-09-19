<?php
// src/Controller/QuoteController.php
namespace App\Controller;

use App\Service\QuoteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

final class QuoteController extends AbstractController
{
    public function __construct(private QuoteService $svc) {}

    #[Route('/api/catalog/quote', methods: ['POST'])]
    public function quote(Request $r): JsonResponse
    {
        $req = $r->toArray(false);
        return $this->json($this->svc->quote($req));
    }
}
