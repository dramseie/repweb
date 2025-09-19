<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\RestConnectorRepository;

#[ORM\Entity(repositoryClass: RestConnectorRepository::class)]
#[ORM\Table(name: 'rest_connector')]
class RestConnector
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(length: 255)]
    private string $baseUrl;

    // JSON object of default headers (e.g. {"Authorization":"Bearer ...","Accept":"application/json"})
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $defaultHeaders = [];

    // Optional notes
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $v): self { $this->name = $v; return $this; }
    public function getBaseUrl(): string { return $this->baseUrl; }
    public function setBaseUrl(string $v): self { $this->baseUrl = rtrim($v, '/'); return $this; }
    public function getDefaultHeaders(): ?array { return $this->defaultHeaders ?? []; }
    public function setDefaultHeaders(?array $v): self { $this->defaultHeaders = $v ?: []; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): self { $this->notes = $v; return $this; }
}
