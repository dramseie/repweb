<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'share_link')]
class ShareLink
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'token', type: 'string', length: 64, unique: true)]
    private string $token;

    #[ORM\Column(name: 'is_public', type: 'boolean')]
    private bool $isPublic = true;

    #[ORM\Column(name: 'resource_type', type: 'string', length: 64)]
    private string $resourceType = 'report_file';

    #[ORM\Column(name: 'resource_id', type: 'integer')]
    private int $resourceId;

    // FORMAT column (uppercase)
    #[ORM\Column(name: 'FORMAT', type: 'string', length: 16)]
    private string $format = 'csv';

    #[ORM\Column(name: 'filename', type: 'string', length: 190, nullable: true)]
    private ?string $filename = null;

    #[ORM\Column(name: 'expires_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $expiresAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(name: 'created_by', type: 'integer', nullable: true)]
    private ?int $createdBy = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->token = bin2hex(random_bytes(24));
    }

    // getters/setters
    public function getId(): ?int { return $this->id; }

    public function getToken(): string { return $this->token; }
    public function setToken(string $t): self { $this->token = $t; return $this; }

    public function isPublic(): bool { return $this->isPublic; }
    public function setIsPublic(bool $v): self { $this->isPublic = $v; return $this; }

    public function getResourceType(): string { return $this->resourceType; }
    public function setResourceType(string $t): self { $this->resourceType = $t; return $this; }

    public function getResourceId(): int { return $this->resourceId; }
    public function setResourceId(int $id): self { $this->resourceId = $id; return $this; }

    public function getFormat(): string { return $this->format; }
    public function setFormat(string $f): self { $this->format = $f; return $this; }

    public function getFilename(): ?string { return $this->filename; }
    public function setFilename(?string $f): self { $this->filename = $f; return $this; }

    public function getExpiresAt(): ?\DateTimeInterface { return $this->expiresAt; }
    public function setExpiresAt(?\DateTimeInterface $d): self { $this->expiresAt = $d; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }

    public function getCreatedBy(): ?int { return $this->createdBy; }
    public function setCreatedBy(?int $u): self { $this->createdBy = $u; return $this; }
}
