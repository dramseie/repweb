<?php

namespace App\Controller\Api;

use App\Entity\Issue;
use App\Entity\IssueLabel;
use App\Entity\SmartsheetProject;
use App\Entity\SmartsheetTask;
use App\Entity\Sprint;
use App\Entity\User;
use App\Enum\IssuePriority;
use App\Enum\IssueResolution;
use App\Enum\IssueSeverity;
use App\Enum\IssueStatus;
use App\Enum\IssueType;
use App\Repository\IssueRepository;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/issues', name: 'api_issues_')]
class IssueController extends AbstractController
{
    public function __construct(
        private readonly IssueRepository $issueRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $filters = [
            'status' => $this->normaliseList($request->query->all('status')),
            'priority' => $this->normaliseList($request->query->all('priority')),
            'severity' => $this->normaliseList($request->query->all('severity')),
            'assignee' => $request->query->getInt('assignee') ?: null,
            'reporter' => $request->query->getInt('reporter') ?: null,
            'project' => $request->query->getInt('project') ?: null,
            'task' => $request->query->getInt('task') ?: null,
            'sprint' => $request->query->getInt('sprint') ?: null,
            'search' => $request->query->get('search'),
            'dateFrom' => $request->query->get('dateFrom'),
            'dateTo' => $request->query->get('dateTo'),
        ];

        $filters = array_filter($filters, static fn ($value) => $value !== null && $value !== [] && $value !== '');

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = max(1, min(100, (int) $request->query->get('limit', 20)));

        $result = $this->issueRepository->findByFilters($filters, $page, $limit);

        return $this->json([
            'items' => $result['issues'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
        ], context: ['groups' => ['issue:read']]);
    }

    #[Route('/statistics', name: 'stats', methods: ['GET'])]
    public function statistics(): JsonResponse
    {
        $stats = $this->issueRepository->getIssueStatistics();

        return $this->json($stats);
    }

    #[Route('/search', name: 'search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $query = (string) $request->query->get('q', '');
        $issues = $this->issueRepository->searchIssues($query);

        return $this->json([
            'items' => $issues,
            'total' => count($issues),
        ], context: ['groups' => ['issue:read']]);
    }

    #[Route('/assignees/{assigneeId}/open', name: 'open_by_assignee', requirements: ['assigneeId' => '\d+'], methods: ['GET'])]
    public function openByAssignee(int $assigneeId): JsonResponse
    {
        $user = $this->entityManager->getRepository(User::class)->find($assigneeId);
        if ($user === null) {
            return $this->json(['message' => 'Assignee not found.'], Response::HTTP_NOT_FOUND);
        }

        $issues = $this->issueRepository->findOpenIssuesByAssignee($user);

        return $this->json([
            'items' => $issues,
            'total' => count($issues),
        ], context: ['groups' => ['issue:read']]);
    }

