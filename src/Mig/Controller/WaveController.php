<?php
namespace App\Mig\Controller;

use App\Mig\Entity\Wave;
use App\Mig\Http\ApiResponse;
use App\Mig\Repository\WaveRepository;
use App\Mig\Service\FeatureToggle;
use Doctrine\ORM\EntityManagerInterface as EM;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/mig/projects/{projectId}/waves')]
class WaveController extends AbstractController
{
    public function __construct(
        private readonly FeatureToggle $ft,
        private readonly WaveRepository $repo,
        private readonly EM $em
    ) {}

    #[Route('', name: 'mig_wave_list', methods: ['GET'])]
    public function list(string $projectId)
    {
        if (!$this->ft->mig()) return ApiResponse::err('Migration Manager disabled', 503);
        $rows = $this->repo->createQueryBuilder('w')
            ->andWhere('w.projectId = :pid')->setParameter('pid',$projectId)
            ->orderBy('w.startAt','ASC')->getQuery()->getArrayResult();
        return ApiResponse::ok(['items'=>$rows]);
    }

    #[Route('', name: 'mig_wave_create', methods: ['POST'])]
    public function create(string $projectId, Request $r)
    {
        if (!$this->ft->mig()) return ApiResponse::err('Migration Manager disabled', 503);
        $p = json_decode($r->getContent() ?: '{}', true);

        $e = new Wave();
        $e->setProjectId($projectId);
        $e->setName($p['name'] ?? 'Wave');
        $e->setStatus($p['status'] ?? 'Planned');
        if (!empty($p['start_at'])) $e->setStartAt(new \DateTimeImmutable($p['start_at']));
        if (!empty($p['end_at']))   $e->setEndAt(new \DateTimeImmutable($p['end_at']));

        $this->repo->save($e, true);
        return ApiResponse::ok(['id'=>$e->getId()]);
    }

    #[Route('/{id}', name: 'mig_wave_get', methods: ['GET'])]
    public function getOne(string $projectId, string $id)
    {
        if (!$this->ft->mig()) return ApiResponse::err('Migration Manager disabled', 503);
        $e = $this->repo->find($id);
        return ($e && $e->getProjectId()===$projectId) ? ApiResponse::ok([
            'id'=>$e->getId(),'name'=>$e->getName(),
            'start_at'=>$e->getStartAt()?->format(\DateTimeInterface::ATOM),
            'end_at'=>$e->getEndAt()?->format(\DateTimeInterface::ATOM),
            'status'=>$e->getStatus()
        ]) : ApiResponse::err('Not found', 404);
    }

    #[Route('/{id}', name: 'mig_wave_update', methods: ['PATCH','PUT'])]
    public function update(string $projectId, string $id, Request $r)
    {
        if (!$this->ft->mig()) return ApiResponse::err('Migration Manager disabled', 503);
        $e = $this->repo->find($id);
        if (!$e || $e->getProjectId()!==$projectId) return ApiResponse::err('Not found', 404);
        $p = json_decode($r->getContent() ?: '{}', true);

        if (isset($p['name']))   $e->setName($p['name']);
        if (isset($p['status'])) $e->setStatus($p['status']);
        if (array_key_exists('start_at',$p)) $e->setStartAt(empty($p['start_at'])? null : new \DateTimeImmutable($p['start_at']));
        if (array_key_exists('end_at',$p))   $e->setEndAt(empty($p['end_at'])?   null : new \DateTimeImmutable($p['end_at']));
        $this->repo->save($e, true);
        return ApiResponse::ok(['id'=>$e->getId()]);
    }

    #[Route('/{id}', name: 'mig_wave_delete', methods: ['DELETE'])]
    public function delete(string $projectId, string $id)
    {
        if (!$this->ft->mig()) return ApiResponse::err('Migration Manager disabled', 503);
        $e = $this->repo->find($id);
        if (!$e || $e->getProjectId()!==$projectId) return ApiResponse::err('Not found', 404);
        $this->repo->remove($e, true);
        return ApiResponse::ok(['deleted'=>$id]);
    }
}
