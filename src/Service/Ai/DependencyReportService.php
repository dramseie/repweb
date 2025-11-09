<?php

namespace App\Service\Ai;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Coordinates text-to-SQL planning, execution, and summarisation for NiFi CMDB lookups.
 */
final class DependencyReportService
{
    private const SYSTEM_PROMPT = <<<PROMPT
You are a senior engineering analyst producing secure SQL for MariaDB 10.11.
Return strict JSON with the following keys:
  - sql (string): one single SELECT statement – no comments, no temp tables.
  - reasoning (string): short explanation of the approach.
  - result_focus (array of strings): column names that should be highlighted in the answer.
  - suggested_followup (string|null): optional follow-up question for the user.
Rules:
  • Use only the tables provided in the schema section.
  • Never use SELECT * – always project explicit columns.
  • Prefer safe filters (LIKE, =, IN) and always add LIMIT 200 unless the user explicitly asks for more.
  • Do not mutate data. Reject destructive verbs (INSERT, UPDATE, DELETE, DROP, ALTER, TRUNCATE, GRANT, REVOKE).
  • Use table aliases for readability.
  • Optimise for clarity; dependency reports usually join FAT_CMDB with FAT_CMDBRelAll, optionally FAT_CMDBPath.
PROMPT;

    private const SCHEMA_SUMMARY = <<<SCHEMA
Table FAT_CMDB (core CI catalogue – text columns unless stated):
  CI_UCMDB_ID, CI_ID (unique string), CI_Name, CI_Search_Code, CI_Type_Short (e.g. AppCI, TechCI),
  CI_Subtype_Short_Name, CI_Status, CI_Main_Service, CI_Server_Type, CI_Environment, CI_Lifecycle,
  CI_Business_Criticality (smallint), CI_Version, CI_Fully_Qualified_Name, CI_Primary_IP_Address,
  CI_Description (longtext), CI_Provider_Organisation, CI_Managed_By_Name, CI_Owner fields,…
Table FAT_CMDBRelAll (flat dependency edges):
  ParentCI, ParentType, ParentStatus, Relation, ChildCI, ChildType, ChildStatus.
  Parent/Child values reference FAT_CMDB.CI_Search_Code / CI_Name combinations.
Table FAT_CMDBPath (precomputed traversal paths):
  ci_search_code_path_start, ci_type_path_start, ci_status_path_start,
  ci_search_code_path_end, ci_type_path_end, ci_status_path_end,
  ci_path (text with arrows), ci_path_direction_flag, ci_path_level, path_status.
Primary usage:
  • Join FAT_CMDBRelAll to FAT_CMDB on CI_Search_Code or CI_ID to enrich relation endpoints.
  • Use FAT_CMDBPath when hierarchical depth is required (ci_path gives the chain).
SCHEMA;

    private const DEFAULT_PREVIEW_COLUMNS = [
        'CI_Name',
        'CI_ID',
        'CI_Type_Short',
        'CI_Subtype_Short_Name',
        'CI_Status',
        'Relation',
        'ParentCI',
        'ParentType',
        'ChildCI',
        'ChildType',
        'ci_path',
        'ci_path_level',
    ];

