<?php
declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class QwPageController extends AbstractController
{
    #[Route('/qw/builder', name: 'qw_builder_root')]
    public function index(): Response
    {
        // redirect to a default questionnaire id (1) so the builder is reachable
        return $this->redirectToRoute('qw_builder', ['id' => 1]);
    }

    #[Route('/qw/builder/{id}', name: 'qw_builder')]
    public function builder(int $id): Response
    {
        return $this->render('qw/builder.html.twig', [
            'qid' => $id,
        ]);
    }
}
