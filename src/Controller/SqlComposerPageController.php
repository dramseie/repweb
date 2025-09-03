<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SqlComposerPageController extends AbstractController
{
    #[Route('/sql-composer', name: 'app_sql_composer')]
    public function __invoke(): Response
    {
        return $this->render('tools/sql_composer.html.twig');
    }
}
