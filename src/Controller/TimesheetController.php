<?php

namespace App\Controller;

use App\Entity\TimesheetContract;
use App\Entity\TimesheetHour;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TimesheetController extends AbstractController
{
    #[Route('/timesheet', name: 'timesheet_index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $contracts = $entityManager
            ->getRepository(TimesheetContract::class)
            ->findBy([], ['createdAt' => 'DESC']);

        $hours = $entityManager
            ->getRepository(TimesheetHour::class)
            ->findBy([], ['workDate' => 'DESC', 'createdAt' => 'DESC']);

        return $this->render('timesheet/index.html.twig', [
            'contracts' => $contracts,
            'hours' => $hours,
            'error' => null,
        ]);
    }

    #[Route('/timesheet/contract', name: 'timesheet_contract_create', methods: ['POST'])]
    public function createContract(Request $request, EntityManagerInterface $entityManager): Response
    {
        $projectName = trim((string) $request->request->get('projectName', ''));
        $poNumber = trim((string) $request->request->get('poNumber', ''));
        $supplier = trim((string) $request->request->get('supplier', ''));
        $workloadHoursWeek = trim((string) $request->request->get('workloadHoursWeek', ''));

        $workloadValue = str_replace(',', '.', $workloadHoursWeek);

        if ($projectName === '' || $supplier === '' || $workloadValue === '' || !is_numeric($workloadValue)) {
            return $this->redirectToRoute('timesheet_index');
        }

        $workloadFormatted = number_format((float) $workloadValue, 2, '.', '');
        $contract = new TimesheetContract();
        $contract
            ->setProjectName($projectName)
            ->setPoNumber($poNumber !== '' ? $poNumber : null)
            ->setSupplier($supplier)
            ->setWorkloadHoursWeek($workloadFormatted)
            ->setBillingFrequency('monthly')
            ->setRequiresSignedReport(true)
            ->setUpdatedAt(new \DateTimeImmutable());

        $entityManager->persist($contract);
        $entityManager->flush();

        return $this->redirectToRoute('timesheet_index');
    }

    #[Route('/timesheet/contract/{id}', name: 'timesheet_contract_update', methods: ['POST'])]
    public function updateContract(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $contract = $entityManager->find(TimesheetContract::class, $id);
        if (!$contract) {
            return $this->redirectToRoute('timesheet_index');
        }

        $projectName = trim((string) $request->request->get('projectName', ''));
        $poNumber = trim((string) $request->request->get('poNumber', ''));
        $supplier = trim((string) $request->request->get('supplier', ''));
        $workloadHoursWeek = trim((string) $request->request->get('workloadHoursWeek', ''));

        $workloadValue = str_replace(',', '.', $workloadHoursWeek);

        if ($projectName === '' || $supplier === '' || $workloadValue === '' || !is_numeric($workloadValue)) {
            return $this->redirectToRoute('timesheet_index');
        }

        $workloadFormatted = number_format((float) $workloadValue, 2, '.', '');
        $contract
            ->setProjectName($projectName)
            ->setPoNumber($poNumber !== '' ? $poNumber : null)
            ->setSupplier($supplier)
            ->setWorkloadHoursWeek($workloadFormatted)
            ->setUpdatedAt(new \DateTimeImmutable());

        $entityManager->flush();

        return $this->redirectToRoute('timesheet_index');
    }

    #[Route('/timesheet/hours', name: 'timesheet_hours_create', methods: ['POST'])]
    public function createHours(Request $request, EntityManagerInterface $entityManager): Response
    {
        $contractId = (int) $request->request->get('contractId', 0);
        $workDateRaw = trim((string) $request->request->get('workDate', ''));
        $hoursRaw = trim((string) $request->request->get('hours', ''));
        $comment = trim((string) $request->request->get('comment', ''));

        $contract = $contractId > 0 ? $entityManager->find(TimesheetContract::class, $contractId) : null;
        $hoursValue = str_replace(',', '.', $hoursRaw);

        if (!$contract || $workDateRaw === '' || $hoursValue === '' || !is_numeric($hoursValue)) {
            return $this->redirectToRoute('timesheet_index');
        }

        try {
            $workDate = new \DateTimeImmutable($workDateRaw);
        } catch (\Throwable) {
            return $this->redirectToRoute('timesheet_index');
        }

        $hoursFormatted = number_format((float) $hoursValue, 2, '.', '');
        $entry = new TimesheetHour();
        $entry
            ->setContract($contract)
            ->setWorkDate($workDate)
            ->setHours($hoursFormatted)
            ->setComment($comment !== '' ? $comment : null)
            ->setUpdatedAt(new \DateTimeImmutable());

        $entityManager->persist($entry);
        $entityManager->flush();

        return $this->redirectToRoute('timesheet_index');
    }
}
