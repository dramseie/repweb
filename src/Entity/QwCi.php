<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'qw_ci')]
#[ORM\UniqueConstraint(name: 'uq_tenant_ci', columns: ['tenant_id','ci_key'])]
class QwCi
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private string $tenantId;

    #[ORM\Column(length: 191)]
    private string $ciKey;

    #[ORM\Column(length: 255)]
    private string $ciName;

    public function getId(): ?string { return $this->id ?? null; }
    public function getTenantId(): string { return $this->tenantId; }
    public function setTenantId(string $t): void { $this->tenantId = $t; }
    public function getCiKey(): string { return $this->ciKey; }
    public function setCiKey(string $k): void { $this->ciKey = $k; }
    public function getCiName(): string { return $this->ciName; }
    public function setCiName(string $n): void { $this->ciName = $n; }
}
