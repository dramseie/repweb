<?php

namespace App\Tests\Functional\Api;

use App\Entity\Issue;
use App\Entity\SmartsheetProject;
use App\Entity\SmartsheetTask;
use App\Enum\IssuePriority;
use App\Enum\IssueSeverity;
use App\Enum\IssueStatus;
use App\Enum\IssueType;
use App\Tests\Functional\ApiWebTestCase;
use Symfony\Component\HttpFoundation\Response;

class IssueControllerTest extends ApiWebTestCase
{
    public function testListIssuesReturnsPersistedIssue(): void
    {
        $reporter = $this->createUser('reporter@example.com');

        $issue = (new Issue())
            ->setIssueNumber('ISSUE-001')
            ->setTitle('Existing issue')
            ->setDescription('Already tracked.')
            ->setIssueType(IssueType::Bug)
            ->setPriority(IssuePriority::Medium)
            ->setSeverity(IssueSeverity::Major)
            ->setStatus(IssueStatus::Open)
            ->setReporter($reporter);

        $this->entityManager->persist($issue);
        $this->entityManager->flush();

        $this->client->request('GET', '/api/issues');

        $this->assertResponseIsSuccessful();

        $payload = $this->jsonResponseArray();
        $this->assertSame(1, $payload['total']);
        $this->assertCount(1, $payload['items']);
        $this->assertSame('ISSUE-001', $payload['items'][0]['issueNumber']);
    }

    public function testCreateIssueRequiresMandatoryFields(): void
    {
        $this->client->request(
            'POST',
            '/api/issues',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $payload = $this->jsonResponseArray();
        $this->assertSame('Invalid request payload.', $payload['message']);
        $this->assertArrayHasKey('errors', $payload);
    }

    public function testCreateIssuePersistsEntity(): void
    {
        $reporter = $this->createUser('creator@example.com');

        $this->client->request(
            'POST',
            '/api/issues',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'issueNumber' => 'ISSUE-100',
                'title' => 'Created through API',
                'issueType' => 'bug',
                'priority' => 'high',
                'severity' => 'critical',
                'status' => 'new',
                'reporterId' => $reporter->getId(),
                'labels' => [
                    [
                        'name' => 'backend',
                        'color' => '#ff0000',
                    ],
                ],
                'estimatedHours' => 4.5,
                'description' => 'End-to-end coverage for create flow.',
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $payload = $this->jsonResponseArray();
        $this->assertSame('ISSUE-100', $payload['issueNumber']);
        $this->assertSame('Created through API', $payload['title']);

        $issue = $this->entityManager->getRepository(Issue::class)->findOneBy(['issueNumber' => 'ISSUE-100']);
        $this->assertNotNull($issue);
        $this->assertSame('Created through API', $issue->getTitle());
        $this->assertSame(['backend'], $issue->getLabels());
        $this->assertSame(IssuePriority::High, $issue->getPriority());
    }

    public function testShowIssueReturns404WhenMissing(): void
    {
        $this->client->request('GET', '/api/issues/9999');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $payload = $this->jsonResponseArray();
        $this->assertSame('Issue not found.', $payload['message']);
    }

    public function testLinkExternalSmartsheetTaskAssociatesProject(): void
    {
        $reporter = $this->createUser('linker@example.com');

        $issue = (new Issue())
            ->setIssueNumber('ISSUE-EXTERNAL-001')
            ->setTitle('Needs external link')
            ->setDescription('Link to Smartsheet task')
            ->setIssueType(IssueType::Task)
            ->setPriority(IssuePriority::High)
            ->setSeverity(IssueSeverity::Minor)
            ->setStatus(IssueStatus::Open)
            ->setReporter($reporter);

        $project = (new SmartsheetProject())
            ->setSmartsheetSheetId(123456)
            ->setProjectName('Project Plan')
            ->setProjectCode('PRJ-PLAN');

        $task = (new SmartsheetTask())
            ->setSmartsheetRowId(654321)
            ->setTaskName('Design phase')
            ->setTaskNumber('TASK-01')
            ->setProject($project);

        $this->entityManager->persist($project);
        $this->entityManager->persist($task);
        $this->entityManager->persist($issue);
        $this->entityManager->flush();

        $this->client->request(
            'PUT',
            sprintf('/api/issues/%d/external-link', $issue->getId()),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'source' => 'smartsheet',
                'taskId' => $task->getId(),
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertResponseIsSuccessful();

        $payload = $this->jsonResponseArray();
        $this->assertSame('Issue linked to Smartsheet source.', $payload['message']);
        $this->assertSame($task->getId(), $payload['issue']['task']['id']);
        $this->assertSame($project->getId(), $payload['issue']['project']['id']);

        $this->entityManager->refresh($issue);
        $this->assertSame($task->getId(), $issue->getTask()?->getId());
        $this->assertSame($project->getId(), $issue->getProject()?->getId());
    }

    public function testLinkExternalSmartsheetTaskCanBeCleared(): void
    {
        $reporter = $this->createUser('unlinker@example.com');

        $project = (new SmartsheetProject())
            ->setSmartsheetSheetId(222222)
            ->setProjectName('Existing Project')
            ->setProjectCode('PRJ-EXIST');

        $task = (new SmartsheetTask())
            ->setSmartsheetRowId(333333)
            ->setTaskName('Follow-up task')
            ->setTaskNumber('TASK-99')
            ->setProject($project);

        $issue = (new Issue())
            ->setIssueNumber('ISSUE-EXTERNAL-002')
            ->setTitle('Already linked issue')
            ->setDescription('Must unlink')
            ->setIssueType(IssueType::Bug)
            ->setPriority(IssuePriority::Medium)
            ->setSeverity(IssueSeverity::Major)
            ->setStatus(IssueStatus::Open)
            ->setReporter($reporter)
            ->setProject($project)
            ->setTask($task);

        $this->entityManager->persist($project);
        $this->entityManager->persist($task);
        $this->entityManager->persist($issue);
        $this->entityManager->flush();

        $this->client->request(
            'PUT',
            sprintf('/api/issues/%d/external-link', $issue->getId()),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'unlink' => true,
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertResponseIsSuccessful();

        $payload = $this->jsonResponseArray();
        $this->assertSame('Issue external link cleared.', $payload['message']);
        $this->assertNull($payload['issue']['task'] ?? null);
        $this->assertNull($payload['issue']['project'] ?? null);

        $this->entityManager->refresh($issue);
        $this->assertNull($issue->getTask());
        $this->assertNull($issue->getProject());
    }
}
