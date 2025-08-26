<?php

namespace App\Entity;

use App\Repository\MenuItemRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MenuItemRepository::class)]
#[ORM\Table(name: 'menu_item')]
class MenuItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'label', type: 'string', length: 255)]
    private string $label = '';

    #[ORM\Column(name: 'url', type: 'string', length: 255, nullable: true)]
    private ?string $url = null;

    #[ORM\Column(name: 'route', type: 'string', length: 255, nullable: true)]
    private ?string $route = null;

    // Stored as TEXT in DB; helpers expose array
    #[ORM\Column(name: 'route_params', type: 'text', nullable: true)]
    private ?string $routeParamsText = null;

    /**
     * ✅ Proper self-referencing relation using the existing `parent_id` column.
     * No schema change needed.
     */
    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?self $parent = null;

    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class)]
    private Collection $children;

    #[ORM\Column(name: 'position', type: 'integer', options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column(name: 'divider_before', type: 'boolean', options: ['default' => false])]
    private bool $dividerBefore = false;

    #[ORM\Column(name: 'mega_group', type: 'string', length: 255, nullable: true)]
    private ?string $megaGroup = null;

    #[ORM\Column(name: 'description', type: 'string', length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'is_active', type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    // Stored as TEXT JSON; helpers expose array
    #[ORM\Column(name: 'roles', type: 'text', nullable: true)]
    private ?string $rolesText = null;

    #[ORM\Column(name: 'icon', type: 'string', length: 255, nullable: true)]
    private ?string $icon = null;

    #[ORM\Column(name: 'badge', type: 'string', length: 32, nullable: true)]
    private ?string $badge = null;

    #[ORM\Column(name: 'external', type: 'boolean', options: ['default' => false])]
    private bool $external = false;

    public function __construct()
    {
        $this->children = new ArrayCollection();
    }

    public function __toString(): string
    {
        $label = $this->getLabel() ?: '(no label)';
        return $this->id ? "$label #{$this->id}" : $label;
    }

    // --- id/label/url/route ---------------------------------------------------

    public function getId(): ?int { return $this->id; }

    public function getLabel(): string { return $this->label; }
    public function setLabel(string $label): self { $this->label = $label; return $this; }

    public function getUrl(): ?string { return $this->url; }
    public function setUrl(?string $url): self { $this->url = $url; return $this; }

    public function getRoute(): ?string { return $this->route; }
    public function setRoute(?string $route): self { $this->route = $route; return $this; }

    // --- route params (JSON text <-> array) -----------------------------------

    public function getRouteParams(): array
    {
        if (!$this->routeParamsText) return [];
        $arr = json_decode($this->routeParamsText, true);
        return is_array($arr) ? $arr : [];
    }
    public function setRouteParams(array $params): self
    {
        $this->routeParamsText = $params ? json_encode($params, JSON_UNESCAPED_UNICODE) : null;
        return $this;
    }
    public function getRouteParamsText(): ?string { return $this->routeParamsText; }
    public function setRouteParamsText(?string $text): self { $this->routeParamsText = $text; return $this; }

    // --- parent/children (relation) -------------------------------------------

    public function getParent(): ?self { return $this->parent; }
    public function setParent(?self $parent): self { $this->parent = $parent; return $this; }

    /** @return Collection<int,self> */
    public function getChildren(): Collection { return $this->children; }
    public function addChild(self $child): self
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParent($this);
        }
        return $this;
    }
    public function removeChild(self $child): self
    {
        if ($this->children->removeElement($child) && $child->getParent() === $this) {
            $child->setParent(null);
        }
        return $this;
    }

    /** Convenience for legacy code (e.g. navbar) */
    public function getParentId(): ?int
    {
        return $this->parent?->getId();
    }

    // --- position/divider/mega/description/active -----------------------------

    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): self { $this->position = $position; return $this; }

    public function isDividerBefore(): bool { return $this->dividerBefore; }
    public function setDividerBefore(bool $v): self { $this->dividerBefore = $v; return $this; }

    public function getMegaGroup(): ?string { return $this->megaGroup; }
    public function setMegaGroup(?string $megaGroup): self { $this->megaGroup = $megaGroup; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): self { $this->isActive = $isActive; return $this; }

    // --- roles (JSON text <-> array) ------------------------------------------

    public function getRoles(): array
    {
        if (!$this->rolesText) return [];
        $arr = json_decode($this->rolesText, true);
        return is_array($arr) ? $arr : [];
    }
    public function setRoles(array $roles): self
    {
        $this->rolesText = $roles ? json_encode(array_values($roles), JSON_UNESCAPED_UNICODE) : null;
        return $this;
    }
    public function getRolesText(): ?string { return $this->rolesText; }
    public function setRolesText(?string $text): self { $this->rolesText = $text; return $this; }

    public function getRolesDisplay(): string
    {
        $r = $this->getRoles();
        return $r ? implode(', ', $r) : '';
    }

    // --- icon/badge/external ---------------------------------------------------

    public function getIcon(): ?string { return $this->icon; }
    public function setIcon(?string $icon): self { $this->icon = $icon; return $this; }

    public function getBadge(): ?string { return $this->badge; }
    public function setBadge(?string $badge): self { $this->badge = $badge; return $this; }

    public function isExternal(): bool { return $this->external; }
    public function setExternal(bool $external): self { $this->external = $external; return $this; }
}
