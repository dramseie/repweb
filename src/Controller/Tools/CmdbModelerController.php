<?php
namespace App\Controller\Tools;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/tools/cmdb-modeler')]
class CmdbModelerController extends AbstractController
{
    #[Route('', name: 'tools_cmdb_modeler', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('tools/cmdb_modeler.html.twig');
    }
}
