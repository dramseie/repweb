<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/json-imports')]
class JsonImportController extends AbstractController
{
    public function __construct(private Connection $db) {}

    // GET /api/json-imports/options
    #[Route('/options', name: 'json_import_options', methods: ['GET'])]
    public function options(): JsonResponse
    {
        $rows = $this->db->fetchAllAssociative(
            "SELECT ji_source AS source, ji_key AS `key`, ji_ts AS ts
             FROM nifi.json_import
             ORDER BY ji_source, ji_key"
        );

        return $this->json(['items' => $rows]);
    }

    // GET /api/json-imports/{source}/{key}
    #[Route('/{source}/{key}', name: 'json_import_get', methods: ['GET'])]
    public function getOne(string $source, string $key): Response
    {
        $row = $this->db->fetchAssociative(
            "SELECT ji_source, ji_key, ji_json, ji_ts
             FROM nifi.json_import
             WHERE ji_source = ? AND ji_key = ?",
            [$source, $key]
        );

        if (!$row) {
            return new JsonResponse(['error' => 'Not found'], 404);
        }

        $payload = $row['ji_json']; // LONGBLOB returned as string by DBAL
        $decoded = null;
        if ($payload !== null && $payload !== '') {
            $decoded = json_decode($payload, true);
        }

        if (json_last_error() === JSON_ERROR_NONE && $decoded !== null) {
            return $this->json([
                'source' => $row['ji_source'],
                'key'    => $row['ji_key'],
                'ts'     => $row['ji_ts'],
                'json'   => $decoded,
            ]);
        }

        return new Response($payload ?? '', 200, [
            'Content-Type' => 'application/json; charset=utf-8'
        ]);
    }
}
