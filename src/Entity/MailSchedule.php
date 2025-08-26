<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'mail_schedule')]
#[ORM\HasLifecycleCallbacks]
class MailSchedule
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: MailTemplate::class)]
    #[ORM\JoinColumn(name: 'template_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private MailTemplate $template;

    #[ORM\Column(name: 'timezone', type: 'string', length: 64)]
    private string $timezone = 'Europe/Paris';

    #[ORM\Column(name: 'years', type: 'json', nullable: true)]
    private ?array $years = null;

    #[ORM\Column(name: 'months', type: 'json', nullable: true)]
    private ?array $months = null;

    #[ORM\Column(name: 'month_days', type: 'json', nullable: true)]
    private ?array $monthDays = null;

    #[ORM\Column(name: 'weekdays', type: 'json', nullable: true)]
    private ?array $weekdays = null; // ['MO','TU','WE','TH','FR','SA','SU']

    #[ORM\Column(name: 'hours', type: 'json', nullable: true)]
    private ?array $hours = null; // 0..23

    // MINUTE column (uppercase)
    #[ORM\Column(name: 'MINUTE', type: 'integer')]
    private int $minute = 0;

    #[ORM\Column(name: 'enabled', type: 'boolean')]
    private bool $enabled = true;

    #[ORM\Column(name: 'next_run_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $nextRunAt = null;

    #[ORM\Column(name: 'last_run_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastRunAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeInterface $updatedAt;

    #[ORM\PrePersist]
    public function onCreate(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // getters/setters
    public function getId(): ?int { return $this->id; }
    public function getTemplate(): MailTemplate { return $this->template; }
    public function setTemplate(MailTemplate $t): self { $this->template = $t; return $this; }

    public function getTimezone(): string { return $this->timezone; }
    public function setTimezone(string $tz): self { $this->timezone = $tz; return $this; }

    public function getYears(): ?array { return $this->years; }
    public function setYears(?array $v): self { $this->years = $v; return $this; }

    public function getMonths(): ?array { return $this->months; }
    public function setMonths(?array $v): self { $this->months = $v; return $this; }

    public function getMonthDays(): ?array { return $this->monthDays; }
    public function setMonthDays(?array $v): self { $this->monthDays = $v; return $this; }

    public function getWeekdays(): ?array { return $this->weekdays; }
    public function setWeekdays(?array $v): self { $this->weekdays = $v; return $this; }

    public function getHours(): ?array { return $this->hours; }
    public function setHours(?array $v): self { $this->hours = $v; return $this; }

    public function getMinute(): int { return $this->minute; }
    public function setMinute(int $m): self { $this->minute = $m; return $this; }

    public function isEnabled(): bool { return $this->enabled; }
    public function setEnabled(bool $v): self { $this->enabled = $v; return $this; }

    public function getNextRunAt(): ?\DateTimeInterface { return $this->nextRunAt; }
    public function setNextRunAt(?\DateTimeInterface $d): self { $this->nextRunAt = $d; return $this; }

    public function getLastRunAt(): ?\DateTimeInterface { return $this->lastRunAt; }
    public function setLastRunAt(?\DateTimeInterface $d): self { $this->lastRunAt = $d; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }
}
