<?php
namespace App\Mig\Entity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: App\Mig\Repository\ServerRepository::class)]
#[ORM\Table(name: 'repweb_mig.mig_server')]
class Server
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private string $containerId;

    #[ORM\Column(length: 200)]
    private string $hostname;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $application = null;

    #[ORM\Column(length: 20, options: ['default' => 'LiftShift'])]
    private string $method = 'LiftShift';

    #[ORM\Column(type: 'bigint', nullable: true, options: ['unsigned' => true])]
    private ?string $ciEntityId = null;

    public function getId(): ?string { return $this->id; }
    public function getContainerId(): string { return $this->containerId; }
    public function setContainerId(string $v): self { $this->containerId = $v; return $this; }
    public function getHostname(): string { return $this->hostname; }
    public function setHostname(string $v): self { $this->hostname = $v; return $this; }
    public function getApplication(): ?string { return $this->application; }
    public function setApplication(?string $v): self { $this->application = $v; return $this; }
    public function getMethod(): string { return $this->method; }
    public function setMethod(string $v): self { $this->method = $v; return $this; }
    public function getCiEntityId(): ?string { return $this->ciEntityId; }
    public function setCiEntityId(?string $v): self { $this->ciEntityId = $v; return $this; }
}
