<?php

namespace App\Service\Discovery;

use App\Entity\Discovery\DiscoveryApplication;
use App\Entity\Discovery\DiscoveryApplicationResponse;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class DiscoveryAnswerCloner
{
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityManagerInterface $em
    ) {
    }

    public function ensureResponseExists(DiscoveryApplication $application): int
    {
        if (!$application->getId()) {
            $this->em->flush();
        }
        $appId = $application->getId();
        if (!$appId) {
            throw new BadRequestHttpException('Application must be persisted before creating a response');
        }
        $existing = $this->connection->fetchOne(
            'SELECT response_id FROM discovery_application_response WHERE application_id = ? ORDER BY created_at DESC LIMIT 1',
            [$appId]
        );
        if ($existing) {
            return (int) $existing;
        }

        $questionnaireId = $application->getQuestionnaireId();
        if (!$questionnaireId) {
            throw new BadRequestHttpException('Questionnaire identifier missing for this application');
        }

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $status = 'in_progress';

        $this->connection->insert('qw_response', [
            'questionnaire_id' => $questionnaireId,
            'status' => $status,
            'started_at' => $now,
            'submitted_by_user_id' => null,
            'submitted_at' => null,
            'approved_at' => null,
            'rejected_at' => null,
        ], [
            ParameterType::INTEGER,
            ParameterType::STRING,
            ParameterType::STRING,
            ParameterType::NULL,
            ParameterType::NULL,
            ParameterType::NULL,
            ParameterType::NULL,
        ]);

        $responseId = (int) $this->connection->lastInsertId();

        $responseEntity = $this->getOrCreateResponseEntity($application, $responseId);
        $responseEntity->setStatus($status);
        $this->em->persist($responseEntity);

        return $responseId;
    }

    public function cloneAnswers(DiscoveryApplication $source, DiscoveryApplication $target): array
    {
        if ($source->getQuestionnaireId() !== $target->getQuestionnaireId()) {
            throw new BadRequestHttpException('Source and target applications must share the same questionnaire');
        }

        $sourceResponseId = $this->ensureResponseExists($source);
        $targetResponseId = $this->ensureResponseExists($target);

        $answers = $this->connection->fetchAllAssociative(
            'SELECT item_id, field_id, value_text, value_json FROM qw_answer WHERE response_id = ?',
            [$sourceResponseId]
        );

        $attachments = $this->connection->fetchAllAssociative(
            'SELECT item_id, field_id, storage_path, original_name, mime_type, size_bytes, created_at FROM qw_attachment WHERE response_id = ?',
            [$sourceResponseId]
        );

        $this->connection->executeStatement('DELETE FROM qw_answer WHERE response_id = ?', [$targetResponseId]);
        $this->connection->executeStatement('DELETE FROM qw_attachment WHERE response_id = ?', [$targetResponseId]);

        $timestamp = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        foreach ($answers as $row) {
            $this->connection->insert('qw_answer', [
                'response_id' => $targetResponseId,
                'item_id' => $row['item_id'],
                'field_id' => $row['field_id'],
                'value_text' => $row['value_text'],
                'value_json' => $row['value_json'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }

        foreach ($attachments as $row) {
            $this->connection->insert('qw_attachment', [
                'response_id' => $targetResponseId,
                'item_id' => $row['item_id'],
                'field_id' => $row['field_id'],
                'storage_path' => $row['storage_path'],
                'original_name' => $row['original_name'],
                'mime_type' => $row['mime_type'],
                'size_bytes' => $row['size_bytes'],
                'created_at' => $row['created_at'],
            ]);
        }

        $targetResponse = $this->getOrCreateResponseEntity($target, $targetResponseId);
        $targetResponse->setStatus('in_progress');
        $targetResponse->setClonedFromApplicationId($source->getId());
        $targetResponse->setSnapshot([
            'clonedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'sourceApplicationId' => $source->getId(),
            'sourceResponseId' => $sourceResponseId,
            'answersCopied' => count($answers),
            'attachmentsCopied' => count($attachments),
        ]);
        $this->em->persist($targetResponse);

        return [
            'ok' => true,
            'answersCopied' => count($answers),
            'attachmentsCopied' => count($attachments),
            'sourceResponseId' => $sourceResponseId,
            'targetResponseId' => $targetResponseId,
        ];
    }

    private function getOrCreateResponseEntity(DiscoveryApplication $application, int $responseId): DiscoveryApplicationResponse
    {
        foreach ($application->getResponses() as $response) {
            if ($response->getResponseId() === $responseId) {
                return $response;
            }
        }

        $response = new DiscoveryApplicationResponse();
        $response->setApplication($application);
        $response->setResponseId($responseId);
        $application->addResponse($response);
        $this->em->persist($response);
        return $response;
    }
}
