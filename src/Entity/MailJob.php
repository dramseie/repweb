<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'mail_job')]
#[ORM\HasLifecycleCallbacks]
class MailJob
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: MailTemplate::class)]
    #[ORM\JoinColumn(name: 'template_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private MailTemplate $template;

    #[ORM\Column(name: 'scheduled_at', type: 'datetime_immutable')]
    private \DateTimeInterface $scheduledAt;

    // STATUS column (uppercase)
    #[ORM\Column(name: 'STATUS', type: 'string', length: 16)]
    private string $status = 'queued';

    #[ORM\Column(name: 'error_message', type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(name: 'sent_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $sentAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->scheduledAt = $now;
        $this->createdAt   = $now;
        $this->updatedAt   = $now;
    }

    #[ORM\PreUpdate]
    public function onUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // getters/setters
    public function getId(): ?int { return $this->id; }

    public function getTemplate(): MailTemplate { return $this->template; }
    public function setTemplate(MailTemplate $t): self { $this->template = $t; return $this; }

    public function getScheduledAt(): \DateTimeInterface { return $this->scheduledAt; }
    public function setScheduledAt(\DateTimeInterface $d): self { $this->scheduledAt = $d; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $s): self { $this->status = $s; return $this; }

    public function getErrorMessage(): ?string { return $this->errorMessage; }
    public function setErrorMessage(?string $e): self { $this->errorMessage = $e; return $this; }

    public function getSentAt(): ?\DateTimeInterface { return $this->sentAt; }
    public function setSentAt(?\DateTimeInterface $d): self { $this->sentAt = $d; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }
}
