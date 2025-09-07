<?php
namespace App\Controller\Geo;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/api/geo', name: 'api_geo_')]
class NominatimController extends AbstractController
{
    public function __construct(private HttpClientInterface $http) {}

    #[Route('/search', name: 'search', methods: ['GET'])]
    public function search(Request $req): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $q = trim((string)$req->query->get('q', ''));
        if ($q === '') return $this->json([]);

        $res = $this->http->request('GET', 'https://nominatim.openstreetmap.org/search', [
            'query' => [
                'q' => $q,
                'format' => 'jsonv2',
                'addressdetails' => 1,
                'limit' => 8,
            ],
            'headers' => ['User-Agent' => 'repweb/1.0 (admin@yourdomain)'],
        ]);
        return $this->json($res->toArray(false));
    }

    #[Route('/reverse', name: 'reverse', methods: ['GET'])]
    public function reverse(Request $req): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $lat = $req->query->get('lat');
        $lon = $req->query->get('lon');
        if ($lat === null || $lon === null) return $this->json(['error'=>'missing lat/lon'], 400);

        $res = $this->http->request('GET', 'https://nominatim.openstreetmap.org/reverse', [
            'query' => [
                'lat' => $lat, 'lon' => $lon,
                'format' => 'jsonv2',
                'addressdetails' => 1,
            ],
            'headers' => ['User-Agent' => 'repweb/1.0 (admin@yourdomain)'],
        ]);
        return $this->json($res->toArray(false));
    }
}
