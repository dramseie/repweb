<?php
namespace App\Mig\Controller;

use App\Mig\Entity\Slot;
use App\Mig\Entity\SlotAssignment;
use App\Mig\Http\ApiResponse;
use App\Mig\Repository\SlotAssignmentRepository;
use App\Mig\Repository\SlotRepository;
use App\Mig\Service\FeatureToggle;
use Doctrine\ORM\EntityManagerInterface as EM;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/mig/slots')]
class SlotController extends AbstractController
{
    public function __construct(
        private readonly FeatureToggle $ft,
        private readonly SlotRepository $repo,
        private readonly SlotAssignmentRepository $assignRepo,
        private readonly EM $em
    ) {}

    #[Route('', name: 'mig_slot_list', methods: ['GET'])]
    public function list(Request $r)
    {
        if (!$this->ft->mig()) return ApiResponse::err('Migration Manager disabled', 503);
        $qb = $this->repo->createQueryBuilder('s');
        if ($wid = $r->query->get('window_id')) $qb->andWhere('s.windowId = :wid')->setParameter('wid',$wid);
        $rows = $qb->orderBy('s.startsAt','ASC')->getQuery()->getArrayResult();
        return ApiResponse::ok(['items'=>$rows]);
    }

    #[Route('', name: 'mig_slot_create', methods: ['POST'])]
    public function create(Request $r)
    {
        if (!$this->ft->mig()) return ApiResponse::err('Migration Manager disabled', 503);
        $p = json_decode($r->getContent() ?: '{}', true);

        $e = new Slot();
        $e->setWindowId($p['window_id']);
        $e->setLabel($p['label'] ?? 'Slot');
        $e->setStartsAt(new \DateTimeImmutable($p['starts_at']));
        $e->setEndsAt(new \DateTimeImmutable($p['ends_at']));
        $e->setCapacity((int)($p['capacity'] ?? 1));
        $this->repo->save($e, true);

        return ApiResponse::ok(['id'=>$e->getId()]);
    }

    #[Route('/{id}', name: 'mig_slot_update', methods: ['PATCH','PUT'])]
    public function update(string $id, Request $r)
    {
        if (!$this->ft->mig()) return ApiResponse::err('Migration Manager disabled', 503);
        $e = $this->repo->find($id);
        if (!$e) return ApiResponse::err('Not found', 404);
        $p = json_decode($r->getContent() ?: '{}', true);

        if (isset($p['label']))     $e->setLabel($p['label']);
        if (isset($p['starts_at'])) $e->setStartsAt(new \DateTimeImmutable($p['starts_at']));
        if (isset($p['ends_at']))   $e->setEndsAt(new \DateTimeImmutable($p['ends_at']));
        if (isset($p['capacity']))  $e->setCapacity((int)$p['capacity']);

        $this->repo->save($e, true);
        return ApiResponse::ok(['id'=>$e->getId()]);
    }

    #[Route('/{id}', name: 'mig_slot_delete', methods: ['DELETE'])]
    public function delete(string $id)
    {
        if (!$this->ft->mig()) return ApiResponse::err('Migration Manager disabled', 503);
        $e = $this->repo->find($id);
        if (!$e) return ApiResponse::err('Not found', 404);
        $this->repo->remove($e, true);
        return ApiResponse::ok(['deleted'=>$id]);
    }

    #[Route('/{id}/assign', name: 'mig_slot_assign', methods: ['POST'])]
    public function assign(string $id, Request $r)
    {
        if (!$this->ft->mig()) return ApiResponse::err('Migration Manager disabled', 503);
        $slot = $this->repo->find($id);
        if (!$slot) return ApiResponse::err('Slot not found', 404);

        $p = json_decode($r->getContent() ?: '{}', true);
        $serverIds = array_values(array_unique(array_map('strval', $p['server_ids'] ?? [])));
        if (!$serverIds) return ApiResponse::ok(['assigned'=>[]]);

        // simple replace-all strategy for now
        $conn = $this->em->getConnection();
        $conn->executeStatement('DELETE FROM repweb_mig.mig_slot_assignment WHERE slot_id = ?', [$id]);

        foreach ($serverIds as $sid) {
            $as = new SlotAssignment();
            $as->setSlotId($id);
            $as->setServerId($sid);
            $this->assignRepo->save($as, false);
        }
        $this->em->flush();

        return ApiResponse::ok(['slot'=>$id,'assigned'=>$serverIds]);
    }
}
