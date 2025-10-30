<?php

namespace App\Entity\Discovery;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'discovery_application_response')]
#[ORM\HasLifecycleCallbacks]
class DiscoveryApplicationResponse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: DiscoveryApplication::class, inversedBy: 'responses')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?DiscoveryApplication $application = null;

    #[ORM\Column(type: 'bigint')]
    private string $responseId;

    #[ORM\Column(length: 32)]
    private string $status = 'draft';

    #[ORM\Column(type: 'bigint', nullable: true)]
    private ?string $clonedFromApplicationId = null;

    #[ORM\Column(name: 'snapshot_json', type: 'json', nullable: true)]
    private ?array $snapshot = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id ? (int) $this->id : null;
    }

    public function getApplication(): ?DiscoveryApplication
    {
        return $this->application;
    }

    public function setApplication(?DiscoveryApplication $application): self
    {
        $this->application = $application;
        return $this;
    }

    public function getResponseId(): int
    {
        return (int) $this->responseId;
    }

    public function setResponseId(int $responseId): self
    {
        $this->responseId = (string) $responseId;
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

    public function getClonedFromApplicationId(): ?int
    {
        return $this->clonedFromApplicationId ? (int) $this->clonedFromApplicationId : null;
    }

    public function setClonedFromApplicationId(?int $clonedFromApplicationId): self
    {
        $this->clonedFromApplicationId = $clonedFromApplicationId ? (string) $clonedFromApplicationId : null;
        return $this;
    }

    public function getSnapshot(): ?array
    {
        return $this->snapshot;
    }

    public function setSnapshot(?array $snapshot): self
    {
        $this->snapshot = $snapshot;
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
