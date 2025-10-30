<?php

namespace App\Service\Discovery;

use App\Entity\Discovery\DiscoveryApplication;
use App\Entity\Discovery\DiscoveryApplicationResponse;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Aggregates questionnaire runtime data and persists answers for a given CI key.
 * Currently supports discovery applications (AppCI) and falls back to generic CI lookups
 * via the qw_ci/qw_questionnaire tables when an application match is not found.
 */
class DiscoveryQuestionnaireRuntimeService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Connection $connection,
        private readonly DiscoveryAnswerCloner $answerCloner,
    ) {
    }

    /**
     * Retrieve questionnaire, fields and the active response for a CI key.
     *
     * @return array<string,mixed>
     */
    public function load(string $ciKey): array
    {
        $context = $this->resolveContext($ciKey);
        return $this->buildRuntimePayload($context);
    }

    /**
     * Persist answers for the CI key and optionally transition the response state.
     *
     * @param array<int, array<string, mixed>> $answers
     * @return array<string, mixed>
     */
    public function save(string $ciKey, array $answers, string $status): array
    {
        $context = $this->resolveContext($ciKey);

        if (!in_array($status, ['in_progress', 'submitted'], true)) {
            throw new BadRequestHttpException('Unsupported status value');
        }

        $responseId = $context['responseId'];

        $this->connection->transactional(function (Connection $conn) use ($answers, $responseId, $status): void {
            $conn->executeStatement('DELETE FROM qw_answer WHERE response_id = ?', [$responseId]);

            $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

            foreach ($answers as $answer) {
                $itemId = isset($answer['itemId']) ? (int) $answer['itemId'] : null;
                if (!$itemId) {
                    continue; // skip malformed entry
                }

                $fieldId = $answer['fieldId'] ?? null;
                $value = $answer['value'] ?? null;

                $valueText = null;
                $valueJson = null;

                if (is_array($value) || is_object($value) || is_bool($value)) {
                    try {
                        $valueJson = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                    } catch (JsonException $e) {
                        throw new BadRequestHttpException('Unable to encode answer payload', $e);
                    }
                } elseif ($value !== null && $value !== '') {
                    $valueText = (string) $value;
                }

                $types = [
                    ParameterType::INTEGER,
                    ParameterType::INTEGER,
                    $fieldId !== null ? ParameterType::INTEGER : ParameterType::NULL,
                    $valueText !== null ? ParameterType::STRING : ParameterType::NULL,
                    $valueJson !== null ? ParameterType::STRING : ParameterType::NULL,
                    ParameterType::STRING,
                    ParameterType::STRING,
                ];

                $conn->insert('qw_answer', [
                    'response_id' => $responseId,
                    'item_id' => $itemId,
                    'field_id' => $fieldId,
                    'value_text' => $valueText,
                    'value_json' => $valueJson,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $types);
            }

            $update = ['status' => $status];
            if ($status === 'submitted') {
                $update['submitted_at'] = $now;
            } else {
                $update['submitted_at'] = null;
            }
            $conn->update('qw_response', $update, ['id' => $responseId]);
        });

        // Mirror the status into the Doctrine entity for discovery applications.
        if ($context['mode'] === 'application') {
            /** @var DiscoveryApplication $application */
            $application = $context['application'];
            $responseEntity = $this->locateResponseEntity($application, $context['responseId']);
            if ($responseEntity) {
                $responseEntity->setStatus($status);
                $responseEntity->setSnapshot([
                    'savedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
                    'status' => $status,
                ]);
                $this->em->persist($responseEntity);
                $this->em->flush();
            }
        }

        return $this->buildRuntimePayload($context);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRuntimePayload(array $context): array
    {
        $questionnaireId = $context['questionnaireId'];
        $questionnaire = $this->connection->fetchAssociative(
            'SELECT id, title, description FROM qw_questionnaire WHERE id = :id',
            ['id' => $questionnaireId]
        );
        if (!$questionnaire) {
            throw new NotFoundHttpException('Questionnaire not found');
        }

        $itemsRows = $this->connection->fetchAllAssociative(
            'SELECT id, parent_id, type, title, help, sort, outline, required, visible_when
             FROM qw_item WHERE questionnaire_id = :qid ORDER BY sort, id',
            ['qid' => $questionnaireId]
        );

        $items = [];
        $itemIds = [];
        foreach ($itemsRows as $row) {
            $itemIds[] = (int) $row['id'];
            $items[] = [
                'id' => (int) $row['id'],
                'parentId' => $row['parent_id'] !== null ? (int) $row['parent_id'] : null,
                'type' => $row['type'],
                'title' => $row['title'],
                'help' => $row['help'],
                'sort' => (int) $row['sort'],
                'outline' => $row['outline'],
                'required' => (bool) $row['required'],
                'visibleWhen' => $row['visible_when'] ? json_decode($row['visible_when'], true) : null,
            ];
        }

        $fields = [];
        if ($itemIds) {
            $fieldsRows = $this->connection->fetchAllAssociative(
                'SELECT id, item_id, ui_type, placeholder, default_value, min_value, max_value, step_value, options_json
                 FROM qw_field WHERE item_id IN (?) ORDER BY id',
                [$itemIds],
                [ArrayParameterType::INTEGER]
            );
            foreach ($fieldsRows as $row) {
                $options = $row['options_json'] ? json_decode($row['options_json'], true) : null;
                $fields[] = [
                    'id' => (int) $row['id'],
                    'itemId' => (int) $row['item_id'],
                    'uiType' => $row['ui_type'],
                    'placeholder' => $row['placeholder'],
                    'defaultValue' => $row['default_value'],
                    'minValue' => $row['min_value'] !== null ? (float) $row['min_value'] : null,
                    'maxValue' => $row['max_value'] !== null ? (float) $row['max_value'] : null,
                    'stepValue' => $row['step_value'] !== null ? (float) $row['step_value'] : null,
                    'label' => $options['label'] ?? null,
                    'options' => $options['options'] ?? null,
                    'help' => $options['help'] ?? null,
                ];
            }
        }

        $answersRows = $this->connection->fetchAllAssociative(
            'SELECT item_id, field_id, value_text, value_json FROM qw_answer WHERE response_id = :rid',
            ['rid' => $context['responseId']]
        );
        $answers = [];
        foreach ($answersRows as $row) {
            $answers[] = [
                'itemId' => (int) $row['item_id'],
                'fieldId' => $row['field_id'] !== null ? (int) $row['field_id'] : null,
                'valueText' => $row['value_text'],
                'valueJson' => $row['value_json'] ? json_decode($row['value_json'], true) : null,
            ];
        }

        $responseRow = $this->connection->fetchAssociative(
            'SELECT id, status, started_at, submitted_at FROM qw_response WHERE id = :rid',
            ['rid' => $context['responseId']]
        );

        return [
            'ci' => [
                'key' => $context['ciKey'],
                'name' => $context['ciName'],
                'tenantId' => $context['tenantId'],
                'type' => $context['mode'],
                'application' => $context['mode'] === 'application' ? $this->normalizeApplication($context['application']) : null,
            ],
            'questionnaire' => [
                'id' => (int) $questionnaire['id'],
                'title' => $questionnaire['title'],
                'description' => $questionnaire['description'],
            ],
            'items' => $items,
            'fields' => $fields,
            'response' => [
                'id' => (int) $responseRow['id'],
                'status' => $responseRow['status'],
                'startedAt' => $responseRow['started_at'],
                'submittedAt' => $responseRow['submitted_at'],
                'answers' => $answers,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeApplication(?DiscoveryApplication $application): ?array
    {
        if (!$application) {
            return null;
        }

        return [
            'id' => $application->getId(),
            'appCi' => $application->getAppCi(),
            'appName' => $application->getAppName(),
            'environment' => $application->getEnvironment(),
            'status' => $application->getStatus(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveContext(string $ciKey): array
    {
        $appRepo = $this->em->getRepository(DiscoveryApplication::class);
        /** @var DiscoveryApplication|null $application */
        $application = $appRepo->findOneBy(['appCi' => $ciKey]);

        if ($application && $application->getQuestionnaireId()) {
            $responseId = $this->answerCloner->ensureResponseExists($application);

            return [
                'mode' => 'application',
                'application' => $application,
                'questionnaireId' => (int) $application->getQuestionnaireId(),
                'responseId' => $responseId,
                'ciKey' => $ciKey,
                'tenantId' => $application->getTenantId(),
                'ciName' => $application->getAppName(),
            ];
        }

        $row = $this->connection->fetchAssociative(
            'SELECT c.id AS ci_id, c.ci_name, c.tenant_id, q.id AS questionnaire_id
             FROM qw_ci c
             INNER JOIN qw_questionnaire q ON q.ci_id = c.id
             WHERE c.ci_key = :ciKey
             ORDER BY q.updated_at DESC
             LIMIT 1',
            ['ciKey' => $ciKey]
        );

        if (!$row) {
            throw new NotFoundHttpException('CI not found or no questionnaire assigned');
        }

        $questionnaireId = (int) $row['questionnaire_id'];
        $responseId = $this->ensureGenericResponse($questionnaireId);

        return [
            'mode' => 'ci',
            'application' => null,
            'questionnaireId' => $questionnaireId,
            'responseId' => $responseId,
            'ciKey' => $ciKey,
            'tenantId' => (int) $row['tenant_id'],
            'ciName' => $row['ci_name'],
        ];
    }

    private function ensureGenericResponse(int $questionnaireId): int
    {
        $existing = $this->connection->fetchOne(
            'SELECT id FROM qw_response
             WHERE questionnaire_id = :qid
             ORDER BY (status = "in_progress") DESC, id DESC
             LIMIT 1',
            ['qid' => $questionnaireId]
        );

        if ($existing) {
            return (int) $existing;
        }

        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->connection->insert('qw_response', [
            'questionnaire_id' => $questionnaireId,
            'status' => 'in_progress',
            'started_at' => $now,
            'submitted_at' => null,
            'approved_at' => null,
            'rejected_at' => null,
            'submitted_by_user_id' => null,
        ], [
            ParameterType::INTEGER,
            ParameterType::STRING,
            ParameterType::STRING,
            ParameterType::NULL,
            ParameterType::NULL,
            ParameterType::NULL,
            ParameterType::NULL,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function locateResponseEntity(DiscoveryApplication $application, int $responseId): ?DiscoveryApplicationResponse
    {
        foreach ($application->getResponses() as $response) {
            if ($response->getResponseId() === $responseId) {
                return $response;
            }
        }

        return null;
    }
}
