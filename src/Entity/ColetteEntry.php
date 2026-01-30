<?php

namespace App\Entity;

use App\Repository\ColetteEntryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ColetteEntryRepository::class)]
#[ORM\Table(name: 'colette_entries')]
#[ORM\UniqueConstraint(name: 'colette_entries_slug_idx', columns: ['slug'])]
#[ORM\Index(name: 'colette_entries_category_idx', columns: ['category'])]
#[ORM\Index(name: 'colette_entries_published_idx', columns: ['is_published'])]
#[ORM\HasLifecycleCallbacks]
class ColetteEntry
{
    public const CATEGORY_RECIPE = 'recipe';
    public const CATEGORY_KNOWLEDGE = 'knowledge';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 120)]
    private string $slug = '';

    #[ORM\Column(type: 'string', length: 255)]
    private string $title = '';

    #[ORM\Column(type: 'string', length: 32)]
    private string $category = self::CATEGORY_RECIPE;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $subtitle = null;

    #[ORM\Column(type: 'text')]
    private string $content = '';

    #[ORM\Column(name: 'source_notes', type: 'text', nullable: true)]
    private ?string $sourceNotes = null;

    #[ORM\Column(name: 'image_data', type: 'text', nullable: true, length: 16777215)]
    private ?string $imageData = null;

    #[ORM\Column(name: 'image_mime_type', type: 'string', length: 100, nullable: true)]
    private ?string $imageMimeType = null;

    #[ORM\Column(name: 'ocr_extract', type: 'text', nullable: true)]
    private ?string $ocrExtract = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(name: 'is_published', type: 'boolean')]
    private bool $isPublished = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): self
    {
        if (!\in_array($category, self::getAvailableCategories(), true)) {
            throw new \InvalidArgumentException(sprintf('Unknown Colette entry category "%s".', $category));
        }

        $this->category = $category;
        return $this;
    }

    public static function getAvailableCategories(): array
    {
        return [
            self::CATEGORY_RECIPE,
            self::CATEGORY_KNOWLEDGE,
        ];
    }

    public function getSubtitle(): ?string
    {
        return $this->subtitle;
    }

    public function setSubtitle(?string $subtitle): self
    {
        $this->subtitle = $subtitle;
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getSourceNotes(): ?string
    {
        return $this->sourceNotes;
    }

    public function setSourceNotes(?string $sourceNotes): self
    {
        $this->sourceNotes = $sourceNotes;
        return $this;
    }

    public function getImageData(): ?string
    {
        return $this->imageData;
    }

    public function setImageData(?string $imageData): self
    {
        $this->imageData = $imageData;
        return $this;
    }

    public function getImageMimeType(): ?string
    {
        return $this->imageMimeType;
    }

    public function setImageMimeType(?string $imageMimeType): self
    {
        $this->imageMimeType = $imageMimeType;
        return $this;
    }

    public function getOcrExtract(): ?string
    {
        return $this->ocrExtract;
    }

    public function setOcrExtract(?string $ocrExtract): self
    {
        $this->ocrExtract = $ocrExtract;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function setIsPublished(bool $isPublished): self
    {
        $this->isPublished = $isPublished;
        return $this;
    }

    #[ORM\PrePersist]
    public function setTimestampsOnCreate(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt ??= $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
