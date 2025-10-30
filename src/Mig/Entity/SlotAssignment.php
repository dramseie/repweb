<?php
namespace App\Mig\Entity;

use App\Mig\Repository\SlotAssignmentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SlotAssignmentRepository::class)]
#[ORM\Table(name: 'repweb_mig.mig_slot_assignment')]
class SlotAssignment
{
    #[ORM\Id]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private string $slotId;

    #[ORM\Id]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private string $serverId;

    public function getSlotId(): string { return $this->slotId; }
    public function setSlotId(string $v): self { $this->slotId = $v; return $this; }
    public function getServerId(): string { return $this->serverId; }
    public function setServerId(string $v): self { $this->serverId = $v; return $this; }
}
