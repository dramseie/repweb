<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ToolsController extends AbstractController
{
    #[Route('/tools/datatables', name: 'tools_datatables', methods: ['GET'])]
    public function datatables(): Response
    {
        return $this->render('tools/datatables.html.twig');
    }

    // New: JSON Import Query Builder page at /tools/jsonimport
    #[Route('/tools/jsonimport', name: 'tools_jsonimport', methods: ['GET'])]
    public function jsonImport(): Response
    {
        return $this->render('tools/json_import.html.twig', [
            'title' => 'JSON Import Query Builder',
        ]);
    }
}
