<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'eav_relation', schema: 'eav')]
class EavRelation
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type:'bigint')]
    private ?string $rel_id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name:'tenant_id', referencedColumnName:'tenant_id', nullable:false)]
    private Tenant $tenant;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name:'rel_type_id', referencedColumnName:'rel_type_id', nullable:false)]
    private EavRelationType $type;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name:'parent_entity_id', referencedColumnName:'entity_id', nullable:false)]
    private EavEntity $parent;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name:'child_entity_id', referencedColumnName:'entity_id', nullable:false)]
    private EavEntity $child;

    #[ORM\Column(type:'datetime', nullable:true)] private ?\DateTimeInterface $valid_from = null;
    #[ORM\Column(type:'datetime', nullable:true)] private ?\DateTimeInterface $valid_to   = null;
    #[ORM\Column(type:'text',     nullable:true)] private ?string            $notes      = null;

    public function getRelId(): ?string { return $this->rel_id; }
    public function getTenant(): Tenant { return $this->tenant; }
    public function setTenant(Tenant $t): self { $this->tenant=$t; return $this; }
    public function getType(): EavRelationType { return $this->type; }
    public function setType(EavRelationType $t): self { $this->type=$t; return $this; }
    public function getParent(): EavEntity { return $this->parent; }
    public function setParent(EavEntity $p): self { $this->parent=$p; return $this; }
    public function getChild(): EavEntity { return $this->child; }
    public function setChild(EavEntity $c): self { $this->child=$c; return $this; }
    public function getValidFrom(): ?\DateTimeInterface { return $this->valid_from; }
    public function setValidFrom(?\DateTimeInterface $d): self { $this->valid_from=$d; return $this; }
    public function getValidTo(): ?\DateTimeInterface { return $this->valid_to; }
    public function setValidTo(?\DateTimeInterface $d): self { $this->valid_to=$d; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $n): self { $this->notes=$n; return $this; }

    public function __toString(): string
    {
        return ($this->parent?->getName() ?? 'parent').' → '.($this->child?->getName() ?? 'child')
            .' ('.$this->type?->getName().')';
    }
}
