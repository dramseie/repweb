<?php

namespace App\Entity;

use App\Repository\ReportTileRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReportTileRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ReportTile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(length: 50)]
    private string $type = 'link';

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $config = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $thumbnailUrl = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $allowedRoles = [];

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    // keep mutable DateTime if you prefer; callbacks will maintain it
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTime $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTime();
    }

    #----------------------------------------
    # Lifecycle
    #----------------------------------------
    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        // createdAt is already set in constructor; ensure safety if constructed by hydration
        if (!isset($this->createdAt)) {
            $this->createdAt = new \DateTimeImmutable();
        }
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    #----------------------------------------
    # Getters/Setters
    #----------------------------------------
    public function getId(): ?int { return $this->id; }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }

    public function getType(): string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }

    public function getConfig(): ?array { return $this->config; }

    /**
     * Accepts array|null OR JSON string; throws if invalid.
     */
    public function setConfig(array|string|null $config): self
    {
        if (is_string($config)) {
            $decoded = json_decode($config, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException('Invalid JSON for config: ' . json_last_error_msg());
            }
            $config = $decoded;
        }
        $this->config = $config;
        return $this;
    }

    public function getThumbnailUrl(): ?string { return $this->thumbnailUrl; }
    public function setThumbnailUrl(?string $url): self { $this->thumbnailUrl = $url; return $this; }

    public function getAllowedRoles(): ?array { return $this->allowedRoles; }

    /**
     * Normalizes to array (filters empties); null clears roles (visible to all).
     */
    public function setAllowedRoles(?array $roles): self
    {
        if ($roles === null) {
            $this->allowedRoles = null;
            return $this;
        }
        // filter out empty strings/nulls and reindex
        $this->allowedRoles = array_values(array_filter($roles, static fn($r) => is_string($r) && $r !== ''));
        return $this;
    }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $active): self { $this->isActive = $active; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $dt): self { $this->createdAt = $dt; return $this; }

    public function getUpdatedAt(): \DateTime { return $this->updatedAt; }
    public function setUpdatedAt(\DateTime $dt): self { $this->updatedAt = $dt; return $this; }

    #----------------------------------------
    # EasyAdmin virtual JSON field
    #----------------------------------------
    public function getConfigJson(): string
    {
        // pretty-printed, never "null" (return {} when empty)
        $data = $this->config ?? [];
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function setConfigJson(?string $json): self
    {
        $json = trim((string) $json);
        if ($json === '') {
            $this->config = null;
            return $this;
        }
        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }
        $this->config = $decoded;
        return $this;
    }

    #----------------------------------------
    # Helpers
    #----------------------------------------
    /**
     * Convenience getter: read a key from config with default
     */
    public function getConfigValue(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function __toString(): string
    {
        return $this->title ?? ('Tile #'.$this->id);
    }
}
