<?php

namespace App\Entity\Discovery;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'discovery_session')]
#[ORM\HasLifecycleCallbacks]
class DiscoverySession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: DiscoveryProject::class, inversedBy: 'sessions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?DiscoveryProject $project = null;

    #[ORM\Column(length: 190)]
    private string $title;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $heldAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $summary = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $minutesHtml = null;

    #[ORM\Column(name: 'participants_json', type: 'json', nullable: true)]
    private ?array $participants = null;

    #[ORM\Column(name: 'action_items_json', type: 'json', nullable: true)]
    private ?array $actionItems = null;

    #[ORM\Column(name: 'mail_status', length: 16)]
    private string $mailStatus = 'pending';

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $mailedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $mailError = null;

    #[ORM\Column(length: 190, nullable: true)]
    private ?string $createdBy = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getHeldAt(): ?\DateTimeImmutable
    {
        return $this->heldAt;
    }

    public function setHeldAt(?\DateTimeImmutable $heldAt): self
    {
        $this->heldAt = $heldAt;
        return $this;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(?string $summary): self
    {
        $this->summary = $summary;
        return $this;
    }

    public function getMinutesHtml(): ?string
    {
        return $this->minutesHtml;
    }

    public function setMinutesHtml(?string $minutesHtml): self
    {
        $this->minutesHtml = $minutesHtml;
        return $this;
    }

    public function getParticipants(): ?array
    {
        return $this->participants;
    }

    public function setParticipants(?array $participants): self
    {
        $this->participants = $participants;
        return $this;
    }

    public function getActionItems(): ?array
    {
        return $this->actionItems;
    }

    public function setActionItems(?array $actionItems): self
    {
        $this->actionItems = $actionItems;
        return $this;
    }

    public function getMailStatus(): string
    {
        return $this->mailStatus;
    }

    public function setMailStatus(string $mailStatus): self
    {
        $this->mailStatus = $mailStatus;
        return $this;
    }

    public function getMailedAt(): ?\DateTimeImmutable
    {
        return $this->mailedAt;
    }

    public function setMailedAt(?\DateTimeImmutable $mailedAt): self
    {
        $this->mailedAt = $mailedAt;
        return $this;
    }

    public function getMailError(): ?string
    {
        return $this->mailError;
    }

    public function setMailError(?string $mailError): self
    {
        $this->mailError = $mailError;
        return $this;
    }

    public function getCreatedBy(): ?string
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?string $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    #[ORM\PrePersist]
    public function touchCreated(): void
    {
        if (!$this->createdAt) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }
}
