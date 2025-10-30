<?php
namespace App\Mig\Entity;

use App\Mig\Repository\CalendarSlotRepository;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CalendarSlotRepository::class)]
#[ORM\Table(name: 'repweb_mig.mig_calendar_slot')]
class CalendarSlot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Calendar::class)]
    #[ORM\JoinColumn(name: 'calendar_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Calendar $calendar;

    #[ORM\Column(length: 150)]
    private string $label;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeInterface $startsAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeInterface $endsAt;

    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $capacity = 1;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    public function getId(): ?string { return $this->id; }
    public function getCalendar(): Calendar { return $this->calendar; }
    public function setCalendar(Calendar $v): self { $this->calendar = $v; return $this; }
    public function getLabel(): string { return $this->label; }
    public function setLabel(string $v): self { $this->label = $v; return $this; }
    public function getStartsAt(): DateTimeInterface { return $this->startsAt; }
    public function setStartsAt(DateTimeInterface $v): self { $this->startsAt = $v; return $this; }
    public function getEndsAt(): DateTimeInterface { return $this->endsAt; }
    public function setEndsAt(DateTimeInterface $v): self { $this->endsAt = $v; return $this; }
    public function getCapacity(): int { return $this->capacity; }
    public function setCapacity(int $v): self { $this->capacity = $v; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): self { $this->notes = $v; return $this; }
}
