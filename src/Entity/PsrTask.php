<?php
// src/Entity/PsrTask.php
namespace App\Entity\Psr;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "psr_task")]
class PsrTask {
  #[ORM\Id, ORM\GeneratedValue, ORM\Column(type:"bigint")]
  private ?string $id = null;

  #[ORM\ManyToOne(inversedBy: "tasks")]
  #[ORM\JoinColumn(name:"project_id", referencedColumnName:"id", nullable:false, onDelete:"CASCADE")]
  private PsrProject $project;

  #[ORM\ManyToOne]
  #[ORM\JoinColumn(name:"parent_id", referencedColumnName:"id", nullable:true, onDelete:"SET NULL")]
  private ?PsrTask $parent = null;

  #[ORM\Column(length:64, nullable:true)]
  private ?string $wbsCode = null;

  #[ORM\Column(length:200)]
  private string $name;

  #[ORM\Column(type:"smallint", options:["unsigned"=>true])]
  private int $rag = 1; // 0..3

  #[ORM\Column(type:"smallint", options:["unsigned"=>true])]
  private int $progressPct = 0;

  #[ORM\Column(type:"date", nullable:true)]
  private ?\DateTimeInterface $startDate = null;

  #[ORM\Column(type:"date", nullable:true)]
  private ?\DateTimeInterface $dueDate = null;

  #[ORM\Column(type:"integer", options:["unsigned"=>true])]
  private int $sortOrder = 0;

  // + getters/setters
}
