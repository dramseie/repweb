<?php

namespace App\Controller;

use App\Entity\TimesheetContract;
use App\Entity\TimesheetHour;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TimesheetController extends AbstractController
{
    #[Route('/timesheet', name: 'timesheet_index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        $contracts = $entityManager
            ->getRepository(TimesheetContract::class)
            ->findBy([], ['createdAt' => 'DESC']);

        $hours = $entityManager
            ->getRepository(TimesheetHour::class)
            ->findBy([], ['workDate' => 'DESC', 'createdAt' => 'DESC']);

        $statsMonthRaw = trim((string) $request->query->get('statsMonth', ''));
        $monthStart = null;
        if ($statsMonthRaw !== '') {
            $parsedMonth = \DateTimeImmutable::createFromFormat('Y-m', $statsMonthRaw);
            if ($parsedMonth instanceof \DateTimeImmutable) {
                $monthStart = $parsedMonth->setDate((int) $parsedMonth->format('Y'), (int) $parsedMonth->format('m'), 1)->setTime(0, 0, 0);
            }
        }
        if (!$monthStart) {
            $monthStart = (new \DateTimeImmutable('first day of this month'))->setTime(0, 0, 0);
        }
        $monthEnd = $monthStart->modify('+1 month');
        $statsByCategory = [];
        $monthlyReportable = [];
        $monthlyBillable = [];
        $billableCategories = ['RemoteOffice', 'OnSite'];
        foreach ($hours as $entry) {
            $workDate = $entry->getWorkDate();
            $workTimestamp = $workDate->getTimestamp();
            $category = $entry->getCategory() ?: 'Uncategorized';
            $monthKey = $workDate->format('Y-m');
            $monthlyReportable[$monthKey] = ($monthlyReportable[$monthKey] ?? 0.0) + (float) $entry->getHours();
            if (in_array($category, $billableCategories, true)) {
                $monthlyBillable[$monthKey] = ($monthlyBillable[$monthKey] ?? 0.0) + (float) $entry->getHours();
            }
            if ($workTimestamp < $monthStart->getTimestamp() || $workTimestamp >= $monthEnd->getTimestamp()) {
                continue;
            }
            $statsByCategory[$category] = ($statsByCategory[$category] ?? 0.0) + (float) $entry->getHours();
        }
        ksort($statsByCategory);
        ksort($monthlyReportable);
        ksort($monthlyBillable);

        $monthlyLabels = array_values(array_unique(array_merge(array_keys($monthlyReportable), array_keys($monthlyBillable))));
        sort($monthlyLabels);
        $monthlyReportableData = [];
        $monthlyBillableData = [];
        foreach ($monthlyLabels as $label) {
            $monthlyReportableData[] = number_format((float) ($monthlyReportable[$label] ?? 0.0), 2, '.', '');
            $monthlyBillableData[] = number_format((float) ($monthlyBillable[$label] ?? 0.0), 2, '.', '');
        }

        return $this->render('timesheet/index.html.twig', [
            'contracts' => $contracts,
            'hours' => $hours,
            'statsByCategory' => $statsByCategory,
            'statsMonthLabel' => $monthStart->format('Y-m'),
            'statsMonthlyLabels' => $monthlyLabels,
            'statsMonthlyReportable' => $monthlyReportableData,
            'statsMonthlyBillable' => $monthlyBillableData,
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
        $fromDateRaw = trim((string) $request->request->get('fromDate', ''));
        $toDateRaw = trim((string) $request->request->get('toDate', ''));
        $totalHoursRaw = trim((string) $request->request->get('totalHours', ''));
        $customerApprovalEmails = trim((string) $request->request->get('customerApprovalEmails', ''));
        $supplierTimesheetReceiverEmail = trim((string) $request->request->get('supplierTimesheetReceiverEmail', ''));

        $workloadValue = str_replace(',', '.', $workloadHoursWeek);
        $totalHoursValue = str_replace(',', '.', $totalHoursRaw);

        if ($projectName === '' || $supplier === '' || $workloadValue === '' || !is_numeric($workloadValue)) {
            return $this->redirectToRoute('timesheet_index');
        }

        $workloadFormatted = number_format((float) $workloadValue, 2, '.', '');
        $totalHoursFormatted = $totalHoursValue !== '' && is_numeric($totalHoursValue)
            ? number_format((float) $totalHoursValue, 2, '.', '')
            : null;
        try {
            $fromDate = $fromDateRaw !== '' ? new \DateTimeImmutable($fromDateRaw) : null;
            $toDate = $toDateRaw !== '' ? new \DateTimeImmutable($toDateRaw) : null;
        } catch (\Throwable) {
            return $this->redirectToRoute('timesheet_index');
        }
        $contract = new TimesheetContract();
        $contract
            ->setProjectName($projectName)
            ->setPoNumber($poNumber !== '' ? $poNumber : null)
            ->setSupplier($supplier)
            ->setWorkloadHoursWeek($workloadFormatted)
            ->setFromDate($fromDate)
            ->setToDate($toDate)
            ->setTotalHours($totalHoursFormatted)
            ->setCustomerApprovalEmails($customerApprovalEmails !== '' ? $customerApprovalEmails : null)
            ->setSupplierTimesheetReceiverEmail($supplierTimesheetReceiverEmail !== '' ? $supplierTimesheetReceiverEmail : null)
            ->setBillingFrequency('monthly')
            ->setRequiresSignedReport(true)
            ->setUpdatedAt(new \DateTimeImmutable());

        $file = $request->files->get('contractPdf');
        if ($file instanceof UploadedFile) {
            $storedPath = $this->storeContractPdf($file);
            if ($storedPath === null) {
                return $this->redirectToRoute('timesheet_index');
            }
            $contract->setContractPdfPath($storedPath);
        }

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
        $fromDateRaw = trim((string) $request->request->get('fromDate', ''));
        $toDateRaw = trim((string) $request->request->get('toDate', ''));
        $totalHoursRaw = trim((string) $request->request->get('totalHours', ''));
        $customerApprovalEmails = trim((string) $request->request->get('customerApprovalEmails', ''));
        $supplierTimesheetReceiverEmail = trim((string) $request->request->get('supplierTimesheetReceiverEmail', ''));

        $workloadValue = str_replace(',', '.', $workloadHoursWeek);
        $totalHoursValue = str_replace(',', '.', $totalHoursRaw);

        if ($projectName === '' || $supplier === '' || $workloadValue === '' || !is_numeric($workloadValue)) {
            return $this->redirectToRoute('timesheet_index');
        }

        $workloadFormatted = number_format((float) $workloadValue, 2, '.', '');
        $totalHoursFormatted = $totalHoursValue !== '' && is_numeric($totalHoursValue)
            ? number_format((float) $totalHoursValue, 2, '.', '')
            : null;
        try {
            $fromDate = $fromDateRaw !== '' ? new \DateTimeImmutable($fromDateRaw) : null;
            $toDate = $toDateRaw !== '' ? new \DateTimeImmutable($toDateRaw) : null;
        } catch (\Throwable) {
            return $this->redirectToRoute('timesheet_index');
        }
        $contract
            ->setProjectName($projectName)
            ->setPoNumber($poNumber !== '' ? $poNumber : null)
            ->setSupplier($supplier)
            ->setWorkloadHoursWeek($workloadFormatted)
            ->setFromDate($fromDate)
            ->setToDate($toDate)
            ->setTotalHours($totalHoursFormatted)
            ->setCustomerApprovalEmails($customerApprovalEmails !== '' ? $customerApprovalEmails : null)
            ->setSupplierTimesheetReceiverEmail($supplierTimesheetReceiverEmail !== '' ? $supplierTimesheetReceiverEmail : null)
            ->setUpdatedAt(new \DateTimeImmutable());

        $file = $request->files->get('contractPdf');
        if ($file instanceof UploadedFile) {
            $storedPath = $this->storeContractPdf($file);
            if ($storedPath === null) {
                return $this->redirectToRoute('timesheet_index');
            }
            $this->removeContractPdf($contract->getContractPdfPath());
            $contract->setContractPdfPath($storedPath);
        }

        $entityManager->flush();

        return $this->redirectToRoute('timesheet_index');
    }

    private function storeContractPdf(UploadedFile $file): ?string
    {
        if (!$file->isValid()) {
            return null;
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        $mime = $file->getClientMimeType() ?: $file->getMimeType() ?: '';
        if ($extension !== 'pdf' && $mime !== 'application/pdf') {
            return null;
        }

        $projectDir = (string) $this->getParameter('kernel.project_dir');
        $relativeDir = 'uploads/timesheet_contracts';
        $targetDir = $projectDir . '/public/' . $relativeDir;
        if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            return null;
        }

        try {
            $storedName = sprintf('contract-%s.pdf', bin2hex(random_bytes(8)));
        } catch (\Throwable) {
            return null;
        }

        $relativePath = $relativeDir . '/' . $storedName;
        try {
            $file->move($targetDir, $storedName);
        } catch (FileException) {
            return null;
        }

        return $relativePath;
    }

    private function removeContractPdf(?string $relativePath): void
    {
        if (!$relativePath) {
            return;
        }
        $projectDir = (string) $this->getParameter('kernel.project_dir');
        $absolutePath = $projectDir . '/public/' . ltrim($relativePath, '/');
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    #[Route('/timesheet/hours', name: 'timesheet_hours_create', methods: ['POST'])]
    public function createHours(Request $request, EntityManagerInterface $entityManager): Response
    {
        $contractId = (int) $request->request->get('contractId', 0);
        $startDateTimeRaw = trim((string) $request->request->get('startDateTime', ''));
        $endDateTimeRaw = trim((string) $request->request->get('endDateTime', ''));
        $workDateRaw = trim((string) $request->request->get('workDate', ''));
        $hoursRaw = trim((string) $request->request->get('hours', ''));
        $startTimeRaw = trim((string) $request->request->get('startTime', ''));
        $endTimeRaw = trim((string) $request->request->get('endTime', ''));
        $comment = trim((string) $request->request->get('comment', ''));
        $category = trim((string) $request->request->get('category', ''));

        $contract = $contractId > 0 ? $entityManager->find(TimesheetContract::class, $contractId) : null;
        $hoursValue = str_replace(',', '.', $hoursRaw);
        $globalCategories = ['Vacation', 'Sickness'];
        $isGlobalCategory = in_array($category, $globalCategories, true);

        if (($contractId > 0 && !$contract) || (!$contract && !$isGlobalCategory)) {
            return $this->redirectToRoute('timesheet_index');
        }

        $startTime = null;
        $endTime = null;
        $workDate = null;

        if ($startDateTimeRaw !== '' && $endDateTimeRaw !== '') {
            try {
                $startTime = new \DateTimeImmutable($startDateTimeRaw);
                $endTime = new \DateTimeImmutable($endDateTimeRaw);
            } catch (\Throwable) {
                return $this->redirectToRoute('timesheet_index');
            }

            $diffSeconds = $endTime->getTimestamp() - $startTime->getTimestamp();
            if ($diffSeconds <= 0) {
                return $this->redirectToRoute('timesheet_index');
            }

            $workDate = $startTime->setTime(0, 0, 0);
            $hoursFormatted = number_format($diffSeconds / 3600, 2, '.', '');
        } else {
            if ($workDateRaw === '') {
                return $this->redirectToRoute('timesheet_index');
            }

            try {
                $workDate = new \DateTimeImmutable($workDateRaw);
            } catch (\Throwable) {
                return $this->redirectToRoute('timesheet_index');
            }

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
        }

        $entry = new TimesheetHour();
        $entry
            ->setContract($contract)
            ->setWorkDate($workDate)
            ->setHours($hoursFormatted)
            ->setComment($comment !== '' ? $comment : null)
            ->setCategory($category !== '' ? $category : null)
            ->setStartTime($startTime)
            ->setEndTime($endTime)
            ->setUpdatedAt(new \DateTimeImmutable());

        $entityManager->persist($entry);
        $entityManager->flush();

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'id' => $entry->getId(),
            'contractId' => $contract?->getId(),
            'contractLabel' => $contract ? sprintf('%s · %s', $contract->getProjectName(), $contract->getSupplier()) : null,
                'workDate' => $workDate->format('Y-m-d'),
                'hours' => $entry->getHours(),
                'comment' => $entry->getComment(),
                'category' => $entry->getCategory(),
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

        $startDateTimeRaw = trim((string) $request->request->get('startDateTime', ''));
        $endDateTimeRaw = trim((string) $request->request->get('endDateTime', ''));
        $workDateRaw = trim((string) $request->request->get('workDate', ''));
        $hoursRaw = trim((string) $request->request->get('hours', ''));
        $startTimeRaw = trim((string) $request->request->get('startTime', ''));
        $endTimeRaw = trim((string) $request->request->get('endTime', ''));
        $comment = trim((string) $request->request->get('comment', ''));
        $category = trim((string) $request->request->get('category', ''));

        if ($startDateTimeRaw !== '' && $endDateTimeRaw !== '') {
            try {
                $startTime = new \DateTimeImmutable($startDateTimeRaw);
                $endTime = new \DateTimeImmutable($endDateTimeRaw);
            } catch (\Throwable) {
                return new JsonResponse(['message' => 'Invalid time'], Response::HTTP_BAD_REQUEST);
            }

            $diffSeconds = $endTime->getTimestamp() - $startTime->getTimestamp();
            if ($diffSeconds <= 0) {
                return new JsonResponse(['message' => 'End time must be after start time'], Response::HTTP_BAD_REQUEST);
            }

            $hoursFormatted = number_format($diffSeconds / 3600, 2, '.', '');
            $entry
                ->setWorkDate($startTime->setTime(0, 0, 0))
                ->setStartTime($startTime)
                ->setEndTime($endTime)
                ->setHours($hoursFormatted)
                ->setComment($comment !== '' ? $comment : null)
                ->setCategory($category !== '' ? $category : $entry->getCategory())
                ->setUpdatedAt(new \DateTimeImmutable());
        } else {
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
                    ->setCategory($category !== '' ? $category : $entry->getCategory())
                    ->setUpdatedAt(new \DateTimeImmutable());
            } else {
                $hoursValue = str_replace(',', '.', $hoursRaw);
                if ($hoursValue !== '' && is_numeric($hoursValue)) {
                    $hoursFormatted = number_format((float) $hoursValue, 2, '.', '');
                    $entry
                        ->setWorkDate($workDate)
                        ->setStartTime(null)
                        ->setEndTime(null)
                        ->setHours($hoursFormatted)
                        ->setComment($comment !== '' ? $comment : null)
                        ->setCategory($category !== '' ? $category : $entry->getCategory())
                        ->setUpdatedAt(new \DateTimeImmutable());
                } else {
                    $entry
                        ->setWorkDate($workDate)
                        ->setComment($comment !== '' ? $comment : null)
                        ->setCategory($category !== '' ? $category : $entry->getCategory())
                        ->setUpdatedAt(new \DateTimeImmutable());
                }
            }
        }

        $entityManager->flush();

        return new JsonResponse([
            'id' => $entry->getId(),
            'workDate' => $entry->getWorkDate()->format('Y-m-d'),
            'hours' => $entry->getHours(),
            'comment' => $entry->getComment(),
            'category' => $entry->getCategory(),
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
