<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
#[ORM\Table(name: 'distribution_list')]
#[ORM\HasLifecycleCallbacks]
class DistributionList
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private ?int $id = null;

    // NAME column (uppercase)
    #[ORM\Column(name: 'NAME', type: 'string', length: 190)]
    private string $name = '';

    // DESCRIPTION column (uppercase)
    #[ORM\Column(name: 'DESCRIPTION', type: 'string', length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeInterface $updatedAt;

    // Inverse side of ManyToMany (see MailTemplate)
    #[ORM\ManyToMany(targetEntity: MailTemplate::class, mappedBy: 'distributionLists')]
    private Collection $templates;

    #[ORM\OneToMany(mappedBy: 'list', targetEntity: DistributionMember::class, cascade: ['remove'])]
    private Collection $members;

    public function __construct()
    {
        $this->templates = new ArrayCollection();
        $this->members   = new ArrayCollection();
    }

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

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }

    /** @return Collection<int, DistributionMember> */
    public function getMembers(): Collection { return $this->members; }

    /** @return Collection<int, MailTemplate> */
    public function getTemplates(): Collection { return $this->templates; }
}
