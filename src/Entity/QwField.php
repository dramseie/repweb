<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'qw_field')]
class QwField
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'item_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private QwItem $item;

    #[ORM\Column(enumType: QwUiType::class)] private QwUiType $uiType;
    #[ORM\Column(length: 255, nullable: true)] private ?string $placeholder = null;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $defaultValue = null;
    #[ORM\Column(type: 'float', nullable: true)] private ?float $minValue = null;
    #[ORM\Column(type: 'float', nullable: true)] private ?float $maxValue = null;
    #[ORM\Column(type: 'float', nullable: true)] private ?float $stepValue = null;
    #[ORM\Column(length: 255, nullable: true)] private ?string $validationRegex = null;
    #[ORM\Column(type: 'json', nullable: true)] private ?array $optionsJson = null;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $optionsSql = null;
    #[ORM\Column(type: 'json', nullable: true)] private ?array $chainSql = null;
    #[ORM\Column(length: 255, nullable: true)] private ?string $acceptMime = null;
    #[ORM\Column(type: 'integer', nullable: true)] private ?int $maxSizeMb = null;
    #[ORM\Column(length: 128, nullable: true)] private ?string $uniqueKey = null;

    public function getId(): ?string { return $this->id; }
    public function setItem(QwItem $i): void { $this->item = $i; }
    public function setUiType(QwUiType $t): void { $this->uiType = $t; }
    public function setPlaceholder(?string $v): void { $this->placeholder = $v; }
    public function setDefaultValue(?string $v): void { $this->defaultValue = $v; }
    public function setMinValue($v): void { $this->minValue = $v !== null ? (float)$v : null; }
    public function setMaxValue($v): void { $this->maxValue = $v !== null ? (float)$v : null; }
    public function setStepValue($v): void { $this->stepValue = $v !== null ? (float)$v : null; }
    public function setValidationRegex(?string $v): void { $this->validationRegex = $v; }
    public function setOptionsJson($v): void { $this->optionsJson = $v; }
    public function setOptionsSql(?string $v): void { $this->optionsSql = $v; }
    public function setChainSql($v): void { $this->chainSql = $v; }
    public function setAcceptMime(?string $v): void { $this->acceptMime = $v; }
    public function setMaxSizeMb($v): void { $this->maxSizeMb = $v !== null ? (int)$v : null; }
}
