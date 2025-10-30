<?php

namespace App\Controller\Discovery;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DiscoveryPageController extends AbstractController
{
    #[Route('/discovery', name: 'app_discovery_manager')]
    public function index(): Response
    {
        return $this->render('discovery/manager.html.twig');
    }
}
