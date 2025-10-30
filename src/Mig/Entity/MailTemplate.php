<?php
namespace App\Mig\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Mig\Repository\MailTemplateRepository;

#[ORM\Entity(repositoryClass: MailTemplateRepository::class)]
#[ORM\Table(name: 'repweb_mig.mig_mail_template')]
class MailTemplate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\Column(length: 200)]
    private string $name;

    #[ORM\Column(length: 500)]
    private string $subject;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $bodyHtml = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $bodyText = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $defaultFrom = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?string { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $v): self { $this->name = $v; return $this; }
    public function getSubject(): string { return $this->subject; }
    public function setSubject(string $v): self { $this->subject = $v; return $this; }
    public function getBodyHtml(): ?string { return $this->bodyHtml; }
    public function setBodyHtml(?string $v): self { $this->bodyHtml = $v; return $this; }
    public function getBodyText(): ?string { return $this->bodyText; }
    public function setBodyText(?string $v): self { $this->bodyText = $v; return $this; }
    public function getDefaultFrom(): ?string { return $this->defaultFrom; }
    public function setDefaultFrom(?string $v): self { $this->defaultFrom = $v; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
}
