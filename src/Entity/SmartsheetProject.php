<?php

namespace App\Entity;

use App\Enum\SmartsheetProjectStatus;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'smartsheet_projects')]
class SmartsheetProject
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    #[Groups(['project:list', 'issue:read', 'issue:detail'])]
    private ?int $id = null;

    #[ORM\Column(type: 'bigint', unique: true)]
    #[Assert\NotBlank]
    #[Groups(['project:list', 'issue:read', 'issue:detail'])]
    private ?int $smartsheetSheetId = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['project:list', 'issue:read', 'issue:detail'])]
    private ?string $projectName = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Groups(['project:list', 'issue:read', 'issue:detail'])]
    private ?string $projectCode = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['issue:detail'])]
    private ?string $description = null;

    #[ORM\Column(type: 'date', nullable: true)]
    #[Groups(['issue:detail'])]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: 'date', nullable: true)]
    #[Groups(['issue:detail'])]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(enumType: SmartsheetProjectStatus::class)]
    #[Assert\NotNull]
    #[Groups(['project:list', 'issue:read', 'issue:detail'])]
    private ?SmartsheetProjectStatus $status = SmartsheetProjectStatus::Active;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['issue:detail'])]
    private ?\DateTimeInterface $lastSyncAt = null;

    #[ORM\OneToMany(mappedBy: 'project', targetEntity: SmartsheetTask::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $tasks;

    #[ORM\OneToMany(mappedBy: 'project', targetEntity: Issue::class)]
    private Collection $issues;

    public function __construct()
    {
        $this->tasks = new ArrayCollection();
        $this->issues = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSmartsheetSheetId(): ?int
    {
        return $this->smartsheetSheetId;
    }

    public function setSmartsheetSheetId(int $smartsheetSheetId): self
    {
        $this->smartsheetSheetId = $smartsheetSheetId;

        return $this;
    }

    public function getProjectName(): ?string
    {
        return $this->projectName;
    }

    public function setProjectName(string $projectName): self
    {
        $this->projectName = $projectName;

        return $this;
    }

    public function getProjectCode(): ?string
    {
        return $this->projectCode;
    }

    public function setProjectCode(string $projectCode): self
    {
        $this->projectCode = $projectCode;

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

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeInterface $startDate): self
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeInterface $endDate): self
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getStatus(): ?SmartsheetProjectStatus
    {
        return $this->status;
    }

    public function setStatus(SmartsheetProjectStatus $status): self
    {
        $this->status = $status;

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

    /**
     * @return Collection<int, SmartsheetTask>
     */
    public function getTasks(): Collection
    {
        return $this->tasks;
    }

    public function addTask(SmartsheetTask $task): self
    {
        if (!$this->tasks->contains($task)) {
            $this->tasks->add($task);
            $task->setProject($this);
        }

        return $this;
    }

    public function removeTask(SmartsheetTask $task): self
    {
        if ($this->tasks->removeElement($task) && $task->getProject() === $this) {
            $task->setProject(null);
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
            $issue->setProject($this);
        }

        return $this;
    }

    public function removeIssue(Issue $issue): self
    {
        if ($this->issues->removeElement($issue) && $issue->getProject() === $this) {
            $issue->setProject(null);
        }

        return $this;
    }
}
