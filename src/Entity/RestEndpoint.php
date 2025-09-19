<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\RestEndpointRepository;

#[ORM\Entity(repositoryClass: RestEndpointRepository::class)]
#[ORM\Table(name: 'rest_endpoint')]
class RestEndpoint
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: RestConnector::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private RestConnector $connector;

    // e.g. "/v1/users/{id}"
    #[ORM\Column(length: 255)]
    private string $path;

    #[ORM\Column(length: 10)]
    private string $method = 'GET';

    // JSON object of sample query params (string->string)
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $sampleQuery = [];

    // Raw sample request body (for POST/PUT/PATCH)
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $sampleBody = null;

    // JSON array of saved JSONPath bookmarks (each: {"label": "...", "path": "$.data.items[*].id"})
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $jsonPathBookmarks = [];

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $label = null;

    public function getId(): ?int { return $this->id; }
    public function getConnector(): RestConnector { return $this->connector; }
    public function setConnector(RestConnector $c): self { $this->connector = $c; return $this; }
    public function getPath(): string { return $this->path; }
    public function setPath(string $v): self { $this->path = '/' . ltrim($v, '/'); return $this; }
    public function getMethod(): string { return strtoupper($this->method); }
    public function setMethod(string $v): self { $this->method = strtoupper($v); return $this; }
    public function getSampleQuery(): array { return $this->sampleQuery ?? []; }
    public function setSampleQuery(?array $v): self { $this->sampleQuery = $v ?: []; return $this; }
    public function getSampleBody(): ?string { return $this->sampleBody; }
    public function setSampleBody(?string $v): self { $this->sampleBody = $v; return $this; }
    public function getJsonPathBookmarks(): array { return $this->jsonPathBookmarks ?? []; }
    public function setJsonPathBookmarks(?array $v): self { $this->jsonPathBookmarks = $v ?: []; return $this; }
    public function getLabel(): ?string { return $this->label; }
    public function setLabel(?string $v): self { $this->label = $v; return $this; }
}
