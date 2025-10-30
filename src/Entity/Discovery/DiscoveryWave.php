<?php

namespace App\Entity\Discovery;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'discovery_wave')]
#[ORM\HasLifecycleCallbacks]
class DiscoveryWave
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: DiscoveryProject::class, inversedBy: 'waves')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?DiscoveryProject $project = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $code = null;

    #[ORM\Column(length: 190)]
    private string $name;

    #[ORM\Column(length: 16)]
    private string $status = 'planned';

    #[ORM\Column(type: 'integer')]
    private int $position = 0;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $startAt = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $endAt = null;

    #[ORM\Column(name: 'metadata_json', type: 'json', nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, DiscoveryApplication> */
    #[ORM\OneToMany(mappedBy: 'wave', targetEntity: DiscoveryApplication::class)]
    private Collection $applications;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->applications = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id ? (int) $this->id : null;
    }

    public function getProject(): ?DiscoveryProject
    {
        return $this->project;
    }

    public function setProject(?DiscoveryProject $project): self
    {
        $this->project = $project;
        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): self
    {
        $this->code = $code;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;
        return $this;
    }

    public function getStartAt(): ?\DateTimeInterface
    {
        return $this->startAt;
    }

    public function setStartAt(?\DateTimeInterface $startAt): self
    {
        $this->startAt = $startAt;
        return $this;
    }

    public function getEndAt(): ?\DateTimeInterface
    {
        return $this->endAt;
    }

    public function setEndAt(?\DateTimeInterface $endAt): self
    {
        $this->endAt = $endAt;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /** @return Collection<int, DiscoveryApplication> */
    public function getApplications(): Collection
    {
        return $this->applications;
    }

    #[ORM\PrePersist]
    public function touchCreated(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $this->createdAt ?? $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function touchUpdated(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
