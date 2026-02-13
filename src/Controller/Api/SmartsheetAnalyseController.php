<?php

namespace App\Controller\Api;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/smartsheet/analyse', name: 'api_smartsheet_analyse_')]
class SmartsheetAnalyseController extends AbstractController
{
    private const MASTER_TABLE = 'nifi.smartsheet_master_data';
    private const ALLOWED_DATE_FIELDS = ['Start_Date', 'End_Date'];
    private const SHEET_TABLE_CANDIDATES = [
        'nifi.smartsheet_sheets',
        'nifi.smartsteet_sheets',
    ];

    public function __construct(
        private readonly Connection $connection,
        private readonly RequestStack $requestStack
    ) {}

    private ?string $sheetTable = null;

    #[Route('/tasks', name: 'tasks', methods: ['GET'])]
    public function tasks(): JsonResponse
    {
        $rows = $this->connection->fetchFirstColumn(
            sprintf(
                "SELECT DISTINCT Task_Name FROM %s WHERE Task_Name IS NOT NULL AND Task_Name <> '' AND IFNULL(Phase,'') NOT IN ('Store', 'Country') ORDER BY Task_Name",
                self::MASTER_TABLE
            )
        );

        return $this->json([
            'items' => array_values(array_filter(array_map('strval', $rows))),
            'total' => count($rows),
        ]);
    }

    #[Route('/durations', name: 'durations', methods: ['GET'])]
    public function durations(): JsonResponse
    {
        $taskA = trim((string) $this->getRequestParameter('taskA'));
        $taskB = trim((string) $this->getRequestParameter('taskB'));
        $fieldA = (string) $this->getRequestParameter('fieldA', 'End_Date');
        $fieldB = (string) $this->getRequestParameter('fieldB', 'Start_Date');

        if ($taskA === '' || $taskB === '') {
            throw new BadRequestHttpException('Both taskA and taskB are required.');
        }

        if (!in_array($fieldA, self::ALLOWED_DATE_FIELDS, true) || !in_array($fieldB, self::ALLOWED_DATE_FIELDS, true)) {
            throw new BadRequestHttpException('fieldA and fieldB must be Start_Date or End_Date.');
        }

        $sql = sprintf(
            'SELECT a.`sheet_name`, a.`Site_ID`, a.`Site_Name`, a.`Task_Name` AS task_a, DATE(a.`%1$s`) AS date_a, a.`%%_complete` AS percent_complete_a, a.`Predecessors` AS predecessors_a, '
            . 'b.`Task_Name` AS task_b, DATE(b.`%2$s`) AS date_b, b.`%%_complete` AS percent_complete_b, b.`Predecessors` AS predecessors_b, '
            . 'DATEDIFF(b.`%2$s`, a.`%1$s`) AS days_difference '
            . 'FROM %3$s a '
            . 'INNER JOIN %3$s b ON a.`sheet_name` = b.`sheet_name` AND a.`Site_ID` = b.`Site_ID` '
            . 'WHERE a.`Task_Name` = :taskA AND b.`Task_Name` = :taskB',
            $fieldA,
            $fieldB,
            self::MASTER_TABLE
        );

        $rows = $this->connection->fetchAllAssociative($sql, [
            'taskA' => $taskA,
            'taskB' => $taskB,
        ]);

        if ($rows !== []) {
            $sheetLinks = $this->buildSheetLinkMap();
            if ($sheetLinks !== []) {
                foreach ($rows as &$row) {
                    $sheetName = (string) ($row['sheet_name'] ?? '');
                    $link = $sheetLinks[$sheetName] ?? null;
                    $row['sheet_link'] = $link !== null
                        ? sprintf(
                            '<a class="btn btn-sm btn-outline-success" href="%s" target="SmartSheet" rel="noopener noreferrer" title="Open in Smartsheet"><i class="fa-brands fa-smartsheet" aria-hidden="true"></i><span class="visually-hidden">Open</span></a>',
                            htmlspecialchars($link, ENT_QUOTES)
                        )
                        : '';
                }
                unset($row);
            }
        }

        $columns = array_keys($rows[0] ?? []);
        $columnMeta = array_map(fn (string $key) => [
            'key' => $key,
            'label' => $this->humanizeColumn($key),
        ], $columns);

        return $this->json([
            'columns' => $columnMeta,
            'items' => $rows,
            'total' => count($rows),
        ]);
    }

    private function sheetTable(): string
    {
        if ($this->sheetTable !== null) {
            return $this->sheetTable;
        }

        foreach (self::SHEET_TABLE_CANDIDATES as $table) {
            try {
                $this->connection->fetchOne(sprintf('SELECT 1 FROM %s LIMIT 1', $table));

                return $this->sheetTable = $table;
            } catch (\Throwable) {
                continue;
            }
        }

        throw new \RuntimeException('Unable to resolve Smartsheet sheets table.');
    }

    /**
     * @return array<string, string>
     */
    private function buildSheetLinkMap(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            sprintf('SELECT * FROM %s', $this->sheetTable())
        );

        if ($rows === []) {
            return [];
        }

        $nameColumn = null;
        $linkColumn = null;
        foreach ($rows as $row) {
            $nameColumn ??= $this->findColumn($row, ['sheet_name', 'name', 'title', 'label']);
            $linkColumn ??= $this->findColumn($row, ['permalink', 'url', 'link']);
            if ($nameColumn !== null && $linkColumn !== null) {
                break;
            }
        }

        if ($nameColumn === null || $linkColumn === null) {
            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row[$nameColumn] ?? ''));
            $link = trim((string) ($row[$linkColumn] ?? ''));
            if ($name === '' || $link === '') {
                continue;
            }
            $map[$name] = $link;
        }

        return $map;
    }

    private function getRequestParameter(string $name, mixed $default = null): mixed
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return $default;
        }

        return $request->query->get($name, $default);
    }

    private function humanizeColumn(string $key): string
    {
        $spaced = preg_replace('/[_-]+/', ' ', $key);
        $label = ucwords($spaced ?? $key);

        return trim($label) !== '' ? trim($label) : $key;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $candidates
     */
    private function findColumn(array $row, array $candidates): ?string
    {
        if ($row === []) {
            return null;
        }

        foreach ($candidates as $candidate) {
            foreach ($row as $key => $value) {
                if (strcasecmp($key, $candidate) === 0) {
                    return $key;
                }
            }
        }

        return null;
    }
}
