<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'user_tile')]
#[ORM\UniqueConstraint(columns: ['user_id','tile_id'])]
class UserTile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: ReportTile::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ReportTile $tile;

    #[ORM\Column(type: 'integer')]
    private int $position = 0;

    #[ORM\Column(type: 'boolean')]
    private bool $pinned = false;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $layout = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct() { $this->createdAt = new \DateTimeImmutable(); }

    // Getters / setters
    public function getId(): ?int { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function setUser(User $u): self { $this->user=$u; return $this; }
    public function getTile(): ReportTile { return $this->tile; }
    public function setTile(ReportTile $t): self { $this->tile=$t; return $this; }
    public function getPosition(): int { return $this->position; }
    public function setPosition(int $p): self { $this->position=$p; return $this; }
    public function isPinned(): bool { return $this->pinned; }
    public function setPinned(bool $p): self { $this->pinned=$p; return $this; }
    public function getLayout(): ?array { return $this->layout; }
    public function setLayout(?array $l): self { $this->layout=$l; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
