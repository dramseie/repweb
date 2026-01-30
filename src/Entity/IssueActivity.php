<?php

namespace App\Entity;

use App\Enum\IssueActivityType;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'issue_activity')]
class IssueActivity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    #[Groups(['issue:detail'])]
    private ?int $id = null;

    #[ORM\Column(enumType: IssueActivityType::class)]
    #[Assert\NotNull]
    #[Groups(['issue:detail'])]
    private ?IssueActivityType $activityType = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['issue:detail'])]
    private ?string $fieldName = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['issue:detail'])]
    private ?string $oldValue = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['issue:detail'])]
    private ?string $newValue = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['issue:detail'])]
    private ?string $comment = null;

    #[ORM\ManyToOne(targetEntity: Issue::class, inversedBy: 'activities')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Issue $issue = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[Groups(['issue:detail'])]
    private ?User $user = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['issue:detail'])]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getActivityType(): ?IssueActivityType
    {
        return $this->activityType;
    }

    public function setActivityType(IssueActivityType $activityType): self
    {
        $this->activityType = $activityType;

        return $this;
    }

    public function getFieldName(): ?string
    {
        return $this->fieldName;
    }

    public function setFieldName(?string $fieldName): self
    {
        $this->fieldName = $fieldName;

        return $this;
    }

    public function getOldValue(): ?string
    {
        return $this->oldValue;
    }

    public function setOldValue(?string $oldValue): self
    {
        $this->oldValue = $oldValue;

        return $this;
    }

    public function getNewValue(): ?string
    {
        return $this->newValue;
    }

    public function setNewValue(?string $newValue): self
    {
        $this->newValue = $newValue;

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

    public function getIssue(): ?Issue
    {
        return $this->issue;
    }

    public function setIssue(?Issue $issue): self
    {
        $this->issue = $issue;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

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
}
