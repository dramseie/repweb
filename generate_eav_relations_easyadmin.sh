#!/usr/bin/env bash
set -euo pipefail

BASE_ENT="src/Entity"
BASE_CTL="src/Controller/Admin"
mkdir -p "$BASE_ENT" "$BASE_CTL"

# --- Entities (Doctrine, mapped to eav.* tables) ---
cat > "$BASE_ENT/EavRelationType.php" <<'PHP'
<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'eav_relation_type', schema: 'eav')]
class EavRelationType
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type:'bigint')]
    private ?string $rel_type_id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name:'tenant_id', referencedColumnName:'tenant_id', nullable:false)]
    private Tenant $tenant;

    #[ORM\Column(length:100)]
    private string $name;

    #[ORM\Column(type:'text', nullable:true)]
    private ?string $description = null;

    #[ORM\Column(type:'boolean', options:['default'=>true])]
    private bool $is_directed = true;

    #[ORM\Column(type:'datetime', nullable:true, options:['default'=>'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $created_at = null;

    public function getRelTypeId(): ?string { return $this->rel_type_id; }
    public function getTenant(): Tenant { return $this->tenant; }
    public function setTenant(Tenant $t): self { $this->tenant=$t; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $n): self { $this->name=$n; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $d): self { $this->description=$d; return $this; }
    public function isDirected(): bool { return $this->is_directed; }
    public function setIsDirected(bool $b): self { $this->is_directed=$b; return $this; }
    public function getCreatedAt(): ?\DateTimeInterface { return $this->created_at; }
    public function setCreatedAt(?\DateTimeInterface $d): self { $this->created_at=$d; return $this; }
    public function __toString(): string { return $this->name ?? ('reltype#'.$this->rel_type_id); }
}
PHP

cat > "$BASE_ENT/EavRelation.php" <<'PHP'
<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;

#[ORM.Entity]
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

    public function __toString(): string {
        return ($this->parent?->getName() ?? 'parent').' → '.($this->child?->getName() ?? 'child').
               ' ('.$this->type?->getName().')';
    }
}
PHP

# --- CRUD controllers extending your BaseCrudController ---
cat > "$BASE_CTL/EavRelationTypeCrudController.php" <<'PHP'
<?php
namespace App\Controller\Admin;

use App\Entity\EavRelationType;
use App\Controller\Admin\BaseCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{AssociationField,TextField,TextareaField,BooleanField,IdField,DateTimeField};

class EavRelationTypeCrudController extends BaseCrudController
{
    public static function getEntityFqcn(): string { return EavRelationType::class; }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('relTypeId')->onlyOnIndex(),
            AssociationField::new('tenant'),
            TextField::new('name'),
            TextareaField::new('description')->hideOnIndex(),
            BooleanField::new('isDirected')->renderAsSwitch(false),
            DateTimeField::new('createdAt')->onlyOnIndex(),
        ];
    }
}
PHP

cat > "$BASE_CTL/EavRelationCrudController.php" <<'PHP'
<?php
namespace App\Controller\Admin;

use App\Entity\EavRelation;
use App\Controller\Admin\BaseCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{
    AssociationField, DateTimeField, TextareaField, IdField
};

class EavRelationCrudController extends BaseCrudController
{
    public static function getEntityFqcn(): string { return EavRelation::class; }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('relId')->onlyOnIndex(),
            AssociationField::new('tenant'),
            AssociationField::new('type'),
            AssociationField::new('parent'),
            AssociationField::new('child'),
            DateTimeField::new('validFrom')->hideOnIndex(),
            DateTimeField::new('validTo')->hideOnIndex(),
            TextareaField::new('notes')->hideOnIndex(),
        ];
    }
}
PHP

echo "✅ Relation entities + CRUD controllers generated (using BaseCrudController)."
