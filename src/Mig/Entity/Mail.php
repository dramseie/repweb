<?php
namespace App\Mig\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Mig\Repository\MailRepository;

#[ORM\Entity(repositoryClass: MailRepository::class)]
#[ORM\Table(name: 'repweb_mig.mig_mail')]
class Mail
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $messageId = null;

    #[ORM\Column(length: 16, options: ['default' => 'inbound'])]
    private string $mailbox = 'inbound';

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $subject = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fromAddress = null;

    #[ORM\Column(type: 'json')]
    private array $toAddresses = [];

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $ccAddresses = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $bccAddresses = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $bodyHtml = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $bodyText = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $tags = null;

    #[ORM\Column(type: 'bigint', nullable: true, options: ['unsigned' => true])]
    private ?string $templateId = null;

    #[ORM\Column(length: 32, options: ['default' => 'received'])]
    private string $status = 'received';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $error = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeInterface $sentAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?string { return $this->id; }
    public function getMessageId(): ?string { return $this->messageId; }
    public function setMessageId(?string $v): self { $this->messageId = $v; return $this; }
    public function getMailbox(): string { return $this->mailbox; }
    public function setMailbox(string $v): self { $this->mailbox = $v; return $this; }
    public function getSubject(): ?string { return $this->subject; }
    public function setSubject(?string $v): self { $this->subject = $v; return $this; }
    public function getFromAddress(): ?string { return $this->fromAddress; }
    public function setFromAddress(?string $v): self { $this->fromAddress = $v; return $this; }
    public function getToAddresses(): array { return $this->toAddresses; }
    public function setToAddresses(array $v): self { $this->toAddresses = $v; return $this; }
    public function getCcAddresses(): ?array { return $this->ccAddresses; }
    public function setCcAddresses(?array $v): self { $this->ccAddresses = $v; return $this; }
    public function getBccAddresses(): ?array { return $this->bccAddresses; }
    public function setBccAddresses(?array $v): self { $this->bccAddresses = $v; return $this; }
    public function getBodyHtml(): ?string { return $this->bodyHtml; }
    public function setBodyHtml(?string $v): self { $this->bodyHtml = $v; return $this; }
    public function getBodyText(): ?string { return $this->bodyText; }
    public function setBodyText(?string $v): self { $this->bodyText = $v; return $this; }
    public function getTags(): ?array { return $this->tags; }
    public function setTags(?array $v): self { $this->tags = $v; return $this; }
    public function getTemplateId(): ?string { return $this->templateId; }
    public function setTemplateId(?string $v): self { $this->templateId = $v; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): self { $this->status = $v; return $this; }
    public function getError(): ?string { return $this->error; }
    public function setError(?string $v): self { $this->error = $v; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $v): self { $this->createdAt = $v; return $this; }
    public function getSentAt(): ?\DateTimeInterface { return $this->sentAt; }
    public function setSentAt(?\DateTimeInterface $v): self { $this->sentAt = $v; return $this; }
}
