<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'smartsheet_tasks')]
class SmartsheetTask
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    #[Groups(['task:list', 'issue:read', 'issue:detail'])]
    private ?int $id = null;

    #[ORM\Column(type: 'bigint', unique: true)]
    #[Assert\NotBlank]
    #[Groups(['task:list', 'issue:read', 'issue:detail'])]
    private ?int $smartsheetRowId = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['task:list', 'issue:read', 'issue:detail'])]
    private ?string $taskName = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['task:list', 'issue:read', 'issue:detail'])]
    private ?string $taskNumber = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['issue:detail'])]
    private ?string $description = null;

    #[ORM\Column(type: 'date', nullable: true)]
    #[Groups(['task:list', 'issue:detail'])]
    private ?\DateTimeInterface $dueDate = null;

    #[ORM\Column(type: 'date', nullable: true)]
    #[Groups(['issue:detail'])]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['issue:detail'])]
    private ?string $assignedTo = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['task:list', 'issue:detail'])]
    private ?string $status = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Range(min: 0, max: 100)]
    #[Groups(['task:list', 'issue:detail'])]
    private ?int $progress = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['issue:detail'])]
    private ?\DateTimeInterface $lastSyncAt = null;

    #[ORM\ManyToOne(targetEntity: SmartsheetProject::class, inversedBy: 'tasks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['task:list', 'issue:detail'])]
    private ?SmartsheetProject $project = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[Groups(['issue:detail'])]
    private ?self $parentTask = null;

    #[ORM\OneToMany(mappedBy: 'parentTask', targetEntity: self::class, cascade: ['persist'], orphanRemoval: false)]
    private Collection $children;

    #[ORM\OneToMany(mappedBy: 'task', targetEntity: Issue::class)]
    private Collection $issues;

    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->issues = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSmartsheetRowId(): ?int
    {
        return $this->smartsheetRowId;
    }

    public function setSmartsheetRowId(int $smartsheetRowId): self
    {
        $this->smartsheetRowId = $smartsheetRowId;

        return $this;
    }

    public function getTaskName(): ?string
    {
        return $this->taskName;
    }

    public function setTaskName(string $taskName): self
    {
        $this->taskName = $taskName;

        return $this;
    }

    public function getTaskNumber(): ?string
    {
        return $this->taskNumber;
    }

    public function setTaskNumber(?string $taskNumber): self
    {
        $this->taskNumber = $taskNumber;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getDueDate(): ?\DateTimeInterface
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTimeInterface $dueDate): self
    {
        $this->dueDate = $dueDate;

        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeInterface $startDate): self
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getAssignedTo(): ?string
    {
        return $this->assignedTo;
    }

    public function setAssignedTo(?string $assignedTo): self
    {
        $this->assignedTo = $assignedTo;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getProgress(): ?int
    {
        return $this->progress;
    }

    public function setProgress(?int $progress): self
    {
        $this->progress = $progress;

        return $this;
    }

    public function getLastSyncAt(): ?\DateTimeInterface
    {
        return $this->lastSyncAt;
    }

    public function setLastSyncAt(?\DateTimeInterface $lastSyncAt): self
    {
        $this->lastSyncAt = $lastSyncAt;

        return $this;
    }

    public function getProject(): ?SmartsheetProject
    {
        return $this->project;
    }

    public function setProject(?SmartsheetProject $project): self
    {
        $this->project = $project;

        return $this;
    }

    public function getParentTask(): ?self
    {
        return $this->parentTask;
    }

    public function setParentTask(?self $parentTask): self
    {
        $this->parentTask = $parentTask;

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(self $child): self
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParentTask($this);
        }

        return $this;
    }

    public function removeChild(self $child): self
    {
        if ($this->children->removeElement($child) && $child->getParentTask() === $this) {
            $child->setParentTask(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, Issue>
     */
    public function getIssues(): Collection
    {
        return $this->issues;
    }

    public function addIssue(Issue $issue): self
    {
        if (!$this->issues->contains($issue)) {
            $this->issues->add($issue);
            $issue->setTask($this);
        }

        return $this;
    }

    public function removeIssue(Issue $issue): self
    {
        if ($this->issues->removeElement($issue) && $issue->getTask() === $this) {
            $issue->setTask(null);
        }

        return $this;
    }
}
