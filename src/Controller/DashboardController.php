<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

class DashboardController extends AbstractController
{
    #[Route('/reports/dashboard', name: 'reports_dashboard', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('reports/dashboard.html.twig');
    }
}
