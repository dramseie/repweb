<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PosClientController extends AbstractController
{
    #[Route('/pos/client', name: 'pos_client', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('pos/client.html.twig');
    }
}
