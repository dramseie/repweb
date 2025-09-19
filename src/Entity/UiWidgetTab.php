<?php
namespace App\Entity;

use App\Repository\UiWidgetTabRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UiWidgetTabRepository::class)]
#[ORM\Table(name: 'ui_widget_tab')]
class UiWidgetTab
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'owner_user_id', type: 'integer', nullable: true)]
    private ?int $ownerUserId = null; // NULL = system template

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $code = null;     // for system templates

    #[ORM\Column(type: 'string', length: 120)]
    private string $title = 'Tab';

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column(name: 'is_system', type: 'boolean', options: ['default' => 0])]
    private bool $isSystem = false;

    #[ORM\Column(name: 'layout_json', type: 'json', nullable: true)]
    private ?array $layoutJson = null; // {version, items, layouts}

    // ✅ Use immutable types to match DateTimeImmutable instances
    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

	// In App\Entity\UiWidgetTab
	#[ORM\Column(name: 'sort_order', type: 'integer', options: ['default' => 0])]
	private int $sortOrder = 0;

	#[ORM\Column(name: 'is_hidden', type: 'boolean', options: ['default' => false])]
	private bool $isHidden = false;

	public function getSortOrder(): int { return $this->sortOrder; }
	public function setSortOrder(int $v): self { $this->sortOrder = $v; return $this; }

	public function isHidden(): bool { return $this->isHidden; }
	public function setIsHidden(bool $v): self { $this->isHidden = $v; return $this; }


    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    // getters/setters
    public function getId(): ?int { return $this->id; }
    public function getOwnerUserId(): ?int { return $this->ownerUserId; }
    public function setOwnerUserId(?int $id): self { $this->ownerUserId = $id; return $this; }
    public function getCode(): ?string { return $this->code; }
    public function setCode(?string $c): self { $this->code = $c; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $t): self { $this->title = $t; return $this; }
    public function getPosition(): int { return $this->position; }
    public function setPosition(int $p): self { $this->position = $p; return $this; }
    public function isSystem(): bool { return $this->isSystem; }
    public function setIsSystem(bool $b): self { $this->isSystem = $b; return $this; }
    public function getLayoutJson(): ?array { return $this->layoutJson; }
    public function setLayoutJson(?array $j): self { $this->layoutJson = $j; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }

    public function touch(): void { $this->updatedAt = new \DateTimeImmutable(); }

    // small serializer for the tabs list
    public function toListArray(): array {
        return ['id' => $this->id, 'title' => $this->title, 'position' => $this->position];
    }
}
