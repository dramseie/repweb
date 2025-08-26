<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'eav_value', schema: 'eav')]
class EavValue
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'bigint')]
    private ?string $value_id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'entity_id', referencedColumnName: 'entity_id', nullable: false)]
    private EavEntity $entity;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'attribute_id', referencedColumnName: 'attribute_id', nullable: false)]
    private Attribute $attribute;

    #[ORM\Column(type: 'text', nullable: true)]     private ?string $value_string = null;
    #[ORM\Column(type: 'bigint', nullable: true)]   private ?string $value_integer = null;
    #[ORM\Column(type: 'decimal', precision: 20, scale: 6, nullable: true)]
    private ?string $value_decimal = null;
    #[ORM\Column(type: 'boolean', nullable: true)]  private ?bool   $value_boolean = null;
    #[ORM\Column(type: 'datetime', nullable: true)] private ?\DateTimeInterface $value_datetime = null;
    #[ORM\Column(type: 'json', nullable: true)]     private ?array  $value_json = null;

    #[ORM\Column(type: 'datetime', nullable: true, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $created_at = null;

    public function getValueId(): ?string { return $this->value_id; }
    public function getEntity(): EavEntity { return $this->entity; }
    public function setEntity(EavEntity $e): self { $this->entity=$e; return $this; }
    public function getAttribute(): Attribute { return $this->attribute; }
    public function setAttribute(Attribute $a): self { $this->attribute=$a; return $this; }

    public function getValueString(): ?string { return $this->value_string; }
    public function setValueString(?string $v): self { $this->value_string=$v; return $this; }
    public function getValueInteger(): ?string { return $this->value_integer; }
    public function setValueInteger(?string $v): self { $this->value_integer=$v; return $this; }
    public function getValueDecimal(): ?string { return $this->value_decimal; }
    public function setValueDecimal(?string $v): self { $this->value_decimal=$v; return $this; }
    public function isValueBoolean(): ?bool { return $this->value_boolean; }
    public function setValueBoolean(?bool $v): self { $this->value_boolean=$v; return $this; }
    public function getValueDatetime(): ?\DateTimeInterface { return $this->value_datetime; }
    public function setValueDatetime(?\DateTimeInterface $v): self { $this->value_datetime=$v; return $this; }
    public function getValueJson(): ?array { return $this->value_json; }
    public function setValueJson(?array $v): self { $this->value_json=$v; return $this; }

    public function __toString(): string
    {
        $val = $this->value_string ?? $this->value_integer ?? $this->value_decimal ?? ($this->value_boolean===null ? 'null' : ($this->value_boolean?'true':'false'));
        return ($this->attribute?->getName() ?? 'attr').'='.$val;
    }
}
