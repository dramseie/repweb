<?php
// src/Controller/PublicController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PublicController extends AbstractController
{
    #[Route('/what-is-repweb', name: 'app_what_is_repweb')]
    public function whatIsRepweb(): Response
    {
        return $this->render('public/what_is_repweb.html.twig');
    }
}
