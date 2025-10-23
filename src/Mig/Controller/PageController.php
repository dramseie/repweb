<?php
namespace App\Mig\Controller;

use App\Mig\Service\FeatureToggle;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PageController extends AbstractController
{
    public function __construct(private readonly FeatureToggle $ft = new FeatureToggle()) {}

    #[Route('/mig', name: 'mig_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        // still enforce auth; visibility is controlled by feature flag in the UI
        $this->denyAccessUnlessGranted('ROLE_MIG_VIEW');

        return $this->render('mig/dashboard.html.twig');
    }
}
