<?php

declare(strict_types=1);

namespace App\Controller\Tools;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/tools/task-dependencies')]
class TaskDependenciesController extends AbstractController
{
    #[Route('', name: 'tools_task_dependencies', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('tools/task_dependencies.html.twig');
    }
}
