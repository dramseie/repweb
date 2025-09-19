<?php
namespace App\Controller;

use App\Repository\RestConnectorRepository;
use App\Repository\RestEndpointRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class RestApiExplorerController extends AbstractController
{
    #[Route('/rest-explorer', name: 'rest_explorer')]
    public function index(
        RestConnectorRepository $connRepo,
        RestEndpointRepository $epRepo
    ): Response {
        $connectors = $connRepo->findBy([], ['name' => 'ASC']);
        $endpoints  = $epRepo->findBy([], ['id' => 'DESC']);

        return $this->render('rest_explorer/index.html.twig', [
            'connectors' => $connectors,
            'endpoints'  => $endpoints,
        ]);
    }
}
