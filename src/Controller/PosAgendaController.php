<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PosAgendaController extends AbstractController
{
    #[Route('/pos/agenda', name: 'pos_agenda', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('pos/agenda.html.twig');
    }
}
