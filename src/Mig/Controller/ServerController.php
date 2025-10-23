<?php
namespace App\Mig\Controller;

use App\Mig\Http\ApiResponse;
use App\Mig\Service\FeatureToggle;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/mig/containers/{containerId}/servers')]
class ServerController extends AbstractController
{
    public function __construct(private readonly FeatureToggle $ft) {}

    #[Route('', name: 'mig_server_list', methods: ['GET'])]
    public function list(string $containerId, Request $r) { if(!$this->ft->mig()) return ApiResponse::err('Migration Manager disabled',503);
        $app = $r->query->get('app');
        return ApiResponse::ok(['container'=>$containerId,'filter_app'=>$app,'items'=>[]]); }

    #[Route('', name: 'mig_server_add', methods: ['POST'])]
    public function add(string $containerId, Request $r) { if(!$this->ft->mig()) return ApiResponse::err('Migration Manager disabled',503);
        $p = json_decode($r->getContent()?:'{}', true);
        return ApiResponse::ok(['container'=>$containerId,'added'=>$p]); }

    #[Route('/{id}', name: 'mig_server_update', methods: ['PATCH','PUT'])]
    public function update(string $containerId, string $id, Request $r) { if(!$this->ft->mig()) return ApiResponse::err('Migration Manager disabled',503);
        return ApiResponse::ok(['container'=>$containerId,'id'=>$id,'patched'=>json_decode($r->getContent()?:'{}', true)]); }

    #[Route('/{id}', name: 'mig_server_delete', methods: ['DELETE'])]
    public function delete(string $containerId, string $id) { if(!$this->ft->mig()) return ApiResponse::err('Migration Manager disabled',503);
        return ApiResponse::ok(['deleted'=>$id]); }
}
