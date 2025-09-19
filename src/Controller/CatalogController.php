<?php
// src/Controller/CatalogController.php
namespace App\Controller;

use App\Repository\ExtEavServicesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

final class CatalogController extends AbstractController
{
    public function __construct(private ExtEavServicesRepository $servicesRepo) {}

    #[Route('/api/catalog/services', methods: ['GET'])]
    public function services(Request $r): JsonResponse
    {
        $tenant = $r->query->get('tenant', 'cmdb');
        $rows = $this->servicesRepo->listServices($tenant);

        return $this->json(['tenant' => $tenant, 'services' => $rows]);
    }

    #[Route('/api/catalog/service/{code}', methods: ['GET'])]
    public function serviceDetails(string $code, Request $r): JsonResponse
    {
        $tenant = $r->query->get('tenant', 'cmdb');
        $row = $this->servicesRepo->getServiceDetails($tenant, $code);
        if (!$row) {
            return $this->json(['error' => "Service $code not found in tenant $tenant"], 404);
        }
        return $this->json($row);
    }
	
    #[Route('/api/catalog/rule/{ci}', methods: ['GET'])]
    public function ruleDetails(string $ci, Request $r): JsonResponse
    {
        $tenant = $r->query->get('tenant', 'cmdb');
        $rule = $this->servicesRepo->getRuleDetails($tenant, $ci);
        if (!$rule) {
            return $this->json(['error' => "Rule $ci not found in tenant $tenant"], 404);
        }
        return $this->json($rule);
    }
	
	#[Route('/api/catalog/countries', methods: ['GET'])]
	public function countries(Request $r): JsonResponse
	{
		$tenant = $r->query->get('tenant', 'loc');
		return $this->json([
			'tenant'    => $tenant,
			'countries' => $this->servicesRepo->listCountries($tenant),
		]);
	}
	
}
