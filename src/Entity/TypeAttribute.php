<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'type_attribute', schema: 'eav')]
class TypeAttribute
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'bigint')]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'type_id', referencedColumnName: 'type_id', nullable: false)]
    private EntityType $type;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'attribute_id', referencedColumnName: 'attribute_id', nullable: false)]
    private Attribute $attribute;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $is_required = false;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $sort_order = 0;

    public function getId(): ?string { return $this->id; }
    public function getType(): EntityType { return $this->type; }
    public function setType(EntityType $t): self { $this->type=$t; return $this; }
    public function getAttribute(): Attribute { return $this->attribute; }
    public function setAttribute(Attribute $a): self { $this->attribute=$a; return $this; }
    public function isRequired(): bool { return $this->is_required; }
    public function setIsRequired(bool $b): self { $this->is_required=$b; return $this; }
    public function getSortOrder(): int { return $this->sort_order; }
    public function setSortOrder(int $i): self { $this->sort_order=$i; return $this; }
    public function __toString(): string { return $this->getType()->getName().' • '.$this->getAttribute()->getName(); }
}
