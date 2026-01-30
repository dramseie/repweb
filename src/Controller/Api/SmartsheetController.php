<?php

namespace App\Controller\Api;

use App\Entity\SmartsheetProject;
use App\Entity\SmartsheetSyncLog;
use App\Entity\SmartsheetTask;
use App\Service\SmartsheetService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/smartsheet', name: 'api_smartsheet_')]
class SmartsheetController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SmartsheetService $smartsheetService,
    ) {
    }

    #[Route('/projects', name: 'projects', methods: ['GET'])]
    public function listProjects(): JsonResponse
    {
        $projects = $this->entityManager
            ->getRepository(SmartsheetProject::class)
            ->findBy([], ['projectName' => 'ASC']);

        $items = array_map(fn (SmartsheetProject $project) => $this->projectToArray($project), $projects);

        return $this->json([
            'items' => $items,
            'total' => count($items),
        ]);
    }

    #[Route('/projects/{projectId}', name: 'project_show', requirements: ['projectId' => '\\d+'], methods: ['GET'])]
    public function showProject(int $projectId): JsonResponse
    {
        $project = $this->entityManager->getRepository(SmartsheetProject::class)->find($projectId);
        if ($project === null) {
            return $this->json(['message' => 'Project not found.'], Response::HTTP_NOT_FOUND);
        }

        $tasks = $this->entityManager->getRepository(SmartsheetTask::class)->findBy(
            ['project' => $project],
            ['taskName' => 'ASC']
        );

        return $this->json([
            'project' => $this->projectToArray($project),
            'tasks' => array_map(fn (SmartsheetTask $task) => $this->taskToArray($task), $tasks),
            'taskCount' => count($tasks),
        ]);
    }

    #[Route('/projects/{projectId}/tasks', name: 'project_tasks', requirements: ['projectId' => '\\d+'], methods: ['GET'])]
    public function listProjectTasks(int $projectId): JsonResponse
    {
        $project = $this->entityManager->getRepository(SmartsheetProject::class)->find($projectId);
        if ($project === null) {
            return $this->json(['message' => 'Project not found.'], Response::HTTP_NOT_FOUND);
        }

        $tasks = $this->entityManager->getRepository(SmartsheetTask::class)->findBy(
            ['project' => $project],
            ['taskName' => 'ASC']
        );

        $items = array_map(fn (SmartsheetTask $task) => $this->taskToArray($task), $tasks);

        return $this->json([
            'project' => $this->projectToArray($project),
            'items' => $items,
            'total' => count($items),
        ]);
    }

    #[Route('/projects/sync', name: 'projects_sync', methods: ['POST'])]
    public function syncProjects(): JsonResponse
    {
        $count = $this->smartsheetService->syncProjects();

        return $this->json([
            'message' => 'Projects synchronised successfully.',
            'projectsSynced' => $count,
        ]);
    }

    #[Route('/projects/{projectId}/sync', name: 'project_tasks_sync', requirements: ['projectId' => '\\d+'], methods: ['POST'])]
    public function syncProjectTasks(int $projectId): JsonResponse
    {
        $project = $this->entityManager->getRepository(SmartsheetProject::class)->find($projectId);
        if ($project === null) {
            return $this->json(['message' => 'Project not found.'], Response::HTTP_NOT_FOUND);
        }

        $count = $this->smartsheetService->syncTasksForProject($project);

        return $this->json([
            'message' => 'Project tasks synchronised successfully.',
            'tasksSynced' => $count,
        ]);
    }

    #[Route('/sync', name: 'sync_all', methods: ['POST'])]
    public function syncAll(): JsonResponse
    {
        $result = $this->smartsheetService->syncAll();

        return $this->json([
            'message' => 'Smartsheet synchronisation completed.',
            'projectsSynced' => $result['projects'],
            'tasksSynced' => $result['tasks'],
        ]);
    }

    #[Route('/logs', name: 'logs', methods: ['GET'])]
    public function syncLogs(Request $request): JsonResponse
    {
        $limit = max(1, min(100, (int) $request->query->get('limit', 50)));

        $logs = $this->entityManager
            ->getRepository(SmartsheetSyncLog::class)
            ->findBy([], ['startedAt' => 'DESC'], $limit);

        $items = array_map(static function (SmartsheetSyncLog $log): array {
            return [
                'id' => $log->getId(),
                'syncType' => $log->getSyncType(),
                'status' => $log->getStatus(),
                'recordsProcessed' => $log->getRecordsProcessed(),
                'recordsAdded' => $log->getRecordsAdded(),
                'recordsUpdated' => $log->getRecordsUpdated(),
                'errorMessage' => $log->getErrorMessage(),
                'startedAt' => $log->getStartedAt()?->format(\DateTimeInterface::ATOM),
                'completedAt' => $log->getCompletedAt()?->format(\DateTimeInterface::ATOM),
            ];
        }, $logs);

        return $this->json([
            'items' => $items,
            'total' => count($items),
        ]);
    }

    private function projectToArray(SmartsheetProject $project): array
    {
        return [
            'id' => $project->getId(),
            'smartsheetSheetId' => $project->getSmartsheetSheetId(),
            'projectName' => $project->getProjectName(),
            'projectCode' => $project->getProjectCode(),
            'description' => $project->getDescription(),
            'startDate' => $project->getStartDate()?->format('Y-m-d'),
            'endDate' => $project->getEndDate()?->format('Y-m-d'),
            'status' => $project->getStatus()?->value,
            'lastSyncAt' => $project->getLastSyncAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    private function taskToArray(SmartsheetTask $task): array
    {
        return [
            'id' => $task->getId(),
            'smartsheetRowId' => $task->getSmartsheetRowId(),
            'taskName' => $task->getTaskName(),
            'taskNumber' => $task->getTaskNumber(),
            'description' => $task->getDescription(),
            'startDate' => $task->getStartDate()?->format('Y-m-d'),
            'dueDate' => $task->getDueDate()?->format('Y-m-d'),
            'assignedTo' => $task->getAssignedTo(),
            'status' => $task->getStatus(),
            'progress' => $task->getProgress(),
            'lastSyncAt' => $task->getLastSyncAt()?->format(\DateTimeInterface::ATOM),
            'projectId' => $task->getProject()?->getId(),
            'parentTaskId' => $task->getParentTask()?->getId(),
        ];
    }
}
