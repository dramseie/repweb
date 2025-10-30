<?php

namespace App\Entity\Discovery;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'discovery_project')]
#[ORM\HasLifecycleCallbacks]
class DiscoveryProject
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?string $id = null;

    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private int $tenantId;

    #[ORM\Column(length: 64)]
    private string $code;

    #[ORM\Column(length: 190)]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $legalEntityCi = null;

    #[ORM\Column(length: 16)]
    private string $status = 'draft';

    #[ORM\Column(length: 190, nullable: true)]
    private ?string $ownerEmail = null;

    #[ORM\Column(name: 'metadata_json', type: 'json', nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, DiscoveryApplication> */
    #[ORM\OneToMany(mappedBy: 'project', targetEntity: DiscoveryApplication::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $applications;

    /** @var Collection<int, DiscoveryStakeholder> */
    #[ORM\OneToMany(mappedBy: 'project', targetEntity: DiscoveryStakeholder::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $stakeholders;

    /** @var Collection<int, DiscoverySession> */
    #[ORM\OneToMany(mappedBy: 'project', targetEntity: DiscoverySession::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $sessions;

    /** @var Collection<int, DiscoveryWave> */
    #[ORM\OneToMany(mappedBy: 'project', targetEntity: DiscoveryWave::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $waves;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->applications = new ArrayCollection();
        $this->stakeholders = new ArrayCollection();
        $this->sessions = new ArrayCollection();
    $this->waves = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id ? (int) $this->id : null;
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

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getLegalEntityCi(): ?string
    {
        return $this->legalEntityCi;
    }

    public function setLegalEntityCi(?string $legalEntityCi): self
    {
        $this->legalEntityCi = $legalEntityCi;
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

    public function getOwnerEmail(): ?string
    {
        return $this->ownerEmail;
    }

    public function setOwnerEmail(?string $ownerEmail): self
    {
        $this->ownerEmail = $ownerEmail;
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

    public function addApplication(DiscoveryApplication $application): self
    {
        if (!$this->applications->contains($application)) {
            $this->applications->add($application);
            $application->setProject($this);
        }
        return $this;
    }

    public function removeApplication(DiscoveryApplication $application): self
    {
        if ($this->applications->removeElement($application)) {
            if ($application->getProject() === $this) {
                $application->setProject(null);
            }
        }
        return $this;
    }

    /** @return Collection<int, DiscoveryStakeholder> */
    public function getStakeholders(): Collection
    {
        return $this->stakeholders;
    }

    public function addStakeholder(DiscoveryStakeholder $stakeholder): self
    {
        if (!$this->stakeholders->contains($stakeholder)) {
            $this->stakeholders->add($stakeholder);
            $stakeholder->setProject($this);
        }
        return $this;
    }

    public function removeStakeholder(DiscoveryStakeholder $stakeholder): self
    {
        if ($this->stakeholders->removeElement($stakeholder)) {
            if ($stakeholder->getProject() === $this) {
                $stakeholder->setProject(null);
            }
        }
        return $this;
    }

    /** @return Collection<int, DiscoverySession> */
    public function getSessions(): Collection
    {
        return $this->sessions;
    }

    public function addSession(DiscoverySession $session): self
    {
        if (!$this->sessions->contains($session)) {
            $this->sessions->add($session);
            $session->setProject($this);
        }
        return $this;
    }

    public function removeSession(DiscoverySession $session): self
    {
        if ($this->sessions->removeElement($session)) {
            if ($session->getProject() === $this) {
                $session->setProject(null);
            }
        }
        return $this;
    }

    /** @return Collection<int, DiscoveryWave> */
    public function getWaves(): Collection
    {
        return $this->waves;
    }

    public function addWave(DiscoveryWave $wave): self
    {
        if (!$this->waves->contains($wave)) {
            $this->waves->add($wave);
            $wave->setProject($this);
        }
        return $this;
    }

    public function removeWave(DiscoveryWave $wave): self
    {
        if ($this->waves->removeElement($wave)) {
            if ($wave->getProject() === $this) {
                $wave->setProject(null);
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
