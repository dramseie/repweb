<?php
namespace App\Mig\Controller;

use App\Mig\Entity\Container;
use App\Mig\Http\ApiResponse;
use App\Mig\Repository\ContainerRepository;
use App\Mig\Service\FeatureToggle;
use Doctrine\ORM\EntityManagerInterface as EM;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/mig/waves/{waveId}/containers')]
class ContainerController extends AbstractController
{
    public function __construct(
        private readonly FeatureToggle $ft,
        private readonly ContainerRepository $repo,
        private readonly EM $em
    ) {}

    #[Route('', name: 'mig_container_list', methods: ['GET'])]
    public function list(string $waveId)
    {
        if (!$this->ft->mig()) return ApiResponse::err('Migration Manager disabled', 503);
        $rows = $this->repo->createQueryBuilder('c')
            ->andWhere('c.waveId = :wid')->setParameter('wid',$waveId)
            ->orderBy('c.name','ASC')->getQuery()->getArrayResult();
        return ApiResponse::ok(['items'=>$rows]);
    }

    #[Route('', name: 'mig_container_create', methods: ['POST'])]
    public function create(string $waveId, Request $r)
    {
        if (!$this->ft->mig()) return ApiResponse::err('Migration Manager disabled', 503);
        $p = json_decode($r->getContent() ?: '{}', true);

        $e = new Container();
        $e->setWaveId($waveId);
        $e->setName($p['name'] ?? 'Container');
        $e->setNotes($p['notes'] ?? null);
        $this->repo->save($e, true);
        return ApiResponse::ok(['id'=>$e->getId()]);
    }

    #[Route('/{id}', name: 'mig_container_update', methods: ['PATCH','PUT'])]
    public function update(string $waveId, string $id, Request $r)
    {
        if (!$this->ft->mig()) return ApiResponse::err('Migration Manager disabled', 503);
        $e = $this->repo->find($id);
        if (!$e || $e->getWaveId()!==$waveId) return ApiResponse::err('Not found', 404);
        $p = json_decode($r->getContent() ?: '{}', true);
        if (isset($p['name']))  $e->setName($p['name']);
        if (array_key_exists('notes',$p)) $e->setNotes($p['notes']);
        $this->repo->save($e, true);
        return ApiResponse::ok(['id'=>$e->getId()]);
    }

    #[Route('/{id}', name: 'mig_container_delete', methods: ['DELETE'])]
    public function delete(string $waveId, string $id)
    {
        if (!$this->ft->mig()) return ApiResponse::err('Migration Manager disabled', 503);
        $e = $this->repo->find($id);
        if (!$e || $e->getWaveId()!==$waveId) return ApiResponse::err('Not found', 404);
        $this->repo->remove($e, true);
        return ApiResponse::ok(['deleted'=>$id]);
    }
}
