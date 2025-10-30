<?php

namespace App\Entity\Discovery;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'discovery_application')]
#[ORM\HasLifecycleCallbacks]
class DiscoveryApplication
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: DiscoveryProject::class, inversedBy: 'applications')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?DiscoveryProject $project = null;

    #[ORM\ManyToOne(targetEntity: DiscoveryWave::class, inversedBy: 'applications')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?DiscoveryWave $wave = null;

    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private int $tenantId;

    #[ORM\Column(length: 255)]
    private string $appCi;

    #[ORM\Column(length: 255)]
    private string $appName;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $environment = null;

    #[ORM\Column(length: 32)]
    private string $status = 'draft';

    #[ORM\Column(type: 'bigint', nullable: true)]
    private ?string $questionnaireId = null;

    #[ORM\Column(name: 'raci_json', type: 'json', nullable: true)]
    private ?array $raci = null;

    #[ORM\Column(name: 'metadata_json', type: 'json', nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, DiscoveryApplicationResponse> */
    #[ORM\OneToMany(mappedBy: 'application', targetEntity: DiscoveryApplicationResponse::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $responses;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->responses = new ArrayCollection();
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

    public function getWave(): ?DiscoveryWave
    {
        return $this->wave;
    }

    public function setWave(?DiscoveryWave $wave): self
    {
        $this->wave = $wave;
        return $this;
    }

    public function getTenantId(): int
    {
        return $this->tenantId;
    }

    public function setTenantId(int $tenantId): self
    {
        $this->tenantId = $tenantId;
        return $this;
    }

    public function getAppCi(): string
    {
        return $this->appCi;
    }

    public function setAppCi(string $appCi): self
    {
        $this->appCi = $appCi;
        return $this;
    }

    public function getAppName(): string
    {
        return $this->appName;
    }

    public function setAppName(string $appName): self
    {
        $this->appName = $appName;
        return $this;
    }

    public function getEnvironment(): ?string
    {
        return $this->environment;
    }

    public function setEnvironment(?string $environment): self
    {
        $this->environment = $environment;
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

    public function getQuestionnaireId(): ?int
    {
        return $this->questionnaireId ? (int) $this->questionnaireId : null;
    }

    public function setQuestionnaireId(?int $questionnaireId): self
    {
        $this->questionnaireId = $questionnaireId ? (string) $questionnaireId : null;
        return $this;
    }

    public function getRaci(): ?array
    {
        return $this->raci;
    }

    public function setRaci(?array $raci): self
    {
        $this->raci = $raci;
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

    /** @return Collection<int, DiscoveryApplicationResponse> */
    public function getResponses(): Collection
    {
        return $this->responses;
    }

    public function addResponse(DiscoveryApplicationResponse $response): self
    {
        if (!$this->responses->contains($response)) {
            $this->responses->add($response);
            $response->setApplication($this);
        }
        return $this;
    }

    public function removeResponse(DiscoveryApplicationResponse $response): self
    {
        if ($this->responses->removeElement($response)) {
            if ($response->getApplication() === $this) {
                $response->setApplication(null);
            }
        }
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
