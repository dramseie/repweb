<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'report')]
class Report
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'repid', type: 'integer')]
    private ?int $repid = null;

    #[ORM\Column(name: 'reptype', type: 'string', length: 255, nullable: true)]
    private ?string $reptype = null;

    #[ORM\Column(name: 'repshort', type: 'string', length: 50, nullable: true)]
    private ?string $repshort = null;

    #[ORM\Column(name: 'reptitle', type: 'string', length: 255, nullable: true)]
    private ?string $reptitle = null;

    #[ORM\Column(name: 'repdesc', type: 'text', nullable: true)]
    private ?string $repdesc = null;

    #[ORM\Column(name: 'repsql', type: 'text', nullable: true)]
    private ?string $repsql = null;

    #[ORM\Column(name: 'repparam', type: 'text', nullable: true)]
    private ?string $repparam = null;

    #[ORM\Column(name: 'repowner', type: 'string', length: 255, nullable: true)]
    private ?string $repowner = null;

    #[ORM\Column(name: 'repts', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $repts = null;

    // --- Getters / setters ---

    public function getRepid(): ?int
    {
        return $this->repid;
    }

    public function getReptype(): ?string
    {
        return $this->reptype;
    }

    public function setReptype(?string $reptype): self
    {
        $this->reptype = $reptype;
        return $this;
    }

    public function getRepshort(): ?string
    {
        return $this->repshort;
    }

    public function setRepshort(?string $repshort): self
    {
        $this->repshort = $repshort;
        return $this;
    }

    public function getReptitle(): ?string
    {
        return $this->reptitle;
    }

    public function setReptitle(?string $reptitle): self
    {
        $this->reptitle = $reptitle;
        return $this;
    }

    public function getRepdesc(): ?string
    {
        return $this->repdesc;
    }

    public function setRepdesc(?string $repdesc): self
    {
        $this->repdesc = $repdesc;
        return $this;
    }

    public function getRepsql(): ?string
    {
        return $this->repsql;
    }

    public function setRepsql(?string $repsql): self
    {
        $this->repsql = $repsql;
        return $this;
    }

    public function getRepparam(): ?string
    {
        return $this->repparam;
    }

    public function setRepparam(?string $repparam): self
    {
        $this->repparam = $repparam;
        return $this;
    }

    public function getRepowner(): ?string
    {
        return $this->repowner;
    }

    public function setRepowner(?string $repowner): self
    {
        $this->repowner = $repowner;
        return $this;
    }

    public function getRepts(): ?\DateTimeInterface
    {
        return $this->repts;
    }

    public function setRepts(?\DateTimeInterface $repts): self
    {
        $this->repts = $repts;
        return $this;
    }
}
