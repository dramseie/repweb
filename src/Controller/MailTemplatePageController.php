<?php
// src/Controller/MailTemplatePageController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MailTemplatePageController extends AbstractController
{
    #[Route('/mail/templates', name: 'mail_templates_page')]
    public function index(): Response
    {
        return $this->render('mail/templates.html.twig');
    }
}
