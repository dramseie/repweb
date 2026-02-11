<?php

declare(strict_types=1);

namespace App\Controller\Api;

use DateInterval;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/smartsheet/task-calculator', name: 'api_smartsheet_task_calculator_')]
class SmartsheetTaskCalculatorController extends AbstractController
{
    private const MASTER_TABLE = 'nifi.smartsheet_master_data';
    private const NODE_TABLE = 'smartsheet_task_dependency_node';
    private const EDGE_TABLE = 'smartsheet_task_dependency_edge';
    private const RUN_TABLE = 'smartsheet_task_calc_run';
    private const RESULT_TABLE = 'smartsheet_task_calc_result';

    public function __construct(private readonly Connection $connection) {}

    #[Route('/filters', name: 'filters', methods: ['GET'])]
    public function filters(): JsonResponse
    {
        $sql = sprintf(
            "SELECT DISTINCT country, site_name
            FROM %s
            WHERE IFNULL(phase, '') NOT IN ('Store','Country')
              AND country IS NOT NULL AND country <> ''
              AND site_name IS NOT NULL AND site_name <> ''
            ORDER BY country, site_name",
            self::MASTER_TABLE
        );
        $rows = $this->connection->fetchAllAssociative($sql);
        $countries = [];
        $sitesByCountry = [];
        foreach ($rows as $row) {
            $country = $row['country'];
            $site = $row['site_name'];
            if (!isset($sitesByCountry[$country])) {
                $sitesByCountry[$country] = [];
                $countries[] = $country;
            }
            $sitesByCountry[$country][] = $site;
        }
        foreach ($sitesByCountry as $country => $sites) {
            $sitesByCountry[$country] = array_values(array_unique($sites));
            sort($sitesByCountry[$country]);
        }
        sort($countries);

        return $this->json([
            'countries' => $countries,
            'sitesByCountry' => $sitesByCountry,
        ]);
    }

