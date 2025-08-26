<?php
namespace App\Controller;

use App\Entity\Report;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PlotlyPageController extends AbstractController
{
    #[Route('/plotly/{id}', name: 'plotly_page', methods: ['GET'])]
    public function show(Report $report): Response
    {
        // Optionally require login at controller level:
        // $this->denyAccessUnlessGranted('ROLE_USER');

        return $this->render('report/plotly.html.twig', [
            'report' => $report,
        ]);
    }
}
