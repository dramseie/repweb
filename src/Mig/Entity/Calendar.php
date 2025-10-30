<?php
namespace App\Mig\Entity;

use App\Mig\Repository\CalendarRepository;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CalendarRepository::class)]
#[ORM\Table(name: 'repweb_mig.mig_calendar')]
class Calendar
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\Column(length: 20)]
    private string $method = 'LiftShift';

    #[ORM\Column(length: 200)]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 64, options: ['default' => 'UTC'])]
    private string $timezone = 'UTC';

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeInterface $activeFrom = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeInterface $activeTo = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeInterface $updatedAt;

    public function __construct()
    {
        $now = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?string { return $this->id; }
    public function getMethod(): string { return $this->method; }
    public function setMethod(string $v): self { $this->method = $v; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $v): self { $this->name = $v; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): self { $this->description = $v; return $this; }
    public function getTimezone(): string { return $this->timezone; }
    public function setTimezone(string $v): self { $this->timezone = $v; return $this; }
    public function getActiveFrom(): ?DateTimeInterface { return $this->activeFrom; }
    public function setActiveFrom(?DateTimeInterface $v): self { $this->activeFrom = $v; return $this; }
    public function getActiveTo(): ?DateTimeInterface { return $this->activeTo; }
    public function setActiveTo(?DateTimeInterface $v): self { $this->activeTo = $v; return $this; }
    public function getCreatedAt(): DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(DateTimeInterface $v): self { $this->createdAt = $v; return $this; }
    public function getUpdatedAt(): DateTimeInterface { return $this->updatedAt; }
    public function setUpdatedAt(DateTimeInterface $v): self { $this->updatedAt = $v; return $this; }
}
