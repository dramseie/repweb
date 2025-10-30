<?php
namespace App\Mig\Entity;

use App\Mig\Repository\ContainerRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContainerRepository::class)]
#[ORM\Table(name: 'repweb_mig.mig_container')]
class Container
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private string $waveId;

    #[ORM\Column(type: 'bigint', nullable: true, options: ['unsigned' => true])]
    private ?string $applicationId = null;

    #[ORM\Column(length: 150)]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    public function getId(): ?string { return $this->id; }
    public function getWaveId(): string { return $this->waveId; }
    public function setWaveId(string $v): self { $this->waveId = $v; return $this; }
    public function getApplicationId(): ?string { return $this->applicationId; }
    public function setApplicationId(?string $v): self { $this->applicationId = $v; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $v): self { $this->name = $v; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): self { $this->notes = $v; return $this; }
}
