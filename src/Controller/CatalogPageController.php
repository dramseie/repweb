<?php
// src/Controller/CatalogPageController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class CatalogPageController extends AbstractController
{
    #[Route('/catalog', name: 'catalog_page', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('catalog/index.html.twig');
    }
}
