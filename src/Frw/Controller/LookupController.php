<?php
namespace App\Frw\Controller;

use App\Frw\Service\LookupService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

#[Route('/lookups', name: 'frw_lookups_')]
class LookupController extends AbstractController
{
    public function __construct(private LookupService $svc) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $req): JsonResponse
    {
        $type = $req->query->get('type'); $q = $req->query->get('q');
        return $this->json($this->svc->get($type, $q));
    }
}
