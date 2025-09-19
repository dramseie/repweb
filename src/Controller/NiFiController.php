<?php
namespace App\Controller;

use App\Service\NiFiClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class NiFiController extends AbstractController
{
    #[Route('/api/nifi/process-groups/status', name: 'api_nifi_pg_status', methods: ['GET'])]
    public function status(
        CacheInterface $cache,
        HttpClientInterface $http,
        #[Autowire(param: 'kernel.environment')] string $env,
    ): JsonResponse {
        $client = NiFiClient::fromEnv($http);

        // small cache to avoid hammering NiFi when widget polls
        $ttl = ($env === 'prod') ? 5 : 2; // seconds
        $data = $cache->get('nifi.pg.status', function (ItemInterface $item) use ($client, $ttl) {
            $item->expiresAfter($ttl);
            return $client->collectAllWithStatus('root');
        });

        return $this->json([
            'ts' => time(),
            'items' => $data,
        ]);
    }
}
