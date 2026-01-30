<?php

namespace App\Tests\Functional\Api;

use App\Entity\SmartsheetProject;
use App\Entity\SmartsheetSyncLog;
use App\Entity\SmartsheetTask;
use App\Enum\SmartsheetProjectStatus;
use App\Service\SmartsheetService;
use App\Tests\Functional\ApiWebTestCase;
use Symfony\Component\HttpFoundation\Response;

class SmartsheetControllerTest extends ApiWebTestCase
{
    public function testListProjectsReturnsPersistedRows(): void
    {
        $project = $this->createProject('Project Alpha', 101, 'ALPHA');
        $this->entityManager->persist($project);
        $this->entityManager->flush();

        $this->client->request('GET', '/api/smartsheet/projects');

        $this->assertResponseIsSuccessful();

        $payload = $this->jsonResponseArray();
        $this->assertSame(1, $payload['total']);
        $this->assertSame('Project Alpha', $payload['items'][0]['projectName']);
    }

    public function testShowProjectReturnsTasks(): void
    {
        $project = $this->createProject('Project Beta', 202, 'BETA');

        $task = (new SmartsheetTask())
            ->setSmartsheetRowId(5401)
            ->setTaskName('Initial Setup')
            ->setTaskNumber('TASK-1')
            ->setStatus('In Progress')
            ->setProgress(35)
            ->setProject($project);

        $this->entityManager->persist($project);
        $this->entityManager->persist($task);
        $this->entityManager->flush();

        $this->client->request('GET', '/api/smartsheet/projects/' . $project->getId());

        $this->assertResponseIsSuccessful();

        $payload = $this->jsonResponseArray();
        $this->assertSame('Project Beta', $payload['project']['projectName']);
        $this->assertCount(1, $payload['tasks']);
        $this->assertSame('Initial Setup', $payload['tasks'][0]['taskName']);
    }

    public function testShowProjectReturns404WhenMissing(): void
    {
        $this->client->request('GET', '/api/smartsheet/projects/9999');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $payload = $this->jsonResponseArray();
        $this->assertSame('Project not found.', $payload['message']);
    }

    public function testSyncProjectsUsesServiceStub(): void
    {
        $mock = $this->createMock(SmartsheetService::class);
        $mock->expects($this->once())
            ->method('syncProjects')
            ->willReturn(5);

        self::getContainer()->set(SmartsheetService::class, $mock);

        $this->client->request('POST', '/api/smartsheet/projects/sync');

        $this->assertResponseIsSuccessful();

        $payload = $this->jsonResponseArray();
        $this->assertSame(5, $payload['projectsSynced']);
    }

    public function testSyncLogsReturnsRecentEntries(): void
    {
        $log = (new SmartsheetSyncLog())
            ->setSyncType('projects')
            ->setStatus('completed')
            ->setRecordsProcessed(8)
            ->setRecordsAdded(6)
            ->setRecordsUpdated(2)
            ->setStartedAt(new \DateTimeImmutable('-2 minutes'))
            ->setCompletedAt(new \DateTimeImmutable('-1 minute'));

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        $this->client->request('GET', '/api/smartsheet/logs?limit=10');

        $this->assertResponseIsSuccessful();

        $payload = $this->jsonResponseArray();
        $this->assertSame(1, $payload['total']);
        $this->assertSame('projects', $payload['items'][0]['syncType']);
    }

    private function createProject(string $name, int $sheetId, string $code): SmartsheetProject
    {
        $project = (new SmartsheetProject())
            ->setProjectName($name)
            ->setProjectCode($code)
            ->setSmartsheetSheetId($sheetId)
            ->setStatus(SmartsheetProjectStatus::Active);

        return $project;
    }
}
