<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SmartsheetController extends AbstractController
{
    #[Route('/smartsheet', name: 'smartsheet_dashboard', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('smartsheet/index.html.twig');
    }
}
