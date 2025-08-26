<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'entity', schema: 'eav')]
class EavEntity
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'bigint')]
    private ?string $entity_id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'tenant_id', referencedColumnName: 'tenant_id', nullable: false)]
    private Tenant $tenant;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'type_id', referencedColumnName: 'type_id', nullable: false)]
    private EntityType $type;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(type: 'datetime', nullable: true, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $created_at = null;

    public function getEntityId(): ?string { return $this->entity_id; }
    public function getTenant(): Tenant { return $this->tenant; }
    public function setTenant(Tenant $t): self { $this->tenant=$t; return $this; }
    public function getType(): EntityType { return $this->type; }
    public function setType(EntityType $t): self { $this->type=$t; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $n): self { $this->name=$n; return $this; }
    public function getCreatedAt(): ?\DateTimeInterface { return $this->created_at; }
    public function setCreatedAt(?\DateTimeInterface $d): self { $this->created_at=$d; return $this; }
    public function __toString(): string { return $this->name ?? ('entity#'.$this->entity_id); }
}
