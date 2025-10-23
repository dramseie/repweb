<?php
namespace App\Mig\Controller;

use App\Mig\Entity\Project;
use App\Mig\Http\ApiResponse;
use App\Mig\Repository\ProjectRepository;
use App\Mig\Service\FeatureToggle;
use Doctrine\ORM\EntityManagerInterface as EM;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/mig/projects')]
class ProjectController extends AbstractController
{
    public function __construct(
        private readonly FeatureToggle $ft,
        private readonly ProjectRepository $repo,
        private readonly EM $em
    ) {}

    #[Route('', name: 'mig_project_list', methods: ['GET'])]
    public function list()
    {
        if (!$this->ft->mig()) return ApiResponse::err('Migration Manager disabled', 503);
        $rows = $this->repo->createQueryBuilder('p')->orderBy('p.createdAt','DESC')->getQuery()->getArrayResult();
        return ApiResponse::ok(['items' => $rows]);
    }

    #[Route('', name: 'mig_project_create', methods: ['POST'])]
    public function create(Request $r)
    {
        if (!$this->ft->mig()) return ApiResponse::err('Migration Manager disabled', 503);
        $p = json_decode($r->getContent() ?: '{}', true);

        $e = new Project();
        $e->setTenantId($p['tenant_id'] ?? '1');
        $e->setName($p['name'] ?? 'New Project');
        $e->setDescription($p['description'] ?? null);
        $e->setStatus('Active');
        $e->setCreatedAt(new \DateTimeImmutable());
        $e->setCreatedBy($this->getUser()?->getId() ?? '1');

        $this->repo->save($e, true);
        return ApiResponse::ok(['id' => $e->getId()]);
    }

    #[Route('/{id}', name: 'mig_project_get', methods: ['GET'])]
    public function getOne(string $id)
    {
        if (!$this->ft->mig()) return ApiResponse::err('Migration Manager disabled', 503);
        $e = $this->repo->find($id);
        return $e ? ApiResponse::ok($this->em->getUnitOfWork()->getOriginalEntityData($e) ?: [
            'id'=>$e->getId(),'name'=>$e->getName(),'description'=>$e->getDescription(),'status'=>$e->getStatus()
        ]) : ApiResponse::err('Not found', 404);
    }

    #[Route('/{id}', name: 'mig_project_update', methods: ['PATCH','PUT'])]
    public function update(string $id, Request $r)
    {
        if (!$this->ft->mig()) return ApiResponse::err('Migration Manager disabled', 503);
        $e = $this->repo->find($id);
        if (!$e) return ApiResponse::err('Not found', 404);
        $p = json_decode($r->getContent() ?: '{}', true);

        if (isset($p['name']))        $e->setName($p['name']);
        if (array_key_exists('description',$p)) $e->setDescription($p['description']);
        if (isset($p['status']))      $e->setStatus($p['status']);

        $this->repo->save($e, true);
        return ApiResponse::ok(['id'=>$e->getId()]);
    }

    #[Route('/{id}', name: 'mig_project_delete', methods: ['DELETE'])]
    public function delete(string $id)
    {
        if (!$this->ft->mig()) return ApiResponse::err('Migration Manager disabled', 503);
        $e = $this->repo->find($id);
        if (!$e) return ApiResponse::err('Not found', 404);
        $this->repo->remove($e, true);
        return ApiResponse::ok(['deleted'=>$id]);
    }
}
