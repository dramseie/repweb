<?php

namespace App\Service;

use App\Entity\SmartsheetProject;
use App\Entity\SmartsheetSyncLog;
use App\Entity\SmartsheetTask;
use Doctrine\DBAL\ConnectionException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class SmartsheetService
{
    private const API_BASE_URL = 'https://api.smartsheet.com/2.0';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly string $smartsheetApiToken,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchProjects(): array
    {
        $response = $this->makeApiRequest(self::API_BASE_URL . '/sheets');

        if ($response === null || !isset($response['data']) || !is_array($response['data'])) {
            $this->logger->warning('Smartsheet returned unexpected sheet payload.', ['payload' => $response]);

            return [];
        }

        return $response['data'];
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchTasks(int $sheetId): array
    {
        $response = $this->makeApiRequest(self::API_BASE_URL . '/sheets/' . $sheetId);

        return $response ?? [];
    }

    public function syncProjects(): int
    {
        $log = $this->createSyncLog('projects');

        $this->entityManager->beginTransaction();

        try {
            $existing = $this->getProjectRepository()->findAll();
            $projectBySheetId = [];
            foreach ($existing as $project) {
                $projectBySheetId[$project->getSmartsheetSheetId()] = $project;
            }

            $sheets = $this->fetchProjects();
            $synced = 0;
            $added = 0;
            $updated = 0;

            foreach ($sheets as $sheet) {
                if (!isset($sheet['id']) || !isset($sheet['name'])) {
                    continue;
                }

                $sheetId = (int) $sheet['id'];
                $project = $projectBySheetId[$sheetId] ?? new SmartsheetProject();
                $isNew = $project->getId() === null;

                $project
                    ->setSmartsheetSheetId($sheetId)
                    ->setProjectName((string) $sheet['name'])
                    ->setProjectCode((string)($sheet['permalink'] ?? $sheet['name']))
                    ->setStatus($project->getStatus())
                ;

                if (isset($sheet['createdAt']) && $project->getStartDate() === null) {
                    $project->setStartDate($this->parseDate($sheet['createdAt']));
                }

                if (isset($sheet['modifiedAt'])) {
                    $project->setLastSyncAt($this->parseDateTime($sheet['modifiedAt']));
                }

                if ($isNew) {
                    $this->entityManager->persist($project);
                    $added++;
                } else {
                    $updated++;
                }

                $synced++;
            }

            $log->setRecordsProcessed($synced)
                ->setRecordsAdded($added)
                ->setRecordsUpdated($updated);

            $this->entityManager->flush();
            $this->entityManager->commit();

            $this->completeSyncLog($log, 'completed');

            return $synced;
        } catch (Throwable $exception) {
            $this->rollbackQuietly();
            $this->completeSyncLog($log, 'failed', $exception->getMessage());
            $this->logger->error('Failed to synchronise Smartsheet projects.', ['exception' => $exception]);

            return 0;
        }
    }

    public function syncTasksForProject(SmartsheetProject $project): int
    {
        $log = $this->createSyncLog('tasks');

        $this->entityManager->beginTransaction();

        try {
            $sheetPayload = $this->fetchTasks($project->getSmartsheetSheetId());
            if (empty($sheetPayload)) {
                $this->completeSyncLog($log, 'completed');
                $this->entityManager->commit();

                return 0;
            }

            $columns = $this->buildColumnMap($sheetPayload['columns'] ?? []);
            $existingTasks = $this->getTaskRepository()->findBy(['project' => $project]);
            $taskByRowId = [];

            foreach ($existingTasks as $taskEntity) {
                $taskByRowId[$taskEntity->getSmartsheetRowId()] = $taskEntity;
            }

            $resolvedParents = [];
            $rows = $sheetPayload['rows'] ?? [];
            $count = 0;
            $added = 0;
            $updated = 0;

            foreach ($rows as $row) {
                if (!isset($row['id'])) {
                    continue;
                }

                $rowId = (int) $row['id'];
                $task = $taskByRowId[$rowId] ?? new SmartsheetTask();
                $isNew = $task->getId() === null;

                $task
                    ->setSmartsheetRowId($rowId)
                    ->setTaskName((string) $this->extractValue($row, $columns, 'Task Name'))
                    ->setTaskNumber($this->extractValue($row, $columns, 'Task Number'))
                    ->setDescription($this->extractValue($row, $columns, 'Description'))
                    ->setStartDate($this->parseDate($this->extractValue($row, $columns, 'Start Date')))
                    ->setDueDate($this->parseDate($this->extractValue($row, $columns, 'Due Date')))
                    ->setAssignedTo($this->extractValue($row, $columns, 'Assigned To'))
                    ->setStatus($this->extractValue($row, $columns, 'Status'))
                    ->setProgress($this->parseInt($this->extractValue($row, $columns, 'Progress')))
                    ->setLastSyncAt(new \DateTimeImmutable())
                    ->setProject($project);

                $parentId = isset($row['parentId']) ? (int) $row['parentId'] : null;
                if ($parentId !== null) {
                    $resolvedParents[$rowId] = $parentId;
                } else {
                    $task->setParentTask(null);
                }

                if ($isNew) {
                    $this->entityManager->persist($task);
                    $added++;
                } else {
                    $updated++;
                }

                $taskByRowId[$rowId] = $task;
                $count++;
            }

            foreach ($resolvedParents as $childRowId => $parentRowId) {
                $child = $taskByRowId[$childRowId] ?? null;
                $parent = $taskByRowId[$parentRowId] ?? null;
                if ($child === null) {
                    continue;
                }

                $child->setParentTask($parent);
            }

            $log->setRecordsProcessed($count)
                ->setRecordsAdded($added)
                ->setRecordsUpdated($updated);

            $this->entityManager->flush();
            $this->entityManager->commit();

            $this->completeSyncLog($log, 'completed');

            return $count;
        } catch (Throwable $exception) {
            $this->rollbackQuietly();
            $this->completeSyncLog($log, 'failed', $exception->getMessage());
            $this->logger->error('Failed to synchronise Smartsheet tasks.', [
                'project' => $project->getId(),
                'sheet' => $project->getSmartsheetSheetId(),
                'exception' => $exception,
            ]);

            return 0;
        }
    }

    /**
     * @return array{projects:int,tasks:int}
     */
    public function syncAll(): array
    {
        $projects = $this->syncProjects();

        $taskCount = 0;
        $projectsToSync = $this->getProjectRepository()->findAll();
        foreach ($projectsToSync as $project) {
            $taskCount += $this->syncTasksForProject($project);
        }

        return ['projects' => $projects, 'tasks' => $taskCount];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function makeApiRequest(string $url, string $method = 'GET', ?array $data = null): ?array
    {
        $curl = curl_init($url);
        if ($curl === false) {
            $this->logger->error('Failed to initialise cURL session.', ['url' => $url]);

            return null;
        }

        $headers = [
            'Authorization: Bearer ' . $this->smartsheetApiToken,
            'Accept: application/json',
        ];

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ]);

        if ($data !== null) {
            $payload = json_encode($data, JSON_THROW_ON_ERROR);
            $headers[] = 'Content-Type: application/json';
            curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
        }

        $body = curl_exec($curl);
        $statusCode = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        if ($body === false) {
            $this->logger->error('Smartsheet API request failed.', [
                'url' => $url,
                'error' => curl_error($curl),
            ]);
            curl_close($curl);

            return null;
        }

        curl_close($curl);

        if ($statusCode >= 400) {
            $this->logger->error('Smartsheet API returned error response.', [
                'url' => $url,
                'status' => $statusCode,
                'body' => $body,
            ]);

            return null;
        }

        try {
            $decoded = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            $this->logger->error('Failed to decode Smartsheet API response.', [
                'url' => $url,
                'exception' => $exception,
                'body' => $body,
            ]);

            return null;
        }

        return $decoded;
    }

    private function createSyncLog(string $syncType): SmartsheetSyncLog
    {
        $log = new SmartsheetSyncLog();
        $log->setSyncType($syncType)
            ->setStatus('started')
            ->setStartedAt(new \DateTimeImmutable())
            ->setRecordsProcessed(0)
            ->setRecordsAdded(0)
            ->setRecordsUpdated(0);

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        return $log;
    }

    private function completeSyncLog(SmartsheetSyncLog $log, string $status, ?string $error = null): void
    {
        $log->setStatus($status)
            ->setCompletedAt(new \DateTimeImmutable())
            ->setErrorMessage($error);

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }

    private function rollbackQuietly(): void
    {
        try {
            if ($this->entityManager->getConnection()->isTransactionActive()) {
                $this->entityManager->rollback();
            }
        } catch (ConnectionException $exception) {
            $this->logger->warning('Failed to rollback database transaction.', ['exception' => $exception]);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $columns
     *
     * @return array<string, int>
     */
    private function buildColumnMap(array $columns): array
    {
        $map = [];
        foreach ($columns as $column) {
            if (!isset($column['title']) || !isset($column['id'])) {
                continue;
            }
            $map[(string) $column['title']] = (int) $column['id'];
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function extractValue(array $row, array $columnMap, string $columnName): ?string
    {
        $columnId = $columnMap[$columnName] ?? null;
        if ($columnId === null || !isset($row['cells']) || !is_array($row['cells'])) {
            return null;
        }

        foreach ($row['cells'] as $cell) {
            if (($cell['columnId'] ?? null) === $columnId) {
                return isset($cell['displayValue']) ? (string) $cell['displayValue'] : (isset($cell['value']) ? (string) $cell['value'] : null);
            }
        }

        return null;
    }

    private function parseDate(?string $value): ?\DateTimeInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function parseDateTime(?string $value): ?\DateTimeInterface
    {
        return $this->parseDate($value);
    }

    private function parseInt(?string $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT);
    }

    private function getProjectRepository()
    {
        return $this->entityManager->getRepository(SmartsheetProject::class);
    }

    private function getTaskRepository()
    {
        return $this->entityManager->getRepository(SmartsheetTask::class);
    }
}
