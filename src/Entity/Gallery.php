<?php

namespace App\Entity;

use App\Repository\GalleryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GalleryRepository::class)]
#[ORM\Table(name: 'gallery')]
#[ORM\UniqueConstraint(name: 'uniq_gallery_slug', columns: ['slug'])]
#[ORM\UniqueConstraint(name: 'uniq_gallery_share', columns: ['share_token'])]
class Gallery
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private string $name;

    #[ORM\Column(length: 150)]
    private string $slug;

    #[ORM\Column(length: 80, name: 'share_token')]
    private string $shareToken;

    #[ORM\Column(type: 'datetime_immutable', name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', name: 'updated_at')]
    private \DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, GalleryPhoto>
     */
    #[ORM\OneToMany(mappedBy: 'gallery', targetEntity: GalleryPhoto::class, orphanRemoval: true)]
    private Collection $photos;

    public function __construct(string $name, string $slug, ?string $shareToken = null)
    {
        $now = new \DateTimeImmutable();
        $this->name = $name;
        $this->slug = $slug;
        $this->shareToken = $shareToken ?? self::generateShareToken();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->photos = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function rename(string $name): self
    {
        $this->name = $name;
        $this->touch();

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function changeSlug(string $slug): self
    {
        $this->slug = $slug;
        $this->touch();

        return $this;
    }

    public function getShareToken(): string
    {
        return $this->shareToken;
    }

    public function regenerateShareToken(): self
    {
        $this->shareToken = self::generateShareToken();
        $this->touch();

        return $this;
    }

    public function matchesToken(?string $token): bool
    {
        return $token !== null && hash_equals($this->shareToken, $token);
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return Collection<int, GalleryPhoto>
     */
    public function getPhotos(): Collection
    {
        return $this->photos;
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    private static function generateShareToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(30)), '+/', '-_'), '=');
    }
}
