<?php

namespace App\Controller\Api;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;

#[Route('/api/smartsheet/pivot', name: 'api_smartsheet_pivot_')]
class SmartsheetPivotController extends AbstractController
{
    private const WORKSPACE_TABLE_CANDIDATES = [
        'nifi.smartsheet_workspaces',
        'nifi.smartsteet_workspaces',
    ];

    private const SHEET_TABLE_CANDIDATES = [
        'nifi.smartsheet_sheets',
        'nifi.smartsteet_sheets',
    ];

    public function __construct(private readonly Connection $connection) {}

    private ?string $workspaceTable = null;
    private ?string $sheetTable = null;

    #[Route('/workspaces', name: 'workspaces', methods: ['GET'])]
    public function workspaces(): JsonResponse
    {
        $rows = $this->connection->fetchAllAssociative(
            sprintf('SELECT * FROM %s', $this->workspaceTable())
        );

        $items = array_values(array_filter(array_map(fn (array $row) => $this->formatWorkspace($row), $rows), fn (array $item): bool => $item['id'] !== null));
        usort($items, fn (array $a, array $b): int => strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));

        return $this->json([
            'items' => $items,
            'total' => count($items),
        ]);
    }

    #[Route('/workspaces/{workspaceId}/sheets', name: 'sheets', methods: ['GET'])]
    public function sheets(string $workspaceId): JsonResponse
    {
        if (trim($workspaceId) === '') {
            throw new BadRequestHttpException('workspaceId is required.');
        }

        $table = $this->sheetTable();
        $rows = [];

        try {
            $rows = $this->connection->fetchAllAssociative(
                sprintf('SELECT * FROM %s WHERE workspace_id = :workspaceId', $table),
                ['workspaceId' => $workspaceId]
            );
        } catch (Throwable) {
            // Fallback: fetch all rows and filter in PHP when column names differ.
            $allRows = $this->connection->fetchAllAssociative(sprintf('SELECT * FROM %s', $table));
            $workspaceColumn = null;
            foreach ($allRows as $row) {
                $workspaceColumn ??= $this->findColumn($row, ['workspace_id', 'workspaceId', 'workspaceID', 'workspace']);
                if ($workspaceColumn !== null) {
                    break;
                }
            }
            if ($workspaceColumn === null) {
                return $this->json([
                    'message' => 'Unable to resolve workspace column on Smartsheet sheets table.',
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $rows = array_values(array_filter($allRows, static function (array $row) use ($workspaceColumn, $workspaceId): bool {
                return (string) ($row[$workspaceColumn] ?? '') === (string) $workspaceId;
            }));
        }

        $items = array_values(array_filter(array_map(fn (array $row) => $this->formatSheet($row), $rows), fn (array $item): bool => $item['id'] !== null));
        usort($items, fn (array $a, array $b): int => strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));

        return $this->json([
            'items' => $items,
            'total' => count($items),
        ]);
    }

    #[Route('/sheets/{sheetId}/data', name: 'sheet_data', methods: ['GET'])]
    public function sheetData(string $sheetId): JsonResponse
    {
        if (trim($sheetId) === '') {
            throw new BadRequestHttpException('sheetId is required.');
        }

        try {
            $rows = $this->fetchPivotRows($sheetId);
        } catch (Throwable $exception) {
            return $this->json([
                'message' => 'Failed to execute Smartsheet pivot stored procedure.',
                'details' => $exception->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
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

    /** @return array<int, array<string, mixed>> */
    private function consumeResult(Result $result): array
    {
        try {
            $rows = $result->fetchAllAssociative();
        } finally {
            $result->free();
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchPivotRows(string $sheetId): array
    {
        $native = $this->connection->getNativeConnection();
        if ($native instanceof \PDO) {
            $statement = $native->prepare('CALL nifi.sp_smartsheet_pivot_all(:sheetId)');
            if ($statement === false) {
                throw new \RuntimeException('Unable to prepare Smartsheet pivot statement.');
            }

            $statement->bindValue(':sheetId', $sheetId);
            if ($statement->execute() === false) {
                $errorInfo = $statement->errorInfo();
                throw new \RuntimeException($errorInfo[2] ?? 'Smartsheet pivot execution failed.');
            }

            $rows = $statement->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Drain any additional result sets exposed by the stored procedure to keep MySQL happy.
            while ($statement->nextRowset()) {
                $statement->fetchAll(\PDO::FETCH_ASSOC);
            }

            $statement->closeCursor();

            return $rows;
        }

        if ($native instanceof \mysqli) {
            $statement = $native->prepare('CALL nifi.sp_smartsheet_pivot_all(?)');
            if ($statement === false) {
                throw new \RuntimeException('Unable to prepare Smartsheet pivot statement.');
            }

            if ($statement->bind_param('s', $sheetId) === false) {
                $error = $statement->error ?? 'Failed to bind Smartsheet sheet id parameter.';
                $statement->close();
                throw new \RuntimeException($error);
            }

            if ($statement->execute() === false) {
                $error = $statement->error ?? 'Smartsheet pivot execution failed.';
                $statement->close();
                throw new \RuntimeException($error);
            }

            $rows = [];

            do {
                $result = $statement->get_result();
                if ($result instanceof \mysqli_result) {
                    if ($rows === []) {
                        $rows = $result->fetch_all(MYSQLI_ASSOC) ?: [];
                    }
                    $result->free();
                }
            } while ($statement->more_results() && $statement->next_result());

            $statement->close();

            return $rows;
        }

        $result = $this->connection->executeQuery(
            'CALL nifi.sp_smartsheet_pivot_all(:sheetId)',
            ['sheetId' => $sheetId]
        );

        return $this->consumeResult($result);
    }

    private function workspaceTable(): string
    {
        if ($this->workspaceTable !== null) {
            return $this->workspaceTable;
        }

        foreach (self::WORKSPACE_TABLE_CANDIDATES as $table) {
            try {
                $this->connection->fetchOne(sprintf('SELECT 1 FROM %s LIMIT 1', $table));

                return $this->workspaceTable = $table;
            } catch (Throwable) {
                continue;
            }
        }

        throw new \RuntimeException('Unable to resolve Smartsheet workspace table.');
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
            } catch (Throwable) {
                continue;
            }
        }

        throw new \RuntimeException('Unable to resolve Smartsheet sheets table.');
    }

    /**
     * @param array<string, mixed> $row
     */
    private function formatWorkspace(array $row): array
    {
        $idColumn = $this->findColumn($row, ['workspace_id', 'smartsheet_workspace_id', 'id', 'workspaceId']);
        $nameColumn = $this->findColumn($row, ['workspace_name', 'name', 'label']);

        $id = $idColumn ? $row[$idColumn] : null;
        $name = $nameColumn ? $row[$nameColumn] : null;

        return [
            'id' => $id,
            'name' => $name ?? ($id !== null ? sprintf('Workspace %s', $id) : 'Workspace'),
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function formatSheet(array $row): array
    {
        $idColumn = $this->findColumn($row, ['sheet_id', 'smartsheet_sheet_id', 'id', 'sheetId']);
        $nameColumn = $this->findColumn($row, ['sheet_name', 'name', 'title', 'label']);

        $id = $idColumn ? $row[$idColumn] : null;
        $name = $nameColumn ? $row[$nameColumn] : null;

        return [
            'id' => $id,
            'name' => $name ?? ($id !== null ? sprintf('Sheet %s', $id) : 'Sheet'),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string>    $candidates
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

    private function humanizeColumn(string $key): string
    {
        $spaced = preg_replace('/[_-]+/', ' ', $key);
        $label = ucwords($spaced ?? $key);

        return trim($label) !== '' ? trim($label) : $key;
    }
}
