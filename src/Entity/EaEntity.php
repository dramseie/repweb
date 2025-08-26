<?php

namespace App\Entity;

use App\Repository\EaEntityRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EaEntityRepository::class)]
class EaEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $eaProperty = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEaProperty(): ?string
    {
        return $this->eaProperty;
    }

    public function setEaProperty(string $eaProperty): static
    {
        $this->eaProperty = $eaProperty;

        return $this;
    }
}
