<?php
namespace App\Controller\Ui;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PsrPresentController extends AbstractController
{
    #[Route('/psr/projects/{id}/present', name: 'psr_ui_present', methods: ['GET'])]
    public function present(string $id): Response {
        return $this->render('psr/present.html.twig', ['id'=>$id]);
    }
}