    #[Route('/due', name: 'due_range', methods: ['GET'])]
    public function byDueDate(Request $request): JsonResponse
    {
        $start = $this->parseDate($request->query->get('start'));
        $end = $this->parseDate($request->query->get('end'));

        if ($start === null || $end === null) {
            return $this->json([
                'message' => 'Both start and end query parameters must be valid dates.',
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($end < $start) {
            return $this->json([
                'message' => 'The end date must be greater than or equal to the start date.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $issues = $this->issueRepository->findIssuesByDueDateRange($start, $end);

        return $this->json([
            'items' => $issues,
            'total' => count($issues),
        ], context: ['groups' => ['issue:read']]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $issue = $this->issueRepository->findIssueWithDetails($id);
        if ($issue === null) {
            return $this->json(['message' => 'Issue not found.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($issue, context: ['groups' => ['issue:detail']]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = $this->decodeJsonBody($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $issue = new Issue();

        $errors = $this->applyIssueData($issue, $payload, true);
        if ($errors !== []) {
            return $this->json([
                'message' => 'Invalid request payload.',
                'errors' => $errors,
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!$issue->getIssueNumber()) {
            $issue->setIssueNumber($this->generateIssueNumber());
        }

        $validation = $this->validator->validate($issue, groups: ['Default', 'issue:write']);
        if (count($validation) > 0) {
            return $this->json([
                'message' => 'Validation failed.',
                'errors' => $this->formatViolations($validation),
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($issue);
        $this->entityManager->flush();

        return $this->json($issue, Response::HTTP_CREATED, context: ['groups' => ['issue:detail']]);
    }

    #[Route('/{id}', name: 'update', requirements: ['id' => '\d+'], methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $issue = $this->issueRepository->find($id);
        if ($issue === null) {
            return $this->json(['message' => 'Issue not found.'], Response::HTTP_NOT_FOUND);
        }

        $payload = $this->decodeJsonBody($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $errors = $this->applyIssueData($issue, $payload, false);
        if ($errors !== []) {
            return $this->json([
                'message' => 'Invalid request payload.',
                'errors' => $errors,
            ], Response::HTTP_BAD_REQUEST);
        }

        $issue->setUpdatedAt(new \DateTimeImmutable());

        $validation = $this->validator->validate($issue, groups: ['Default', 'issue:write']);
        if (count($validation) > 0) {
            return $this->json([
                'message' => 'Validation failed.',
                'errors' => $this->formatViolations($validation),
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return $this->json($issue, context: ['groups' => ['issue:detail']]);
    }

    #[Route('/{id}/external-link', name: 'link_external', requirements: ['id' => '\d+'], methods: ['PUT', 'PATCH'])]
    public function linkExternal(int $id, Request $request): JsonResponse
    {
        $issue = $this->issueRepository->find($id);
        if ($issue === null) {
            return $this->json(['message' => 'Issue not found.'], Response::HTTP_NOT_FOUND);
        }

        $payload = $this->decodeJsonBody($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        if (!is_array($payload)) {
            return $this->json([
                'message' => 'Request body must be a JSON object.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $shouldUnlink = filter_var($payload['unlink'] ?? false, FILTER_VALIDATE_BOOL);
        if ($shouldUnlink) {
            $issue->setTask(null);
            $issue->setProject(null);
            $issue->setUpdatedAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            $refreshed = $this->issueRepository->findIssueWithDetails($issue->getId());

            return $this->json([
                'message' => 'Issue external link cleared.',
                'issue' => $refreshed ?? $issue,
            ], context: ['groups' => ['issue:detail']]);
        }

        $source = strtolower(trim((string) ($payload['source'] ?? '')));
        if ($source === '') {
            return $this->json([
                'message' => 'source is required.',
            ], Response::HTTP_BAD_REQUEST);
        }

        return match ($source) {
            'smartsheet' => $this->linkSmartsheetSource($issue, $payload),
            default => $this->json([
                'message' => sprintf('Unsupported external source "%s".', $source),
            ], Response::HTTP_BAD_REQUEST),
        };
    }

    #[Route('/{id}', name: 'delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $issue = $this->issueRepository->find($id);
        if ($issue === null) {
            return $this->json(['message' => 'Issue not found.'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($issue);
        $this->entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @return array<int, string>
     */
    private function applyIssueData(Issue $issue, array $payload, bool $isCreate): array
    {
        $errors = [];

        if (array_key_exists('issueNumber', $payload)) {
            $issueNumber = trim((string) $payload['issueNumber']);
            if ($issueNumber === '') {
                $errors[] = 'issueNumber cannot be blank.';
            } else {
                $issue->setIssueNumber($issueNumber);
            }
        }

        if (isset($payload['title'])) {
            $issue->setTitle((string) $payload['title']);
        } elseif ($isCreate) {
            $errors[] = 'title is required.';
        }

        if (array_key_exists('description', $payload)) {
            $issue->setDescription($this->toNullableString($payload['description']));
        }

        if (isset($payload['issueType'])) {
            $enum = $this->mapEnumValue(IssueType::class, $payload['issueType']);
            if ($enum instanceof IssueType) {
                $issue->setIssueType($enum);
            } else {
                $errors[] = 'Invalid issueType value.';
            }
        }

        if (isset($payload['priority'])) {
            $enum = $this->mapEnumValue(IssuePriority::class, $payload['priority']);
            if ($enum instanceof IssuePriority) {
                $issue->setPriority($enum);
            } else {
                $errors[] = 'Invalid priority value.';
            }
        }

        if (isset($payload['severity'])) {
            $enum = $this->mapEnumValue(IssueSeverity::class, $payload['severity']);
            if ($enum instanceof IssueSeverity) {
                $issue->setSeverity($enum);
            } else {
                $errors[] = 'Invalid severity value.';
            }
        }

        if (isset($payload['status'])) {
            $enum = $this->mapEnumValue(IssueStatus::class, $payload['status']);
            if ($enum instanceof IssueStatus) {
                $issue->setStatus($enum);
                if ($enum === IssueStatus::Resolved && !isset($payload['resolvedAt']) && $issue->getResolvedAt() === null) {
                    $issue->setResolvedAt(new \DateTimeImmutable());
                }
                if ($enum === IssueStatus::Closed && !isset($payload['closedAt']) && $issue->getClosedAt() === null) {
                    $issue->setClosedAt(new \DateTimeImmutable());
                }
            } else {
                $errors[] = 'Invalid status value.';
            }
        }

        if (array_key_exists('resolution', $payload)) {
            if ($payload['resolution'] === null || $payload['resolution'] === '') {
                $issue->setResolution(null);
            } else {
                $enum = $this->mapEnumValue(IssueResolution::class, $payload['resolution']);
                if ($enum instanceof IssueResolution) {
                    $issue->setResolution($enum);
                } else {
                    $errors[] = 'Invalid resolution value.';
                }
            }
        }

        if (array_key_exists('dueDate', $payload)) {
            $issue->setDueDate($this->parseDate($payload['dueDate'] ?? null));
        }

        if (array_key_exists('taskDueDate', $payload)) {
            $issue->setTaskDueDate($this->parseDate($payload['taskDueDate'] ?? null));
        }

        if (array_key_exists('estimatedHours', $payload)) {
            $issue->setEstimatedHours($this->normaliseDecimal($payload['estimatedHours']));
        }

        if (array_key_exists('actualHours', $payload)) {
            $issue->setActualHours($this->normaliseDecimal($payload['actualHours']));
        }

        if (array_key_exists('labels', $payload)) {
            if (is_array($payload['labels'])) {
                $this->syncIssueLabels($issue, $payload['labels']);
            } elseif ($payload['labels'] === null) {
                $this->syncIssueLabels($issue, []);
            } else {
                $errors[] = 'labels must be an array.';
            }
        }

        if (array_key_exists('projectId', $payload)) {
            $project = $this->resolveNullableEntity(SmartsheetProject::class, $payload['projectId']);
            if ($project instanceof SmartsheetProject || $project === null) {
                $issue->setProject($project);
            } else {
                $errors[] = 'Invalid projectId.';
            }
        }

        if (array_key_exists('taskId', $payload)) {
            $task = $this->resolveNullableEntity(SmartsheetTask::class, $payload['taskId']);
            if ($task instanceof SmartsheetTask || $task === null) {
                $issue->setTask($task);
            } else {
                $errors[] = 'Invalid taskId.';
            }
        }

        $reporterEmailProvided = array_key_exists('reporterEmail', $payload);
        $reporterIdProvided = array_key_exists('reporterId', $payload);

        if ($isCreate || $reporterEmailProvided || $reporterIdProvided) {
            $reporterEmail = $reporterEmailProvided ? trim((string) ($payload['reporterEmail'] ?? '')) : null;

            if ($reporterEmailProvided && $reporterEmail === '') {
                $errors[] = 'reporterEmail is required.';
            } else {
                $reporter = $this->resolveUserReference(
                    $reporterEmailProvided ? $reporterEmail : null,
                    $reporterIdProvided ? ($payload['reporterId'] ?? null) : null
                );

                if ($reporter instanceof User) {
                    $issue->setReporter($reporter);
                } elseif ($reporter === false) {
                    $errors[] = $reporterEmailProvided ? 'reporterEmail must match an existing user.' : 'Invalid reporterId.';
                } elseif ($isCreate) {
                    $errors[] = 'reporterEmail is required.';
                }
            }
        }

        $assigneeEmailProvided = array_key_exists('assigneeEmail', $payload);
        $assigneeIdProvided = array_key_exists('assigneeId', $payload);

        if ($assigneeEmailProvided) {
            $assigneeEmail = trim((string) ($payload['assigneeEmail'] ?? ''));

            if ($assigneeEmail === '') {
                $issue->setAssignee(null);
            } else {
                $assignee = $this->resolveUserReference($assigneeEmail, null);
                if ($assignee instanceof User) {
                    $issue->setAssignee($assignee);
                } elseif ($assignee === false) {
                    $errors[] = 'assigneeEmail must match an existing user.';
                }
            }
        } elseif ($assigneeIdProvided) {
            $assignee = $this->resolveUserReference(null, $payload['assigneeId']);
            if ($assignee instanceof User) {
                $issue->setAssignee($assignee);
            } elseif ($assignee === null) {
                $issue->setAssignee(null);
            } elseif ($assignee === false) {
                $errors[] = 'Invalid assigneeId.';
            }
        }

        if (array_key_exists('epicId', $payload)) {
            if ($payload['epicId'] === null) {
                $issue->setEpic(null);
            } else {
                $epic = $this->issueRepository->find((int) $payload['epicId']);
                if ($epic instanceof Issue) {
                    $issue->setEpic($epic);
                } else {
                    $errors[] = 'Invalid epicId.';
                }
            }
        }

        if (array_key_exists('sprintId', $payload)) {
            $sprint = $this->resolveNullableEntity(Sprint::class, $payload['sprintId']);
            if ($sprint instanceof Sprint || $sprint === null) {
                $issue->setSprint($sprint);
            } else {
                $errors[] = 'Invalid sprintId.';
            }
        }

        if (array_key_exists('resolvedAt', $payload)) {
            $issue->setResolvedAt($this->parseDateTime($payload['resolvedAt']));
        }

        if (array_key_exists('closedAt', $payload)) {
            $issue->setClosedAt($this->parseDateTime($payload['closedAt']));
        }

        return $errors;
    }

    private function decodeJsonBody(Request $request): array|JsonResponse
    {
        $content = $request->getContent();
        if ($content === '') {
            return [];
        }

        try {
            $decoded = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->json([
                'message' => 'Invalid JSON payload.',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!is_array($decoded)) {
            return $this->json([
                'message' => 'Request body must be a JSON object.',
            ], Response::HTTP_BAD_REQUEST);
        }

        return $decoded;
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function formatViolations(ConstraintViolationListInterface $violations): array
    {
        $formatted = [];
        /** @var ConstraintViolationInterface $violation */
        foreach ($violations as $violation) {
            $formatted[] = [
                'field' => $violation->getPropertyPath(),
                'message' => $violation->getMessage(),
            ];
        }

        return $formatted;
    }

    /**
     * @param class-string $enumClass
     */
    private function mapEnumValue(string $enumClass, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof $enumClass) {
            return $value;
        }

        if (is_string($value)) {
            $value = strtolower(str_replace(['-', ' '], '_', $value));
        }

        try {
            return $enumClass::from($value);
        } catch (\ValueError | \TypeError) {
            return null;
        }
    }

    private function parseDate(mixed $value): ?\DateTimeInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable((string) $value);
        } catch (\Exception) {
            return null;
        }
    }

    private function parseDateTime(mixed $value): ?\DateTimeInterface
    {
        return $this->parseDate($value);
    }

    private function normaliseDecimal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $className
     *
     * @return T|null|false
     */
    private function resolveNullableEntity(string $className, mixed $id): mixed
    {
        if ($id === null || $id === '') {
            return null;
        }

        $entity = $this->entityManager->getRepository($className)->find((int) $id);

        return $entity ?? false;
    }

    private function resolveUserReference(?string $email, mixed $id): User|null|false
    {
        if ($email !== null) {
            $user = $this->findUserByEmail($email);

            return $user ?? false;
        }

        return $this->resolveNullableEntity(User::class, $id);
    }

    private function linkSmartsheetSource(Issue $issue, array $payload): JsonResponse
    {
        $projectId = $payload['projectId'] ?? null;
        $taskId = $payload['taskId'] ?? null;
        $rowId = $payload['smartsheetRowId'] ?? null;

        $project = null;
        $task = null;

        if ($taskId !== null) {
            $task = $this->resolveNullableEntity(SmartsheetTask::class, $taskId);
            if ($task === false) {
                return $this->json([
                    'message' => 'Smartsheet task not found.',
                ], Response::HTTP_NOT_FOUND);
            }
        } elseif ($rowId !== null) {
            $task = $this->entityManager
                ->getRepository(SmartsheetTask::class)
                ->findOneBy(['smartsheetRowId' => (int) $rowId]);

            if (!$task instanceof SmartsheetTask) {
                return $this->json([
                    'message' => 'Smartsheet task not found for the provided row id.',
                ], Response::HTTP_NOT_FOUND);
            }
        }

        if ($projectId !== null) {
            $project = $this->resolveNullableEntity(SmartsheetProject::class, $projectId);
            if ($project === false) {
                return $this->json([
                    'message' => 'Smartsheet project not found.',
                ], Response::HTTP_NOT_FOUND);
            }
        } elseif ($task instanceof SmartsheetTask) {
            $project = $task->getProject();
        }

        if ($task === null && $project === null) {
            return $this->json([
                'message' => 'At least one of taskId, smartsheetRowId, or projectId must be provided.',
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($task instanceof SmartsheetTask && $project instanceof SmartsheetProject) {
            if ($task->getProject()?->getId() !== $project->getId()) {
                return $this->json([
                    'message' => 'The task does not belong to the provided project.',
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        $issue->setTask($task);
        $issue->setProject($project);
        $issue->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        $refreshed = $this->issueRepository->findIssueWithDetails($issue->getId());

        return $this->json([
            'message' => 'Issue linked to Smartsheet source.',
            'issue' => $refreshed ?? $issue,
        ], context: ['groups' => ['issue:detail']]);
    }

    private function findUserByEmail(string $email): ?User
    {
        $email = trim($email);
        if ($email === '') {
            return null;
        }

        $repository = $this->entityManager->getRepository(User::class);

        $user = $repository->findOneBy(['email' => $email]);
        if (!$user instanceof User && $email !== strtolower($email)) {
            $user = $repository->findOneBy(['email' => strtolower($email)]);
        }

        return $user instanceof User ? $user : null;
    }

    private function syncIssueLabels(Issue $issue, array $labels): void
    {
        $existing = $issue->getLabelEntities();
        foreach (iterator_to_array($existing) as $label) {
            if ($label instanceof IssueLabel) {
                $label->removeIssue($issue);
            }
        }

        $names = [];
        $labelRepository = $this->entityManager->getRepository(IssueLabel::class);
        foreach ($labels as $entry) {
            if (is_array($entry)) {
                $name = isset($entry['name']) ? trim((string) $entry['name']) : '';
                $color = isset($entry['color']) ? (string) $entry['color'] : null;
                $description = isset($entry['description']) ? (string) $entry['description'] : null;
            } else {
                $name = trim((string) $entry);
                $color = null;
                $description = null;
            }

            if ($name === '') {
                continue;
            }

            $names[] = $name;

            $label = $labelRepository->findOneBy(['name' => $name]);
            if (!$label instanceof IssueLabel) {
                $label = new IssueLabel();
                $label->setName($name);
            }

            if ($color !== null) {
                if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
                    $color = null;
                }
            }

            if ($color !== null) {
                $label->setColor($color);
            }

            if ($description !== null) {
                $description = trim($description);
                $label->setDescription($description === '' ? null : $description);
            }

            $this->entityManager->persist($label);
            $label->addIssue($issue);
        }

        $issue->setLabels($names !== [] ? $names : null);
    }

    private function generateIssueNumber(): string
    {
        $prefix = 'ISSUE-' . (new \DateTimeImmutable())->format('Ymd');
        $attempts = 0;
        $candidate = '';
        $exists = true;

        do {
            try {
                $suffix = strtoupper(bin2hex(random_bytes(3)));
            } catch (\Exception) {
                $suffix = strtoupper(substr(hash('crc32', uniqid('', true)), 0, 6));
            }

            $candidate = $prefix . '-' . $suffix;
            $exists = $this->issueRepository->findOneBy(['issueNumber' => $candidate]) instanceof Issue;
            $attempts++;
        } while ($exists && $attempts < 10);

        if ($exists) {
            return $prefix . '-' . strtoupper(substr(hash('crc32', microtime()), 0, 6));
        }

        return $candidate;
    }

    private function toNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = (string) $value;

        return $value === '' ? null : $value;
    }

    /**
     * @param list<mixed> $values
     *
     * @return list<string>
     */
    private function normaliseList(array $values): array
    {
        $normalised = [];
        foreach ($values as $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $normalised[] = (string) $value;
        }

        return $normalised;
    }
}
