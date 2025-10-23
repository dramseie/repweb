<?php
namespace App\Mig\Entity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: App\Mig\Repository\WaveRepository::class)]
#[ORM\Table(name: 'repweb_mig.mig_wave2')]
class Wave
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private string $projectId;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeInterface $startAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeInterface $endAt = null;

    #[ORM\Column(length: 20, options: ['default' => 'Planned'])]
    private string $status = 'Planned';

    public function getId(): ?string { return $this->id; }
    public function getProjectId(): string { return $this->projectId; }
    public function setProjectId(string $v): self { $this->projectId = $v; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $v): self { $this->name = $v; return $this; }
    public function getStartAt(): ?\DateTimeInterface { return $this->startAt; }
    public function setStartAt(?\DateTimeInterface $v): self { $this->startAt = $v; return $this; }
    public function getEndAt(): ?\DateTimeInterface { return $this->endAt; }
    public function setEndAt(?\DateTimeInterface $v): self { $this->endAt = $v; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): self { $this->status = $v; return $this; }
}
