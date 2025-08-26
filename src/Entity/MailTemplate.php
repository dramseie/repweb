<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: \App\Repository\MailTemplateRepository::class)]
#[ORM\Table(name: 'mail_template')]
#[ORM\HasLifecycleCallbacks]
class MailTemplate
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private ?int $id = null;

    // NAME, SUBJECT (uppercase)
    #[ORM\Column(name: 'NAME', type: 'string', length: 190)]
    private string $name = '';

    #[ORM\Column(name: 'SUBJECT', type: 'string', length: 255)]
    private string $subject = '';

    #[ORM\Column(name: 'body_html', type: 'text')]
    private string $bodyHtml = '';

    #[ORM\Column(name: 'body_text', type: 'text', nullable: true)]
    private ?string $bodyText = null;

    #[ORM\Column(name: 'from_email', type: 'string', length: 190)]
    private string $fromEmail = '';

    #[ORM\Column(name: 'reply_to', type: 'string', length: 190, nullable: true)]
    private ?string $replyTo = null;

    #[ORM\Column(name: 'logo_path', type: 'string', length: 255, nullable: true)]
    private ?string $logoPath = null;

    #[ORM\Column(name: 'to_addresses', type: 'json', nullable: true)]
    private ?array $toAddresses = [];

    #[ORM\Column(name: 'cc_addresses', type: 'json', nullable: true)]
    private ?array $ccAddresses = [];

    #[ORM\Column(name: 'is_active', type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeInterface $updatedAt;

    // ManyToMany via mail_template_distribution_list (template_id, list_id)
    #[ORM\ManyToMany(targetEntity: DistributionList::class, inversedBy: 'templates', fetch: 'EXTRA_LAZY')]
    #[ORM\JoinTable(name: 'mail_template_distribution_list')]
    #[ORM\JoinColumn(name: 'template_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'list_id', referencedColumnName: 'id')]
    private Collection $distributionLists;

    public function __construct()
    {
        $this->distributionLists = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function onCreate(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // getters/setters
    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $v): self { $this->name = $v; return $this; }

    public function getSubject(): string { return $this->subject; }
    public function setSubject(string $v): self { $this->subject = $v; return $this; }

    public function getBodyHtml(): string { return $this->bodyHtml; }
    public function setBodyHtml(string $v): self { $this->bodyHtml = $v; return $this; }

    public function getBodyText(): ?string { return $this->bodyText; }
    public function setBodyText(?string $v): self { $this->bodyText = $v; return $this; }

    public function getFromEmail(): string { return $this->fromEmail; }
    public function setFromEmail(string $v): self { $this->fromEmail = $v; return $this; }

    public function getReplyTo(): ?string { return $this->replyTo; }
    public function setReplyTo(?string $v): self { $this->replyTo = $v; return $this; }

    public function getLogoPath(): ?string { return $this->logoPath; }
    public function setLogoPath(?string $v): self { $this->logoPath = $v; return $this; }

    public function getToAddresses(): array { return $this->toAddresses ?? []; }
    public function setToAddresses(?array $v): self { $this->toAddresses = $v; return $this; }

    public function getCcAddresses(): array { return $this->ccAddresses ?? []; }
    public function setCcAddresses(?array $v): self { $this->ccAddresses = $v; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $v): self { $this->isActive = $v; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }

    /** @return Collection<int, DistributionList> */
    public function getDistributionLists(): Collection { return $this->distributionLists; }
}
