<?php

namespace App\Controller;

use App\Entity\TimesheetContract;
use App\Entity\TimesheetHour;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        $startTimeRaw = trim((string) $request->request->get('startTime', ''));
        $endTimeRaw = trim((string) $request->request->get('endTime', ''));
        $comment = trim((string) $request->request->get('comment', ''));

        $contract = $contractId > 0 ? $entityManager->find(TimesheetContract::class, $contractId) : null;
        $hoursValue = str_replace(',', '.', $hoursRaw);

        if (!$contract || $workDateRaw === '') {
            return $this->redirectToRoute('timesheet_index');
        }

        try {
            $workDate = new \DateTimeImmutable($workDateRaw);
        } catch (\Throwable) {
            return $this->redirectToRoute('timesheet_index');
        }

        $startTime = null;
        $endTime = null;
        if ($startTimeRaw !== '' && $endTimeRaw !== '') {
            try {
                $startTime = new \DateTimeImmutable($workDate->format('Y-m-d') . ' ' . $startTimeRaw);
                $endTime = new \DateTimeImmutable($workDate->format('Y-m-d') . ' ' . $endTimeRaw);
            } catch (\Throwable) {
                return $this->redirectToRoute('timesheet_index');
            }

            $diffSeconds = $endTime->getTimestamp() - $startTime->getTimestamp();
            if ($diffSeconds <= 0) {
                return $this->redirectToRoute('timesheet_index');
            }
            $hoursFormatted = number_format($diffSeconds / 3600, 2, '.', '');
        } else {
            if ($hoursValue === '' || !is_numeric($hoursValue)) {
                return $this->redirectToRoute('timesheet_index');
            }
            $hoursFormatted = number_format((float) $hoursValue, 2, '.', '');
        }

        $entry = new TimesheetHour();
        $entry
            ->setContract($contract)
            ->setWorkDate($workDate)
            ->setHours($hoursFormatted)
            ->setComment($comment !== '' ? $comment : null)
            ->setStartTime($startTime)
            ->setEndTime($endTime)
            ->setUpdatedAt(new \DateTimeImmutable());

        $entityManager->persist($entry);
        $entityManager->flush();

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'id' => $entry->getId(),
                'contractId' => $contract->getId(),
                'contractLabel' => sprintf('%s · %s', $contract->getProjectName(), $contract->getSupplier()),
                'workDate' => $workDate->format('Y-m-d'),
                'hours' => $entry->getHours(),
                'comment' => $entry->getComment(),
                'startTime' => $entry->getStartTime()?->format('H:i'),
                'endTime' => $entry->getEndTime()?->format('H:i'),
            ], Response::HTTP_CREATED);
        }

        return $this->redirectToRoute('timesheet_index');
    }

    #[Route('/timesheet/hours/{id}', name: 'timesheet_hours_update', methods: ['POST'])]
    public function updateHours(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $entry = $entityManager->find(TimesheetHour::class, $id);
        if (!$entry) {
            return new JsonResponse(['message' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        $workDateRaw = trim((string) $request->request->get('workDate', ''));
        $startTimeRaw = trim((string) $request->request->get('startTime', ''));
        $endTimeRaw = trim((string) $request->request->get('endTime', ''));
        $comment = trim((string) $request->request->get('comment', ''));

        try {
            $workDate = $workDateRaw !== '' ? new \DateTimeImmutable($workDateRaw) : $entry->getWorkDate();
        } catch (\Throwable) {
            return new JsonResponse(['message' => 'Invalid date'], Response::HTTP_BAD_REQUEST);
        }

        if ($startTimeRaw !== '' && $endTimeRaw !== '') {
            try {
                $startTime = new \DateTimeImmutable($workDate->format('Y-m-d') . ' ' . $startTimeRaw);
                $endTime = new \DateTimeImmutable($workDate->format('Y-m-d') . ' ' . $endTimeRaw);
            } catch (\Throwable) {
                return new JsonResponse(['message' => 'Invalid time'], Response::HTTP_BAD_REQUEST);
            }

            $diffSeconds = $endTime->getTimestamp() - $startTime->getTimestamp();
            if ($diffSeconds <= 0) {
                return new JsonResponse(['message' => 'End time must be after start time'], Response::HTTP_BAD_REQUEST);
            }
            $hoursFormatted = number_format($diffSeconds / 3600, 2, '.', '');
            $entry
                ->setWorkDate($workDate)
                ->setStartTime($startTime)
                ->setEndTime($endTime)
                ->setHours($hoursFormatted)
                ->setComment($comment !== '' ? $comment : null)
                ->setUpdatedAt(new \DateTimeImmutable());
        }

        $entityManager->flush();

        return new JsonResponse([
            'id' => $entry->getId(),
            'workDate' => $entry->getWorkDate()->format('Y-m-d'),
            'hours' => $entry->getHours(),
            'comment' => $entry->getComment(),
            'startTime' => $entry->getStartTime()?->format('H:i'),
            'endTime' => $entry->getEndTime()?->format('H:i'),
        ]);
    }

    #[Route('/timesheet/hours/{id}/delete', name: 'timesheet_hours_delete', methods: ['POST'])]
    public function deleteHours(int $id, EntityManagerInterface $entityManager): Response
    {
        $entry = $entityManager->find(TimesheetHour::class, $id);
        if (!$entry) {
            return new JsonResponse(['message' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        $entityManager->remove($entry);
        $entityManager->flush();

        return new JsonResponse(['status' => 'ok']);
    }
}
