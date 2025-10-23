<?php
namespace App\Mig\Entity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: App\Mig\Repository\SlotRepository::class)]
#[ORM\Table(name: 'repweb_mig.mig_slot')]
class Slot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private string $windowId;

    #[ORM\Column(length: 120)]
    private string $label;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeInterface $startsAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeInterface $endsAt;

    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $capacity = 1;

    public function getId(): ?string { return $this->id; }
    public function getWindowId(): string { return $this->windowId; }
    public function setWindowId(string $v): self { $this->windowId = $v; return $this; }
    public function getLabel(): string { return $this->label; }
    public function setLabel(string $v): self { $this->label = $v; return $this; }
    public function getStartsAt(): \DateTimeInterface { return $this->startsAt; }
    public function setStartsAt(\DateTimeInterface $v): self { $this->startsAt = $v; return $this; }
    public function getEndsAt(): \DateTimeInterface { return $this->endsAt; }
    public function setEndsAt(\DateTimeInterface $v): self { $this->endsAt = $v; return $this; }
    public function getCapacity(): int { return $this->capacity; }
    public function setCapacity(int $v): self { $this->capacity = $v; return $this; }
}
