<?php
namespace App\Frw\Controller;

use App\Frw\Service\TemplateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/templates', name: 'frw_templates_')]
class TemplateController extends AbstractController
{
    public function __construct(private TemplateService $tpls) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse { return $this->json($this->tpls->listActive()); }

    #[Route('/{code}', name: 'get', methods: ['GET'])]
    public function getOne(string $code): JsonResponse { return $this->json($this->tpls->findByCode($code)); }

    // Do NOT put a Route attribute here; it's wired via frw_pages in routes.yaml
    public function wizardPage(string $templateCode): Response
    { return $this->render('frw/wizard.html.twig', ['templateCode' => $templateCode]); }
}