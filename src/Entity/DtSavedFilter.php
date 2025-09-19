<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\DtSavedFilterRepository::class)]
#[ORM\Table(name: "dt_saved_filter")]
class DtSavedFilter
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "bigint", options: ["unsigned" => true])]
    private ?string $id = null;

    #[ORM\Column(length: 128)]
    private string $tableKey;

    #[ORM\Column(length: 160)]
    private string $name;

    #[ORM\Column(type: "boolean")]
    private bool $isPublic = false;

    // CHANGED: bigint → string (email/username allowed)
    #[ORM\Column(type: "string", length: 191, nullable: true)]
    private ?string $ownerId = null;

    #[ORM\Column(type: "json")]
    private array $detailsJson = [];

    #[ORM\Column(type: "json", nullable: true)]
    private ?array $stateJson = null;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // getters/setters
    public function getId(): ?string { return $this->id; }

    public function getTableKey(): string { return $this->tableKey; }
    public function setTableKey(string $v): self { $this->tableKey = $v; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $v): self { $this->name = $v; return $this; }

    public function isPublic(): bool { return $this->isPublic; }
    public function setIsPublic(bool $v): self { $this->isPublic = $v; return $this; }

    public function getOwnerId(): ?string { return $this->ownerId; }
    public function setOwnerId(?string $v): self { $this->ownerId = $v; return $this; }

    public function getDetailsJson(): array { return $this->detailsJson; }
    public function setDetailsJson(array $v): self { $this->detailsJson = $v; return $this; }

    public function getStateJson(): ?array { return $this->stateJson; }
    public function setStateJson(?array $v): self { $this->stateJson = $v; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
