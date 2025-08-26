<?php
namespace App\Controller;

use App\Repository\ReportRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class PivotPageController extends AbstractController
{
	#[Route('/pivot/{id}', name: 'pivot_page', methods: ['GET'])]
	public function view(int $id, ReportRepository $reports): Response
	{
		$report = $reports->find($id);
		if (!$report) {
			throw $this->createNotFoundException('Report not found');
		}

		return $this->render('pivot/page.html.twig', [
			'report' => $report,
			'repId'  => $id,            // <-- pass the route id explicitly
		]);
	}
}
