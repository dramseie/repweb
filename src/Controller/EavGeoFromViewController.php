<?php
namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

final class EavGeoFromViewController
{
    public function __construct(private Connection $db) {}

    #[Route('/api/eav/geo/view', name: 'api_eav_geo_view', methods: ['GET'])]
    public function __invoke(Request $req): JsonResponse
    {
        // Optional filters: status, city, country, search
        $status  = $req->query->get('status');      // e.g. 'active'
        $city    = $req->query->get('city');
        $country = $req->query->get('country');
        $q       = $req->query->get('q');           // matches name/address/city/country

        // Build SQL safely
        $w = ["lat IS NOT NULL", "`long` IS NOT NULL"]; // your view stores them as strings
        $p = [];

        if ($status)  { $w[] = "status = :status";   $p['status']  = $status; }
        if ($city)    { $w[] = "city = :city";       $p['city']    = $city; }
        if ($country) { $w[] = "country = :country"; $p['country'] = $country; }
        if ($q) {
            $w[] = "(name LIKE :q OR address LIKE :q OR city LIKE :q OR country LIKE :q)";
            $p['q'] = "%$q%";
        }

        // Only keep rows where lat/long are valid numbers
        $w[] = "lat REGEXP '^-?[0-9]+(\\.[0-9]+)?$'";
        $w[] = "`long` REGEXP '^-?[0-9]+(\\.[0-9]+)?$'";

        $where = $w ? ('WHERE ' . implode(' AND ', $w)) : '';

        $sql = "
            SELECT
              ci,
              name,
              status,
              address,
              city,
              country,
              `desc`,
              icon,
              CAST(lat  AS DECIMAL(10,7)) AS lat,
              CAST(`long` AS DECIMAL(10,7)) AS lng
            FROM `ext_eav`.`v_loc-geoloc`
            $where
        ";

        $rows = $this->db->fetchAllAssociative($sql, $p);

        // Build GeoJSON
        $features = [];
        foreach ($rows as $r) {
            $props = [
                'ci'      => $r['ci'],
                'name'    => $r['name'],
                'status'  => $r['status'],
                'address' => $r['address'],
                'city'    => $r['city'],
                'country' => $r['country'],
                'desc'    => $r['desc'],
                'icon'    => $r['icon'],
            ];
            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [(float)$r['lng'], (float)$r['lat']],
                ],
                'properties' => $props,
            ];
        }

        return new JsonResponse([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }
}
