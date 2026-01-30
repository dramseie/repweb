<?php

namespace App\Entity;

use App\Enum\IssuePriority;
use App\Enum\IssueResolution;
use App\Enum\IssueSeverity;
use App\Enum\IssueStatus;
use App\Enum\IssueType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'issues')]
class Issue
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    #[Groups(['issue:read', 'issue:detail'])]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Assert\NotBlank(groups: ['issue:write'])]
    #[Groups(['issue:read', 'issue:detail'])]
    private ?string $issueNumber = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(groups: ['issue:write'])]
    #[Groups(['issue:read', 'issue:detail'])]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['issue:read', 'issue:detail'])]
    private ?string $description = null;

    #[ORM\Column(enumType: IssueType::class)]
    #[Assert\NotNull(groups: ['issue:write'])]
    #[Groups(['issue:read', 'issue:detail'])]
    private ?IssueType $issueType = null;

    #[ORM\Column(enumType: IssuePriority::class)]
    #[Assert\NotNull]
    #[Groups(['issue:read', 'issue:detail'])]
    private ?IssuePriority $priority = IssuePriority::Medium;

    #[ORM\Column(enumType: IssueSeverity::class)]
    #[Assert\NotNull]
    #[Groups(['issue:read', 'issue:detail'])]
    private ?IssueSeverity $severity = IssueSeverity::Major;

    #[ORM\Column(enumType: IssueStatus::class)]
    #[Assert\NotNull]
    #[Groups(['issue:read', 'issue:detail'])]
    private ?IssueStatus $status = IssueStatus::New;

    #[ORM\Column(enumType: IssueResolution::class, nullable: true)]
    #[Groups(['issue:detail'])]
    private ?IssueResolution $resolution = null;

    #[ORM\ManyToOne(targetEntity: SmartsheetProject::class, inversedBy: 'issues')]
    #[Groups(['issue:read', 'issue:detail'])]
    private ?SmartsheetProject $project = null;

    #[ORM\ManyToOne(targetEntity: SmartsheetTask::class, inversedBy: 'issues')]
    #[Groups(['issue:read', 'issue:detail'])]
    private ?SmartsheetTask $task = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['issue:read', 'issue:detail'])]
    private ?User $reporter = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[Groups(['issue:read', 'issue:detail'])]
    private ?User $assignee = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[Groups(['issue:detail'])]
    private ?self $epic = null;

    #[ORM\ManyToOne(targetEntity: Sprint::class, inversedBy: 'issues')]
    #[Groups(['issue:read', 'issue:detail'])]
    private ?Sprint $sprint = null;

    #[ORM\Column(type: 'date', nullable: true)]
    #[Groups(['issue:read', 'issue:detail'])]
    private ?\DateTimeInterface $dueDate = null;

    #[ORM\Column(type: 'date', nullable: true)]
    #[Groups(['issue:detail'])]
    private ?\DateTimeInterface $taskDueDate = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero]
    #[Groups(['issue:detail'])]
    private ?string $estimatedHours = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero]
    #[Groups(['issue:detail'])]
    private ?string $actualHours = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Assert\Type('array')]
    #[Groups(['issue:read', 'issue:detail'])]
    private ?array $labels = null;

    #[ORM\ManyToMany(targetEntity: IssueLabel::class, inversedBy: 'issues', cascade: ['persist'])]
    #[ORM\JoinTable(name: 'issue_label_map')]
    #[Groups(['issue:detail'])]
    private Collection $labelEntities;

    #[ORM\OneToMany(mappedBy: 'issue', targetEntity: IssueComment::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['issue:detail'])]
    private Collection $comments;

    #[ORM\OneToMany(mappedBy: 'issue', targetEntity: IssueAttachment::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['issue:detail'])]
    private Collection $attachments;

    #[ORM\OneToMany(mappedBy: 'issue', targetEntity: IssueActivity::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['issue:detail'])]
    private Collection $activities;

    #[ORM\OneToMany(mappedBy: 'issue', targetEntity: IssueWatcher::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['issue:detail'])]
    private Collection $watchers;

    #[ORM\OneToMany(mappedBy: 'sourceIssue', targetEntity: IssueLink::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['issue:detail'])]
    private Collection $sourceLinks;

    #[ORM\OneToMany(mappedBy: 'targetIssue', targetEntity: IssueLink::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['issue:detail'])]
    private Collection $targetLinks;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['issue:read', 'issue:detail'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['issue:detail'])]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['issue:detail'])]
    private ?\DateTimeInterface $resolvedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['issue:detail'])]
    private ?\DateTimeInterface $closedAt = null;

    public function __construct()
    {
        $this->labelEntities = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->attachments = new ArrayCollection();
        $this->activities = new ArrayCollection();
        $this->watchers = new ArrayCollection();
        $this->sourceLinks = new ArrayCollection();
        $this->targetLinks = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIssueNumber(): ?string
    {
        return $this->issueNumber;
    }

    public function setIssueNumber(string $issueNumber): self
    {
        $this->issueNumber = $issueNumber;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

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

    public function getIssueType(): ?IssueType
    {
        return $this->issueType;
    }

    public function setIssueType(IssueType $issueType): self
    {
        $this->issueType = $issueType;

        return $this;
    }

    public function getPriority(): ?IssuePriority
    {
        return $this->priority;
    }

    public function setPriority(IssuePriority $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    public function getSeverity(): ?IssueSeverity
    {
        return $this->severity;
    }

    public function setSeverity(IssueSeverity $severity): self
    {
        $this->severity = $severity;

        return $this;
    }

    public function getStatus(): ?IssueStatus
    {
        return $this->status;
    }

    public function setStatus(IssueStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getResolution(): ?IssueResolution
    {
        return $this->resolution;
    }

    public function setResolution(?IssueResolution $resolution): self
    {
        $this->resolution = $resolution;

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

    public function getTask(): ?SmartsheetTask
    {
        return $this->task;
    }

    public function setTask(?SmartsheetTask $task): self
    {
        $this->task = $task;

        return $this;
    }

    public function getReporter(): ?User
    {
        return $this->reporter;
    }

    public function setReporter(User $reporter): self
    {
        $this->reporter = $reporter;

        return $this;
    }

    public function getAssignee(): ?User
    {
        return $this->assignee;
    }

    public function setAssignee(?User $assignee): self
    {
        $this->assignee = $assignee;

        return $this;
    }

    public function getEpic(): ?self
    {
        return $this->epic;
    }

    public function setEpic(?self $epic): self
    {
        $this->epic = $epic;

        return $this;
    }

    public function getSprint(): ?Sprint
    {
        return $this->sprint;
    }

    public function setSprint(?Sprint $sprint): self
    {
        $this->sprint = $sprint;

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

    public function getTaskDueDate(): ?\DateTimeInterface
    {
        return $this->taskDueDate;
    }

    public function setTaskDueDate(?\DateTimeInterface $taskDueDate): self
    {
        $this->taskDueDate = $taskDueDate;

        return $this;
    }

    public function getEstimatedHours(): ?string
    {
        return $this->estimatedHours;
    }

    public function setEstimatedHours(?string $estimatedHours): self
    {
        $this->estimatedHours = $estimatedHours;

        return $this;
    }

    public function getActualHours(): ?string
    {
        return $this->actualHours;
    }

    public function setActualHours(?string $actualHours): self
    {
        $this->actualHours = $actualHours;

        return $this;
    }

    public function getLabels(): ?array
    {
        return $this->labels;
    }

    public function setLabels(?array $labels): self
    {
        $this->labels = $labels;

        return $this;
    }

    /**
     * @return Collection<int, IssueLabel>
     */
    public function getLabelEntities(): Collection
    {
        return $this->labelEntities;
    }

    public function addLabelEntity(IssueLabel $label): self
    {
        if (!$this->labelEntities->contains($label)) {
            $this->labelEntities->add($label);
        }

        return $this;
    }

    public function removeLabelEntity(IssueLabel $label): self
    {
        $this->labelEntities->removeElement($label);

        return $this;
    }

    /**
     * @return Collection<int, IssueComment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(IssueComment $comment): self
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setIssue($this);
        }

        return $this;
    }

    public function removeComment(IssueComment $comment): self
    {
        if ($this->comments->removeElement($comment) && $comment->getIssue() === $this) {
            $comment->setIssue(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, IssueAttachment>
     */
    public function getAttachments(): Collection
    {
        return $this->attachments;
    }

    public function addAttachment(IssueAttachment $attachment): self
    {
        if (!$this->attachments->contains($attachment)) {
            $this->attachments->add($attachment);
            $attachment->setIssue($this);
        }

        return $this;
    }

    public function removeAttachment(IssueAttachment $attachment): self
    {
        if ($this->attachments->removeElement($attachment) && $attachment->getIssue() === $this) {
            $attachment->setIssue(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, IssueActivity>
     */
    public function getActivities(): Collection
    {
        return $this->activities;
    }

    public function addActivity(IssueActivity $activity): self
    {
        if (!$this->activities->contains($activity)) {
            $this->activities->add($activity);
            $activity->setIssue($this);
        }

        return $this;
    }

    public function removeActivity(IssueActivity $activity): self
    {
        if ($this->activities->removeElement($activity) && $activity->getIssue() === $this) {
            $activity->setIssue(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, IssueWatcher>
     */
    public function getWatchers(): Collection
    {
        return $this->watchers;
    }

    public function addWatcher(IssueWatcher $watcher): self
    {
        if (!$this->watchers->contains($watcher)) {
            $this->watchers->add($watcher);
            $watcher->setIssue($this);
        }

        return $this;
    }

    public function removeWatcher(IssueWatcher $watcher): self
    {
        if ($this->watchers->removeElement($watcher) && $watcher->getIssue() === $this) {
            $watcher->setIssue(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, IssueLink>
     */
    public function getSourceLinks(): Collection
    {
        return $this->sourceLinks;
    }

    public function addSourceLink(IssueLink $link): self
    {
        if (!$this->sourceLinks->contains($link)) {
            $this->sourceLinks->add($link);
            $link->setSourceIssue($this);
        }

        return $this;
    }

    public function removeSourceLink(IssueLink $link): self
    {
        if ($this->sourceLinks->removeElement($link) && $link->getSourceIssue() === $this) {
            $link->setSourceIssue(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, IssueLink>
     */
    public function getTargetLinks(): Collection
    {
        return $this->targetLinks;
    }

    public function addTargetLink(IssueLink $link): self
    {
        if (!$this->targetLinks->contains($link)) {
            $this->targetLinks->add($link);
            $link->setTargetIssue($this);
        }

        return $this;
    }

    public function removeTargetLink(IssueLink $link): self
    {
        if ($this->targetLinks->removeElement($link) && $link->getTargetIssue() === $this) {
            $link->setTargetIssue(null);
        }

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getResolvedAt(): ?\DateTimeInterface
    {
        return $this->resolvedAt;
    }

    public function setResolvedAt(?\DateTimeInterface $resolvedAt): self
    {
        $this->resolvedAt = $resolvedAt;

        return $this;
    }

    public function getClosedAt(): ?\DateTimeInterface
    {
        return $this->closedAt;
    }

    public function setClosedAt(?\DateTimeInterface $closedAt): self
    {
        $this->closedAt = $closedAt;

        return $this;
    }
}
