<?php
namespace App\Mig\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Mig\Repository\MailAttachmentRepository;

#[ORM\Entity(repositoryClass: MailAttachmentRepository::class)]
#[ORM\Table(name: 'repweb_mig.mig_mail_attachment')]
class MailAttachment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Mail::class)]
    #[ORM\JoinColumn(name: 'mail_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Mail $mail;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $filename = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contentType = null;

    #[ORM\Column(type: 'integer', nullable: true, options: ['unsigned' => true])]
    private ?int $size = null;

    #[ORM\Column(type: 'blob', nullable: true)]
    private $data = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?string { return $this->id; }
    public function getMail(): Mail { return $this->mail; }
    public function setMail(Mail $m): self { $this->mail = $m; return $this; }
    public function getFilename(): ?string { return $this->filename; }
    public function setFilename(?string $v): self { $this->filename = $v; return $this; }
    public function getContentType(): ?string { return $this->contentType; }
    public function setContentType(?string $v): self { $this->contentType = $v; return $this; }
    public function getSize(): ?int { return $this->size; }
    public function setSize(?int $v): self { $this->size = $v; return $this; }
    public function getData() { return $this->data; }
    public function setData($v): self { $this->data = $v; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
}
