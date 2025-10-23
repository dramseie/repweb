<?php
namespace App\Controller\Ui;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PsrPageController extends AbstractController
{
    #[Route('/psr/projects', name: 'psr_ui_list', methods: ['GET'])]
    public function list(): Response {
        return $this->render('psr/list.html.twig');
    }

    #[Route('/psr/projects/{id}', name: 'psr_ui_detail', methods: ['GET'])]
    public function detail(string $id): Response {
        return $this->render('psr/detail.html.twig', ['id'=>$id]);
    }

    #[Route('/psr/compare', name: 'psr_ui_compare', methods: ['GET'])]
    public function compare(): Response {
        return $this->render('psr/compare.html.twig');
    }
}
