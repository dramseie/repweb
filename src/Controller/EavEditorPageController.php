<?php
// File: src/Controller/EavEditorPageController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class EavEditorPageController extends AbstractController
{
    #[Route('/eav/editor/{tenant}/{entityType}', name: 'eav_editor_page', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function page(string $tenant, string $entityType): Response
    {
        return $this->render('eav/editor.html.twig', [
            'tenant' => $tenant,
            'entityType' => $entityType,
        ]);
    }
}
