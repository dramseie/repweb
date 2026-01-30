<?php

namespace App\Tests\Functional\Api;

use App\Entity\Issue;
use App\Entity\IssueAttachment;
use App\Enum\IssuePriority;
use App\Enum\IssueSeverity;
use App\Enum\IssueStatus;
use App\Enum\IssueType;
use App\Tests\Functional\ApiWebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class IssueAttachmentControllerTest extends ApiWebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->clearAttachmentStorage();
    }

    protected function tearDown(): void
    {
        $this->clearAttachmentStorage();
        parent::tearDown();
    }

    public function testUploadAttachmentPersistsFile(): void
    {
        $issue = $this->createIssue();

        $payload = $this->uploadTestFile($issue, 'note.txt', 'example content');

        $this->assertSame('note.txt', $payload['originalFilename']);
        $this->assertArrayHasKey('downloadUrl', $payload);
        $this->assertNotEmpty($payload['downloadUrl']);

        $attachment = $this->entityManager->find(IssueAttachment::class, $payload['id']);
        $this->assertInstanceOf(IssueAttachment::class, $attachment);
        $this->assertSame($issue->getId(), $attachment->getIssue()?->getId());
        $this->assertSame($this->authenticatedUser->getId(), $attachment->getUser()?->getId());

        $absolutePath = $this->attachmentAbsolutePath($payload['filepath']);
        $this->assertFileExists($absolutePath);

        $this->client->request('GET', sprintf('/api/issues/%d/attachments', $issue->getId()));
        $this->assertResponseIsSuccessful();

        $list = $this->jsonResponseArray();
        $this->assertCount(1, $list);
        $this->assertSame($payload['id'], $list[0]['id']);

        @unlink($absolutePath);
    }

    public function testDeleteAttachmentRemovesFile(): void
    {
        $issue = $this->createIssue();

        $payload = $this->uploadTestFile($issue, 'delete-me.txt', 'delete content');
        $attachmentId = $payload['id'];
        $absolutePath = $this->attachmentAbsolutePath($payload['filepath']);

        $this->assertFileExists($absolutePath);

        $this->client->request(
            'DELETE',
            sprintf('/api/issues/%d/attachments/%d', $issue->getId(), $attachmentId)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertFileDoesNotExist($absolutePath);
        $this->assertNull($this->entityManager->find(IssueAttachment::class, $attachmentId));
    }

    private function createIssue(): Issue
    {
        $issue = (new Issue())
            ->setIssueNumber('ISSUE-' . uniqid())
            ->setTitle('Attachment test issue')
            ->setIssueType(IssueType::Bug)
            ->setPriority(IssuePriority::Medium)
            ->setSeverity(IssueSeverity::Major)
            ->setStatus(IssueStatus::Open)
            ->setReporter($this->authenticatedUser);

        $this->entityManager->persist($issue);
        $this->entityManager->flush();

        return $issue;
    }

    /**
     * @return array<string, mixed>
     */
    private function uploadTestFile(Issue $issue, string $filename, string $contents): array
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'att');
        if ($tempPath === false) {
            $this->fail('Unable to create temporary file.');
        }

        file_put_contents($tempPath, $contents);

        $uploadedFile = new UploadedFile($tempPath, $filename, 'text/plain', null, true);

        $this->client->request(
            'POST',
            sprintf('/api/issues/%d/attachments', $issue->getId()),
            server: ['CONTENT_TYPE' => 'multipart/form-data'],
            files: ['file' => $uploadedFile]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $payload = $this->jsonResponseArray();

        if (is_file($tempPath)) {
            @unlink($tempPath);
        }

        return $payload;
    }

    private function clearAttachmentStorage(): void
    {
        $root = $this->getStorageRoot();
        if (!is_dir($root)) {
            return;
        }

        $this->removeDirectory($root);
    }

    private function removeDirectory(string $path): void
    {
        $items = scandir($path) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($fullPath)) {
                $this->removeDirectory($fullPath);
            } else {
                @unlink($fullPath);
            }
        }

        @rmdir($path);
    }

    private function attachmentAbsolutePath(?string $relative): string
    {
        $relative = $relative ?? '';
        $relative = str_replace(['..', '\\'], ['', '/'], $relative);
        $relative = ltrim($relative, '/');

        return $this->getStorageRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    private function getStorageRoot(): string
    {
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');

        return $projectDir . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'issue_attachments';
    }
}
