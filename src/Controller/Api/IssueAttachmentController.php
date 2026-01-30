<?php

namespace App\Controller\Api;

use App\Entity\Issue;
use App\Entity\IssueAttachment;
use App\Entity\User;
use App\Repository\IssueRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/issues', name: 'api_issue_attachments_')]
class IssueAttachmentController extends AbstractController
{
    public function __construct(
        private readonly IssueRepository $issues,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/{issueId}/attachments', name: 'list', methods: ['GET'], requirements: ['issueId' => '\d+'])]
    public function list(int $issueId): JsonResponse
    {
        $issue = $this->issues->find($issueId);
        if (!$issue instanceof Issue) {
            return $this->json(['message' => 'Issue not found.'], Response::HTTP_NOT_FOUND);
        }

        $attachments = $issue->getAttachments()->toArray();
        usort(
            $attachments,
            static fn (IssueAttachment $a, IssueAttachment $b): int => ($b->getUploadedAt()?->getTimestamp() ?? 0) <=> ($a->getUploadedAt()?->getTimestamp() ?? 0)
        );

        return $this->json(array_map(fn (IssueAttachment $attachment): array => $this->serializeAttachment($attachment), $attachments));
    }

    #[Route('/{issueId}/attachments', name: 'upload', methods: ['POST'], requirements: ['issueId' => '\d+'])]
    public function upload(int $issueId, Request $request): JsonResponse
    {
        $issue = $this->issues->find($issueId);
        if (!$issue instanceof Issue) {
            return $this->json(['message' => 'Issue not found.'], Response::HTTP_NOT_FOUND);
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Authentication required.'], Response::HTTP_FORBIDDEN);
        }

        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            return $this->json(['message' => 'file upload is required.'], Response::HTTP_BAD_REQUEST);
        }

        if (!$file->isValid()) {
            return $this->json(['message' => 'Uploaded file is invalid.'], Response::HTTP_BAD_REQUEST);
        }

        $fileSize = $file->getSize();
        if (is_int($fileSize) && $fileSize > 50 * 1024 * 1024) {
            return $this->json(['message' => 'Maximum allowed file size is 50 MB.'], Response::HTTP_BAD_REQUEST);
        }

        $originalName = $file->getClientOriginalName() ?: $file->getFilename() ?: 'attachment';
        $safeOriginalName = $this->normaliseOriginalFilename($originalName);
        $extension = $this->determineExtension($file, $safeOriginalName);

        try {
            $storedFilename = $this->buildStoredFilename($issue->getId() ?? 0, $extension);
        } catch (\Exception $exception) {
            return $this->json(['message' => 'Unable to generate filename.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $relativeDir = sprintf('issue-%d', $issue->getId());
        $targetDir = $this->storageRoot() . DIRECTORY_SEPARATOR . $relativeDir;

        if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            return $this->json(['message' => 'Unable to prepare upload directory.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $relativePath = $relativeDir . '/' . $storedFilename;
        $absolutePath = $this->resolveAbsolutePath($relativePath);

        try {
            $file->move(dirname($absolutePath), basename($absolutePath));
        } catch (FileException $exception) {
            return $this->json(['message' => 'Failed to store uploaded file.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $attachment = new IssueAttachment();
        $attachment->setIssue($issue);
        $attachment->setUser($user);
        $attachment->setFilename($storedFilename);
        $attachment->setOriginalFilename($safeOriginalName);
        $attachment->setFilepath($relativePath);
        $attachment->setMimeType($file->getClientMimeType() ?: $file->getMimeType() ?: null);
        $attachment->setFileSize($fileSize !== false ? (int) $fileSize : null);
        $attachment->setUploadedAt(new \DateTimeImmutable());

        $issue->addAttachment($attachment);
        $this->entityManager->persist($attachment);
        $this->entityManager->flush();

        return $this->json($this->serializeAttachment($attachment), Response::HTTP_CREATED);
    }

    #[Route(
        '/{issueId}/attachments/{attachmentId}',
        name: 'delete',
        methods: ['DELETE'],
        requirements: ['issueId' => '\d+', 'attachmentId' => '\d+']
    )]
    public function delete(int $issueId, int $attachmentId): JsonResponse
    {
        $issue = $this->issues->find($issueId);
        if (!$issue instanceof Issue) {
            return $this->json(['message' => 'Issue not found.'], Response::HTTP_NOT_FOUND);
        }

        $attachment = $this->entityManager->find(IssueAttachment::class, $attachmentId);
        if (!$attachment instanceof IssueAttachment || $attachment->getIssue()?->getId() !== $issueId) {
            return $this->json(['message' => 'Attachment not found.'], Response::HTTP_NOT_FOUND);
        }

        $absolutePath = $this->resolveAbsolutePath($attachment->getFilepath() ?? '');
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }

        $issue->removeAttachment($attachment);
        $this->entityManager->remove($attachment);
        $this->entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(
        '/{issueId}/attachments/{attachmentId}/download',
        name: 'download',
        methods: ['GET'],
        requirements: ['issueId' => '\d+', 'attachmentId' => '\d+']
    )]
    public function download(int $issueId, int $attachmentId): Response
    {
        $issue = $this->issues->find($issueId);
        if (!$issue instanceof Issue) {
            return $this->json(['message' => 'Issue not found.'], Response::HTTP_NOT_FOUND);
        }

        $attachment = $this->entityManager->find(IssueAttachment::class, $attachmentId);
        if (!$attachment instanceof IssueAttachment || $attachment->getIssue()?->getId() !== $issueId) {
            return $this->json(['message' => 'Attachment not found.'], Response::HTTP_NOT_FOUND);
        }

        $absolutePath = $this->resolveAbsolutePath($attachment->getFilepath() ?? '');
        if (!is_file($absolutePath)) {
            return $this->json(['message' => 'Stored file is missing.'], Response::HTTP_NOT_FOUND);
        }

        $response = new BinaryFileResponse($absolutePath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $attachment->getOriginalFilename() ?: $attachment->getFilename() ?: 'attachment'
        );

        if ($attachment->getMimeType()) {
            $response->headers->set('Content-Type', (string) $attachment->getMimeType());
        }

        if ($attachment->getFileSize() !== null) {
            $response->headers->set('Content-Length', (string) $attachment->getFileSize());
        }

        return $response;
    }

    private function serializeAttachment(IssueAttachment $attachment): array
    {
        $issueId = $attachment->getIssue()?->getId();

        return [
            'id' => $attachment->getId(),
            'filename' => $attachment->getFilename(),
            'originalFilename' => $attachment->getOriginalFilename(),
            'filepath' => $attachment->getFilepath(),
            'mimeType' => $attachment->getMimeType(),
            'fileSize' => $attachment->getFileSize(),
            'uploadedAt' => $attachment->getUploadedAt()?->format(DATE_ATOM),
            'user' => $attachment->getUser() instanceof User
                ? [
                    'id' => $attachment->getUser()->getId(),
                    'email' => $attachment->getUser()->getEmail(),
                ]
                : null,
            'downloadUrl' => ($issueId !== null && $attachment->getId() !== null)
                ? sprintf('/api/issues/%d/attachments/%d/download', $issueId, $attachment->getId())
                : null,
        ];
    }

    private function storageRoot(): string
    {
        return $this->getParameter('kernel.project_dir') . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'issue_attachments';
    }

    private function resolveAbsolutePath(string $relativePath): string
    {
        $sanitised = ltrim(str_replace(['\\', '..'], ['/', ''], $relativePath), '/');
        $normalised = str_replace('/', DIRECTORY_SEPARATOR, $sanitised);

        return $this->storageRoot() . DIRECTORY_SEPARATOR . $normalised;
    }

    private function normaliseOriginalFilename(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return 'attachment';
        }

        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        if ($ascii !== false && $ascii !== null) {
            $name = $ascii;
        }

        $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?? 'attachment';
        $name = trim($name, '._-');

        if ($name === '') {
            $name = 'attachment';
        }

        if (strlen($name) > 150) {
            $name = substr($name, 0, 150);
        }

        return $name;
    }

    private function determineExtension(UploadedFile $file, string $safeOriginalName): string
    {
        $extension = strtolower((string) pathinfo($safeOriginalName, PATHINFO_EXTENSION));
        if ($extension === '') {
            $guessed = strtolower((string) $file->guessExtension());
            if ($guessed !== '') {
                $extension = preg_replace('/[^a-z0-9]/', '', $guessed) ?? '';
            }
        }

        if ($extension === '') {
            return 'bin';
        }

        return preg_replace('/[^a-z0-9]/', '', $extension) ?: 'bin';
    }

    private function buildStoredFilename(int $issueId, string $extension): string
    {
        $random = bin2hex(random_bytes(8));
        $ext = $extension !== '' ? $extension : 'bin';

        return sprintf('%d-%s.%s', $issueId, $random, $ext);
    }
}
