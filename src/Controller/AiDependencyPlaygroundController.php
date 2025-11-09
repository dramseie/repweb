<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class AiDependencyPlaygroundController extends AbstractController
{
    #[Route('/tools/ai/dependency-report', name: 'ai_dependency_playground', methods: ['GET'])]
    public function __invoke(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        return $this->render('tools/ai_dependency_report.html.twig');
    }
}
