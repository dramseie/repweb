<?php
namespace App\Mig\Entity;

use App\Mig\Repository\ServerRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ServerRepository::class)]
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

    #[ORM\Column(type: 'bigint', nullable: true, options: ['unsigned' => true])]
    private ?string $calendarId = null;

    #[ORM\Column(type: 'bigint', nullable: true, options: ['unsigned' => true])]
    private ?string $slotId = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeInterface $scheduledStart = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeInterface $scheduledEnd = null;

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
    public function getCalendarId(): ?string { return $this->calendarId; }
    public function setCalendarId(?string $v): self { $this->calendarId = $v; return $this; }
    public function getSlotId(): ?string { return $this->slotId; }
    public function setSlotId(?string $v): self { $this->slotId = $v; return $this; }
    public function getScheduledStart(): ?\DateTimeInterface { return $this->scheduledStart; }
    public function setScheduledStart(?\DateTimeInterface $v): self { $this->scheduledStart = $v; return $this; }
    public function getScheduledEnd(): ?\DateTimeInterface { return $this->scheduledEnd; }
    public function setScheduledEnd(?\DateTimeInterface $v): self { $this->scheduledEnd = $v; return $this; }
}
