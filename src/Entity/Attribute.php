<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'attribute', schema: 'eav')]
class Attribute
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'bigint')]
    private ?string $attribute_id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'tenant_id', referencedColumnName: 'tenant_id', nullable: false)]
    private Tenant $tenant;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column(length: 16)]
    private string $data_type; // string|integer|decimal|boolean|datetime|json

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'datetime', nullable: true, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $created_at = null;

    public function getAttributeId(): ?string { return $this->attribute_id; }
    public function getTenant(): Tenant { return $this->tenant; }
    public function setTenant(Tenant $t): self { $this->tenant=$t; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $n): self { $this->name=$n; return $this; }
    public function getDataType(): string { return $this->data_type; }
    public function setDataType(string $d): self { $this->data_type=$d; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $d): self { $this->description=$d; return $this; }
    public function getCreatedAt(): ?\DateTimeInterface { return $this->created_at; }
    public function setCreatedAt(?\DateTimeInterface $d): self { $this->created_at=$d; return $this; }
    public function __toString(): string { return $this->name ?? ('attr#'.$this->attribute_id); }
}
