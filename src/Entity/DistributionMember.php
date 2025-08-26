<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'distribution_member')]
class DistributionMember
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: DistributionList::class, inversedBy: 'members')]
    #[ORM\JoinColumn(name: 'list_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private DistributionList $list;

    #[ORM\Column(name: 'email', type: 'string', length: 190)]
    private string $email = '';

    #[ORM\Column(name: 'display_name', type: 'string', length: 190, nullable: true)]
    private ?string $displayName = null;

    #[ORM\Column(name: 'is_active', type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // getters/setters
    public function getId(): ?int { return $this->id; }

    public function getList(): DistributionList { return $this->list; }
    public function setList(DistributionList $list): self { $this->list = $list; return $this; }

    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): self { $this->email = $email; return $this; }

    public function getDisplayName(): ?string { return $this->displayName; }
    public function setDisplayName(?string $n): self { $this->displayName = $n; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $v): self { $this->isActive = $v; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
}
