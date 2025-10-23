<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'qw_item')]
class QwItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'questionnaire_id', referencedColumnName: 'id', nullable: false)]
    private QwQuestionnaire $questionnaire;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?QwItem $parent = null;

    #[ORM\Column(enumType: QwItemType::class)] private QwItemType $type;
    #[ORM\Column(length: 255)] private string $title;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $help = null;
    #[ORM\Column] private int $sort = 0;
    #[ORM\Column(length: 64, nullable: true)] private ?string $outline = null;
    #[ORM\Column(options: ['default' => 0])] private bool $required = false;
    #[ORM\Column(type: 'json', nullable: true)] private ?array $visibleWhen = null;
    #[ORM\Column] private \DateTimeInterface $createdAt;
    #[ORM\Column] private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?string { return $this->id; }
    public function getQuestionnaire(): QwQuestionnaire { return $this->questionnaire; }
    public function setQuestionnaire(QwQuestionnaire $q): void { $this->questionnaire = $q; }
    public function getParent(): ?QwItem { return $this->parent; }
    public function setParent(?QwItem $p): void { $this->parent = $p; }
    public function getType(): QwItemType { return $this->type; }
    public function setType(QwItemType $t): void { $this->type = $t; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $t): void { $this->title = $t; }
    public function getHelp(): ?string { return $this->help; }
    public function setHelp(?string $h): void { $this->help = $h; }
    public function getSort(): int { return $this->sort; }
    public function setSort(int $s): void { $this->sort = $s; }
    public function getOutline(): ?string { return $this->outline; }
    public function setOutline(?string $o): void { $this->outline = $o; }
    public function isRequired(): bool { return $this->required; }
    public function setRequired(bool $r): void { $this->required = $r; }
    public function getVisibleWhen(): ?array { return $this->visibleWhen; }
    public function setVisibleWhen(?array $v): void { $this->visibleWhen = $v; }
}
