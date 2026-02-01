<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'timesheet_hour')]
class TimesheetHour
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: TimesheetContract::class)]
    #[ORM\JoinColumn(name: 'contract_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private TimesheetContract $contract;

    #[ORM\Column(name: 'work_date', type: 'date_immutable')]
    private \DateTimeInterface $workDate;

    #[ORM\Column(name: 'hours', type: 'decimal', precision: 6, scale: 2)]
    private string $hours = '0.00';

    #[ORM\Column(name: 'start_time', type: 'time_immutable', nullable: true)]
    private ?\DateTimeInterface $startTime = null;

    #[ORM\Column(name: 'end_time', type: 'time_immutable', nullable: true)]
    private ?\DateTimeInterface $endTime = null;

    #[ORM\Column(name: 'comment', type: 'text', nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(name: 'category', type: 'string', length: 64, nullable: true)]
    private ?string $category = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContract(): TimesheetContract
    {
        return $this->contract;
    }

    public function setContract(TimesheetContract $contract): self
    {
        $this->contract = $contract;
        return $this;
    }

    public function getWorkDate(): \DateTimeInterface
    {
        return $this->workDate;
    }

    public function setWorkDate(\DateTimeInterface $workDate): self
    {
        $this->workDate = $workDate;
        return $this;
    }

    public function getHours(): string
    {
        return $this->hours;
    }

    public function setHours(string $hours): self
    {
        $this->hours = $hours;
        return $this;
    }

    public function getStartTime(): ?\DateTimeInterface
    {
        return $this->startTime;
    }

    public function setStartTime(?\DateTimeInterface $startTime): self
    {
        $this->startTime = $startTime;
        return $this;
    }

    public function getEndTime(): ?\DateTimeInterface
    {
        return $this->endTime;
    }

    public function setEndTime(?\DateTimeInterface $endTime): self
    {
        $this->endTime = $endTime;
        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): self
    {
        $this->category = $category;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}
