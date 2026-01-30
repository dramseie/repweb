<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class IssuePageController extends AbstractController
{
    #[Route('/issues', name: 'app_issue_tracker')]
    public function index(): Response
    {
        return $this->render('issues/index.html.twig');
    }
}
