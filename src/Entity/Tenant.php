<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'tenant', schema: 'eav')]
class Tenant
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'bigint')]
    private ?string $tenant_id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'datetime', nullable: true, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $created_at = null;

    public function getTenantId(): ?string { return $this->tenant_id; }
    public function getName(): string { return $this->name; }
    public function setName(string $n): self { $this->name=$n; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $d): self { $this->description=$d; return $this; }
    public function getCreatedAt(): ?\DateTimeInterface { return $this->created_at; }
    public function setCreatedAt(?\DateTimeInterface $d): self { $this->created_at=$d; return $this; }
    public function __toString(): string { return $this->name ?? ('tenant#'.$this->tenant_id); }
}