    #[Route('/run', name: 'run', methods: ['POST'])]
    public function run(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $workspaceId = (int) ($payload['workspaceId'] ?? 0);
        $country = trim((string) ($payload['country'] ?? ''));
        $siteName = trim((string) ($payload['siteName'] ?? ''));
        $dependencyType = $payload['dependencyType'] === 'start-to-start' ? 'start-to-start' : 'finish-to-start';

        if ($workspaceId <= 0) {
            return $this->json(['error' => 'workspaceId is required.'], 400);
        }

        $nodes = $this->connection->fetchAllAssociative(
            sprintf('SELECT id, task_name, duration FROM %s WHERE workspace_id = ?', self::NODE_TABLE),
            [$workspaceId]
        );
        if ($nodes === []) {
            return $this->json(['error' => 'No tasks found in this workspace.'], 400);
        }

        $nodeById = [];
        $durationByTask = [];
        $taskNames = [];
        foreach ($nodes as $node) {
            $nodeById[(int) $node['id']] = $node['task_name'];
            $durationByTask[$node['task_name']] = $node['duration'] !== null ? (int) $node['duration'] : 0;
            $taskNames[] = $node['task_name'];
        }
        $taskNames = array_values(array_unique($taskNames));

        $edges = $this->connection->fetchAllAssociative(
            sprintf('SELECT source_node_id, target_node_id FROM %s WHERE workspace_id = ?', self::EDGE_TABLE),
            [$workspaceId]
        );
        $dependencies = [];
        foreach ($edges as $edge) {
            $sourceTask = $nodeById[(int) $edge['source_node_id']] ?? null;
            $targetTask = $nodeById[(int) $edge['target_node_id']] ?? null;
            if (!$sourceTask || !$targetTask) {
                continue;
            }
            $dependencies[] = [$sourceTask, $targetTask];
        }

        $params = [$taskNames];
        $where = "IFNULL(phase, '') NOT IN ('Store','Country') AND task_name IN (?)";
        $types = [ArrayParameterType::STRING];
        if ($country !== '') {
            $where .= ' AND country = ?';
            $params[] = $country;
            $types[] = ParameterType::STRING;
        }
        if ($siteName !== '') {
            $where .= ' AND site_name = ?';
            $params[] = $siteName;
            $types[] = ParameterType::STRING;
        }

        $sql = sprintf(
            "SELECT country, site_name, task_name, start_date, end_date, row_num
            FROM %s
            WHERE %s",
            self::MASTER_TABLE,
            $where
        );

        $rows = $this->connection->fetchAllAssociative($sql, $params, $types);
        $latest = [];
        foreach ($rows as $row) {
            $key = $row['country'] . '||' . $row['site_name'] . '||' . $row['task_name'];
            if (!isset($latest[$key]) || (int) $row['row_num'] > (int) $latest[$key]['row_num']) {
                $latest[$key] = $row;
            }
        }

        $today = new DateTimeImmutable('today');
        $results = [];
        $sites = [];
        foreach ($latest as $row) {
            $siteKey = $row['country'] . '||' . $row['site_name'];
            $sites[$siteKey][] = $row;
        }

        $this->connection->beginTransaction();
        try {
            $this->connection->insert(self::RUN_TABLE, [
                'workspace_id' => $workspaceId,
                'country' => $country !== '' ? $country : null,
                'site_name' => $siteName !== '' ? $siteName : null,
                'dependency_type' => $dependencyType,
                'created_by' => $this->getUser()?->getUserIdentifier(),
            ]);
            $runId = (int) $this->connection->lastInsertId();

            foreach ($sites as $siteKey => $siteRows) {
                [$siteCountry, $siteNameValue] = explode('||', $siteKey, 2);
                $current = [];
                foreach ($siteRows as $row) {
                    $current[$row['task_name']] = [
                        'start' => $row['start_date'],
                        'end' => $row['end_date'],
                        'row_num' => $row['row_num'] ?? null,
                    ];
                }

                $proposedStart = [];
                $proposedEnd = [];

                foreach ($taskNames as $taskName) {
                    $currStart = $current[$taskName]['start'] ?? null;
                    $currEnd = $current[$taskName]['end'] ?? null;
                    $startDate = $currStart ? new DateTimeImmutable($currStart) : ($currEnd ? new DateTimeImmutable($currEnd) : $today);
                    $duration = $durationByTask[$taskName] ?? 0;
                    $proposedStart[$taskName] = $startDate;
                    $proposedEnd[$taskName] = $this->addWorkdays($startDate, $duration);
                }

                $maxIterations = count($taskNames) + 2;
                for ($i = 0; $i < $maxIterations; $i++) {
                    $changed = false;
                    foreach ($dependencies as [$sourceTask, $targetTask]) {
                        if (!isset($proposedStart[$sourceTask], $proposedStart[$targetTask])) {
                            continue;
                        }
                        $candidate = $dependencyType === 'start-to-start'
                            ? $proposedStart[$sourceTask]
                            : $this->addWorkdays($proposedEnd[$sourceTask], 1);
                        if ($candidate > $proposedStart[$targetTask]) {
                            $proposedStart[$targetTask] = $candidate;
                            $proposedEnd[$targetTask] = $this->addWorkdays($candidate, $durationByTask[$targetTask] ?? 0);
                            $changed = true;
                        }
                    }
                    if (!$changed) {
                        break;
                    }
                }

                foreach ($taskNames as $taskName) {
                    $currStart = $current[$taskName]['start'] ?? null;
                    $currEnd = $current[$taskName]['end'] ?? null;
                    $propStart = $proposedStart[$taskName] ?? null;
                    $propEnd = $proposedEnd[$taskName] ?? null;

                    $insertRow = [
                        'run_id' => $runId,
                        'workspace_id' => $workspaceId,
                        'country' => $siteCountry,
                        'site_name' => $siteNameValue,
                        'task_name' => $taskName,
                        'current_start' => $currStart,
                        'current_end' => $currEnd,
                        'proposed_start' => $propStart ? $propStart->format('Y-m-d') : null,
                        'proposed_end' => $propEnd ? $propEnd->format('Y-m-d') : null,
                    ];
                    $resultRow = $insertRow + ['row_num' => $current[$taskName]['row_num'] ?? null];

                    $this->connection->insert(self::RESULT_TABLE, $insertRow);
                    $results[] = $resultRow;
                }
            }

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            return $this->json(['error' => $e->getMessage()], 500);
        }

        usort($results, static function (array $left, array $right): int {
            $leftRow = $left['row_num'] ?? PHP_INT_MAX;
            $rightRow = $right['row_num'] ?? PHP_INT_MAX;
            if ($leftRow === $rightRow) {
                return strcmp((string) ($left['task_name'] ?? ''), (string) ($right['task_name'] ?? ''));
            }
            return $leftRow <=> $rightRow;
        });

        return $this->json(['runId' => $runId, 'items' => $results]);
    }

    private function addWorkdays(DateTimeImmutable $start, int $days): DateTimeImmutable
    {
        if ($days <= 0) {
            return $start;
        }
        $date = $start;
        $remaining = $days;
        while ($remaining > 0) {
            $date = $date->add(new DateInterval('P1D'));
            $weekday = (int) $date->format('N');
            if ($weekday >= 6) {
                continue;
            }
            $remaining--;
        }
        return $date;
    }
}
