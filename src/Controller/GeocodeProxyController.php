<?php
declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/api/geocode', name: 'api_geocode_')]
final class GeocodeProxyController extends AbstractController
{
    public function __construct(private HttpClientInterface $http) {}

    private function headers(): array
    {
        // Put a *real* contact you control (Nominatim policy requires it)
        return [
            'User-Agent'       => 'repweb-pos/1.0 (+https://repweb.local; contact: admin@ramseier.com)',
            'Accept'           => 'application/json',
            'Accept-Language'  => 'fr,en;q=0.8',
        ];
    }

    // GET /api/geocode/search?q=...&limit=10
    #[Route('/search', name: 'search', methods: ['GET'])]
    public function search(Request $req): JsonResponse
    {
        $q = trim((string) $req->query->get('q', ''));
        if ($q === '') {
            return $this->json([]);
        }

        $params = [
            'q'              => $q,
            'format'         => 'jsonv2',
            'addressdetails' => 1,
            'limit'          => (int) $req->query->get('limit', 10),
            // Bias around Basel/Sundgau
            'countrycodes'   => 'fr,ch,de',
            'dedupe'         => 1,
            'namedetails'    => 0,
            'extratags'      => 0,
        ];

        try {
            $resp = $this->http->request('GET', 'https://nominatim.openstreetmap.org/search', [
                'query'   => $params,
                'headers' => $this->headers(),
            ]);
            $data = $resp->toArray(false);
        } catch (\Throwable) {
            $data = [];
        }

        return $this->json($data);
    }

    // GET /api/geocode/reverse?lat=..&lon=..
    #[Route('/reverse', name: 'reverse', methods: ['GET'])]
    public function reverse(Request $req): JsonResponse
    {
        $lat = $req->query->get('lat');
        $lon = $req->query->get('lon');

        if ($lat === null || $lon === null) {
            return $this->json(['error' => 'missing lat/lon'], 400);
        }

        try {
            $resp = $this->http->request('GET', 'https://nominatim.openstreetmap.org/reverse', [
                'query'   => [
                    'lat'            => $lat,
                    'lon'            => $lon,
                    'format'         => 'jsonv2',
                    'addressdetails' => 1,
                ],
                'headers' => $this->headers(),
            ]);
            $data = $resp->toArray(false);
        } catch (\Throwable) {
            $data = ['error' => 'reverse failed'];
        }

        return $this->json($data);
    }
}
