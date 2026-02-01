<?php

namespace App\Controller\Api;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/smartsheet/master-data', name: 'api_smartsheet_master_data_')]
class SmartsheetMasterDataController extends AbstractController
{
    private const MASTER_TABLE = 'nifi.smartsheet_master_data';
    private const DEFAULT_PAGE_SIZE = 1000;
    private const MAX_PAGE_SIZE = 10000;

    public function __construct(private readonly Connection $connection) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        if ($response = $this->authorize($request)) {
            return $response;
        }

        $pageSize = (int) ($request->query->get('pageSize') ?? $request->query->get('limit') ?? self::DEFAULT_PAGE_SIZE);
        $pageSize = max(1, min(self::MAX_PAGE_SIZE, $pageSize));

        $page = (int) $request->query->get('page', 1);
        $page = max(1, $page);

        $offset = (int) $request->query->get('offset', ($page - 1) * $pageSize);
        $offset = max(0, $offset);

        $total = (int) $this->connection->fetchOne(
            sprintf('SELECT COUNT(*) FROM %s', self::MASTER_TABLE)
        );

        $rows = $this->connection->fetchAllAssociative(
            sprintf('SELECT * FROM %s LIMIT :limit OFFSET :offset', self::MASTER_TABLE),
            ['limit' => $pageSize, 'offset' => $offset],
            ['limit' => ParameterType::INTEGER, 'offset' => ParameterType::INTEGER]
        );

        return $this->json([
            'page' => $page,
            'pageSize' => $pageSize,
            'offset' => $offset,
            'total' => $total,
            'totalPages' => $pageSize > 0 ? (int) ceil($total / $pageSize) : 0,
            'items' => $rows,
        ]);
    }

    private function authorize(Request $request): ?JsonResponse
    {
        if ($this->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            return null;
        }

        $token = $this->extractToken($request);
        $expected = $_ENV['SMARTSHEET_MASTER_API_TOKEN'] ?? ($_ENV['REPORT_API_KEY'] ?? null);

        if ($expected && $token !== '' && hash_equals($expected, $token)) {
            return null;
        }

        return new JsonResponse(['error' => 'Unauthorized'], 401);
    }

    private function extractToken(Request $request): string
    {
        $authHeader = (string) $request->headers->get('Authorization', '');
        if (str_starts_with($authHeader, 'Bearer ')) {
            return trim(substr($authHeader, 7));
        }

        return (string) ($request->query->get('token')
            ?: $request->headers->get('X-Api-Token')
            ?: $request->headers->get('X-Api-Key')
            ?: $request->query->get('api_key')
            ?: '');
    }
}
