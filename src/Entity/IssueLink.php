<?php

namespace App\Entity;

use App\Enum\IssueLinkType;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'issue_links')]
class IssueLink
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    #[Groups(['issue:detail'])]
    private ?int $id = null;

    #[ORM\Column(enumType: IssueLinkType::class)]
    #[Assert\NotNull]
    #[Groups(['issue:detail'])]
    private ?IssueLinkType $linkType = null;

    #[ORM\ManyToOne(targetEntity: Issue::class, inversedBy: 'sourceLinks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Issue $sourceIssue = null;

    #[ORM\ManyToOne(targetEntity: Issue::class, inversedBy: 'targetLinks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['issue:detail'])]
    private ?Issue $targetIssue = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[Groups(['issue:detail'])]
    private ?User $createdBy = null;

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

    public function getLinkType(): ?IssueLinkType
    {
        return $this->linkType;
    }

    public function setLinkType(IssueLinkType $linkType): self
    {
        $this->linkType = $linkType;

        return $this;
    }

    public function getSourceIssue(): ?Issue
    {
        return $this->sourceIssue;
    }

    public function setSourceIssue(?Issue $sourceIssue): self
    {
        $this->sourceIssue = $sourceIssue;

        return $this;
    }

    public function getTargetIssue(): ?Issue
    {
        return $this->targetIssue;
    }

    public function setTargetIssue(?Issue $targetIssue): self
    {
        $this->targetIssue = $targetIssue;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): self
    {
        $this->createdBy = $createdBy;

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
