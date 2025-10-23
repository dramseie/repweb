<?php
declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class QwPageController extends AbstractController
{
    #[Route('/qw/builder/{id}', name: 'qw_builder')]
    public function builder(int $id): Response
    {
        return $this->render('qw/builder.html.twig', [
            'qid' => $id,
        ]);
    }
}
