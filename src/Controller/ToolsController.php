<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ToolsController extends AbstractController
{
    #[Route('/tools/datatables', name: 'tools_datatables')]
    public function datatables(): Response
    {
        return $this->render('tools/datatables.html.twig');
    }
}
