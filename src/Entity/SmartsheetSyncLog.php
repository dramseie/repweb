<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'smartsheet_sync_log')]
class SmartsheetSyncLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(['projects', 'tasks', 'full'])]
    private ?string $syncType = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(['started', 'completed', 'failed'])]
    private ?string $status = 'started';

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $recordsProcessed = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $recordsAdded = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $recordsUpdated = 0;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeInterface $startedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeInterface $completedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSyncType(): ?string
    {
        return $this->syncType;
    }

    public function setSyncType(string $syncType): self
    {
        $this->syncType = $syncType;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getRecordsProcessed(): int
    {
        return $this->recordsProcessed;
    }

    public function setRecordsProcessed(int $recordsProcessed): self
    {
        $this->recordsProcessed = $recordsProcessed;

        return $this;
    }

    public function incrementRecordsProcessed(int $count = 1): self
    {
        $this->recordsProcessed += $count;

        return $this;
    }

    public function getRecordsAdded(): int
    {
        return $this->recordsAdded;
    }

    public function setRecordsAdded(int $recordsAdded): self
    {
        $this->recordsAdded = $recordsAdded;

        return $this;
    }

    public function incrementRecordsAdded(int $count = 1): self
    {
        $this->recordsAdded += $count;

        return $this;
    }

    public function getRecordsUpdated(): int
    {
        return $this->recordsUpdated;
    }

    public function setRecordsUpdated(int $recordsUpdated): self
    {
        $this->recordsUpdated = $recordsUpdated;

        return $this;
    }

    public function incrementRecordsUpdated(int $count = 1): self
    {
        $this->recordsUpdated += $count;

        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): self
    {
        $this->errorMessage = $errorMessage;

        return $this;
    }

    public function getStartedAt(): ?\DateTimeInterface
    {
        return $this->startedAt;
    }

    public function setStartedAt(\DateTimeInterface $startedAt): self
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getCompletedAt(): ?\DateTimeInterface
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeInterface $completedAt): self
    {
        $this->completedAt = $completedAt;

        return $this;
    }
}
