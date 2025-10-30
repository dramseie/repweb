<?php
namespace App\Mig\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Mig\Repository\MailSendLogRepository;

#[ORM\Entity(repositoryClass: MailSendLogRepository::class)]
#[ORM\Table(name: 'repweb_mig.mig_mail_sendlog')]
class MailSendLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Mail::class)]
    #[ORM\JoinColumn(name: 'mail_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?Mail $mail = null;

    #[ORM\Column(length: 50)]
    private string $action;

    #[ORM\Column(length: 50)]
    private string $status;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $message = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?string { return $this->id; }
    public function getMail(): ?Mail { return $this->mail; }
    public function setMail(?Mail $m): self { $this->mail = $m; return $this; }
    public function getAction(): string { return $this->action; }
    public function setAction(string $v): self { $this->action = $v; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): self { $this->status = $v; return $this; }
    public function getMessage(): ?string { return $this->message; }
    public function setMessage(?string $v): self { $this->message = $v; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
}
