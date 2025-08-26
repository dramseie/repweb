<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'mail_attachment')]
class MailAttachment
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: MailTemplate::class)]
    #[ORM\JoinColumn(name: 'template_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private MailTemplate $template;

    // TYPE, FORMAT, POSITION are uppercase
    #[ORM\Column(name: 'TYPE', type: 'string', length: 16)]
    private string $type = 'report'; // 'report' or 'file'

    #[ORM\Column(name: 'report_id', type: 'integer', nullable: true)]
    private ?int $reportId = null;

    #[ORM\Column(name: 'file_path', type: 'string', length: 255, nullable: true)]
    private ?string $filePath = null;

    #[ORM\Column(name: 'FORMAT', type: 'string', length: 16, options: ['default' => 'csv'])]
    private string $format = 'csv'; // csv, excel, json

    #[ORM\Column(name: 'is_link', type: 'boolean')]
    private bool $isLink = false;

    #[ORM\Column(name: 'is_public_link', type: 'boolean')]
    private bool $isPublicLink = false;

    #[ORM\Column(name: 'filename_override', type: 'string', length: 190, nullable: true)]
    private ?string $filenameOverride = null;

    #[ORM\Column(name: 'POSITION', type: 'integer')]
    private int $position = 0;

    // getters/setters
    public function getId(): ?int { return $this->id; }

    public function getTemplate(): MailTemplate { return $this->template; }
    public function setTemplate(MailTemplate $t): self { $this->template = $t; return $this; }

    public function getType(): string { return $this->type; }
    public function setType(string $v): self { $this->type = $v; return $this; }

    public function getReportId(): ?int { return $this->reportId; }
    public function setReportId(?int $v): self { $this->reportId = $v; return $this; }

    public function getFilePath(): ?string { return $this->filePath; }
    public function setFilePath(?string $v): self { $this->filePath = $v; return $this; }

    public function getFormat(): string { return $this->format; }
    public function setFormat(string $v): self { $this->format = $v; return $this; }

    public function isLink(): bool { return $this->isLink; }
    public function setIsLink(bool $v): self { $this->isLink = $v; return $this; }

    public function isPublicLink(): bool { return $this->isPublicLink; }
    public function setIsPublicLink(bool $v): self { $this->isPublicLink = $v; return $this; }

    public function getFilenameOverride(): ?string { return $this->filenameOverride; }
    public function setFilenameOverride(?string $v): self { $this->filenameOverride = $v; return $this; }

    public function getPosition(): int { return $this->position; }
    public function setPosition(int $v): self { $this->position = $v; return $this; }
}
