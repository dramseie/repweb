<?php
namespace App\Mig\Http;

use Symfony\Component\HttpFoundation\JsonResponse;

final class ApiResponse
{
    public static function ok(array $data = [], int $status = 200): JsonResponse
    {
        return new JsonResponse(['ok' => true, 'data' => $data], $status);
    }
    public static function err(string $msg, int $status = 400, array $meta=[]): JsonResponse
    {
        return new JsonResponse(['ok' => false, 'error' => $msg, 'meta'=>$meta], $status);
    }
}
