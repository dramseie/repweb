<?php
// src/Entity/PsrProject.php
namespace App\Entity\Psr;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "psr_project")]
class PsrProject {
  #[ORM\Id, ORM\GeneratedValue, ORM\Column(type:"bigint")]
  private ?string $id = null;

  #[ORM\Column(length:200)]
  private string $name;

  #[ORM\Column(type:"text", nullable:true)]
  private ?string $description = null;

  #[ORM\Column(type:"smallint", options:["unsigned"=>true])]
  private int $weatherTrend = 3; // 1..5

  #[ORM\Column(type:"smallint", options:["unsigned"=>true])]
  private int $ragOverall = 1;   // 0..3

  #[ORM\Column(type:"smallint", options:["unsigned"=>true])]
  private int $progressPct = 0;  // 0..100

  // + getters/setters
}
