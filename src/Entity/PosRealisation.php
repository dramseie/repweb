<?php
namespace App\Entity;

use App\Repository\PosRealisationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PosRealisationRepository::class)]
#[ORM\Table(name: 'pos_realisation')]
class PosRealisation
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 64, unique: true)]
    private string $code;

    #[ORM\Column(type: 'string', length: 120)]
    private string $label;

    #[ORM\Column(type: 'boolean')]
    private bool $enabled = true;

    #[ORM\Column(type: 'integer')]
    private int $sort_order = 0;

    public function getId(): ?int { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function setCode(string $c): self { $this->code = $c; return $this; }

    public function getLabel(): string { return $this->label; }
    public function setLabel(string $l): self { $this->label = $l; return $this; }

    public function isEnabled(): bool { return $this->enabled; }
    public function setEnabled(bool $e): self { $this->enabled = $e; return $this; }

    public function getSortOrder(): int { return $this->sort_order; }
    public function setSortOrder(int $n): self { $this->sort_order = $n; return $this; }
}
