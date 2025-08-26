<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'entity_type', schema: 'eav')]
class EntityType
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'bigint')]
    private ?string $type_id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'tenant_id', referencedColumnName: 'tenant_id', nullable: false)]
    private Tenant $tenant;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'datetime', nullable: true, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $created_at = null;

    public function getTypeId(): ?string { return $this->type_id; }
    public function getTenant(): Tenant { return $this->tenant; }
    public function setTenant(Tenant $t): self { $this->tenant=$t; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $n): self { $this->name=$n; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $d): self { $this->description=$d; return $this; }
    public function getCreatedAt(): ?\DateTimeInterface { return $this->created_at; }
    public function setCreatedAt(?\DateTimeInterface $d): self { $this->created_at=$d; return $this; }
    public function __toString(): string { return $this->name ?? ('type#'.$this->type_id); }
}
