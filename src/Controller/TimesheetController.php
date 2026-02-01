<?php

namespace App\Controller;

use App\Entity\TimesheetContract;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TimesheetController extends AbstractController
{
    #[Route('/timesheet', name: 'timesheet_index', methods: ['GET', 'POST'])]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        $error = null;
        $formData = [
            'projectName' => '',
            'poNumber' => '',
            'supplier' => '',
            'workloadHoursWeek' => '',
        ];

        if ($request->isMethod('POST')) {
            $formData['projectName'] = trim((string) $request->request->get('projectName', ''));
            $formData['poNumber'] = trim((string) $request->request->get('poNumber', ''));
            $formData['supplier'] = trim((string) $request->request->get('supplier', ''));
            $formData['workloadHoursWeek'] = trim((string) $request->request->get('workloadHoursWeek', ''));

            $workloadValue = str_replace(',', '.', $formData['workloadHoursWeek']);

            if ($formData['projectName'] === '' || $formData['supplier'] === '' || $workloadValue === '') {
                $error = 'Project name, supplier, and workload are required.';
            } elseif (!is_numeric($workloadValue)) {
                $error = 'Workload must be a number.';
            } else {
                $workloadFormatted = number_format((float) $workloadValue, 2, '.', '');
                $contract = new TimesheetContract();
                $contract
                    ->setProjectName($formData['projectName'])
                    ->setPoNumber($formData['poNumber'] !== '' ? $formData['poNumber'] : null)
                    ->setSupplier($formData['supplier'])
                    ->setWorkloadHoursWeek($workloadFormatted)
                    ->setBillingFrequency('monthly')
                    ->setRequiresSignedReport(true)
                    ->setUpdatedAt(new \DateTimeImmutable());

                $entityManager->persist($contract);
                $entityManager->flush();

                return $this->redirectToRoute('timesheet_index');
            }
        }

        $contracts = $entityManager
            ->getRepository(TimesheetContract::class)
            ->findBy([], ['createdAt' => 'DESC']);

        return $this->render('timesheet/index.html.twig', [
            'contracts' => $contracts,
            'error' => $error,
            'formData' => $formData,
        ]);
    }
}