    public function __construct(
        private readonly MistralClient $client,
        #[Autowire(service: 'doctrine.dbal.nifi_connection')]
        private readonly Connection $nifiConnection,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function generateReport(string $question): array
    {
        $question = trim($question);
        if ($question === '') {
            throw new \InvalidArgumentException('Please provide a question for the dependency report.');
        }

        $plan = $this->planSql($question);
        $sql = $this->extractSql($plan);
        $safeSql = $this->enforceSqlGuards($sql);

        [$rows, $columns] = $this->runQuery($safeSql);
        $summary = $this->summariseResults($question, $safeSql, $rows, $plan['result_focus'] ?? []);

        return [
            'question' => $question,
            'sql' => $safeSql,
            'rowCount' => count($rows),
            'columns' => $columns,
            'rows' => $rows,
            'analysis' => [
                'reasoning' => $plan['reasoning'] ?? '',
                'summary' => $summary,
                'suggestedFollowUp' => $plan['suggested_followup'] ?? null,
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function planSql(string $question): array
    {
        $messages = [
            ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
            ['role' => 'user', 'content' => $this->buildPlanningPrompt($question)],
        ];

        return $this->client->chatJson($messages, [
            'temperature' => 0.1,
            'top_p' => 0.9,
            'max_output_tokens' => 800,
        ]);
    }

    private function buildPlanningPrompt(string $question): string
    {
        return sprintf(
            "Database schema (MariaDB 10.11):\n%s\n\nUser question:\n\"%s\".\n\nProduce valid SQL that answers the question while keeping the result set manageable.",
            self::SCHEMA_SUMMARY,
            $question,
        );
    }

    /**
     * @param array<string,mixed> $plan
     */
    private function extractSql(array $plan): string
    {
        $sql = $plan['sql'] ?? '';
        if (!\is_string($sql) || trim($sql) === '') {
            throw new \RuntimeException('The AI did not return a SQL statement.');
        }

        return trim($sql);
    }

    private function enforceSqlGuards(string $sql): string
    {
        $trimmed = trim($sql);
        $trimmed = rtrim($trimmed, ';');

        if (!preg_match('/^select\s/i', $trimmed)) {
            throw new \RuntimeException('Only SELECT statements are allowed.');
        }

        if (preg_match('/\b(insert|update|delete|drop|alter|truncate|create|grant|revoke)\b/i', $trimmed)) {
            throw new \RuntimeException('Destructive SQL keywords are not permitted.');
        }

        if (str_contains($trimmed, '--') || str_contains($trimmed, '/*')) {
            throw new \RuntimeException('Inline SQL comments are not permitted.');
        }

        $upperSql = strtoupper($trimmed);
        if (!str_contains($upperSql, 'FAT_CMDB')) {
            throw new \RuntimeException('Query must target the FAT_CMDB dataset.');
        }

        if (preg_match('/;.+/s', $sql)) {
            throw new \RuntimeException('Multiple SQL statements are not allowed.');
        }

        if (!preg_match('/\blimit\s+\d+/i', $trimmed)) {
            $trimmed .= ' LIMIT 200';
        }

        return $trimmed;
    }

    /**
     * @return array{0: array<int,array<string,mixed>>, 1: array<int,string>}
     */
    private function runQuery(string $sql): array
    {
        try {
            $rows = $this->nifiConnection->executeQuery($sql)->fetchAllAssociative();
        } catch (DbalException $e) {
            throw new \RuntimeException('NiFi database query failed: '.$e->getMessage(), previous: $e);
        }

        $columns = [];
        if ($rows !== []) {
            $columns = array_keys($rows[0]);
        }

        return [$rows, $columns];
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array<int,string> $focusColumns
     */
    private function summariseResults(string $question, string $sql, array $rows, array $focusColumns): string
    {
        if ($rows === []) {
            return 'No rows matched the filters. Consider broadening the search or adjusting the criteria.';
        }

        $preview = $this->buildPreviewRows($rows, $focusColumns);
        $payload = [
            'question' => $question,
            'sql' => $sql,
            'row_count' => count($rows),
            'preview_rows' => $preview,
        ];

        try {
            $payloadJson = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $payloadJson = json_encode($payload) ?: '{}';
        }

        $messages = [
            ['role' => 'system', 'content' => 'You summarise SQL query results for infrastructure analysts. Keep it under 140 words, highlight notable dependencies, and mention if data is truncated.'],
            ['role' => 'user', 'content' => $payloadJson],
        ];

        return $this->client->chat($messages, [
            'temperature' => 0.3,
            'max_output_tokens' => 600,
        ]);
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array<int,string> $focusColumns
     * @return array<int,array<string,mixed>>
     */
    private function buildPreviewRows(array $rows, array $focusColumns): array
    {
        $maxRows = 40;
        $columnsToKeep = self::DEFAULT_PREVIEW_COLUMNS;
        foreach ($focusColumns as $column) {
            if ($column !== '' && !\in_array($column, $columnsToKeep, true)) {
                $columnsToKeep[] = $column;
            }
        }

        $preview = [];
        foreach ($rows as $row) {
            $filtered = [];
            foreach ($columnsToKeep as $column) {
                foreach ($row as $key => $value) {
                    if (strcasecmp($key, $column) === 0 && $value !== null && $value !== '') {
                        $filtered[$key] = $value;
                    }
                }
            }

            if ($filtered === []) {
                $filtered = array_slice($row, 0, 6, true);
            }

            $preview[] = $filtered;
            if (count($preview) >= $maxRows) {
                break;
            }
        }

        return $preview;
    }
}
