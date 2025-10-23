<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'qw_questionnaire')]
#[ORM\UniqueConstraint(name: 'uq_tenant_code', columns: ['tenant_id','code'])]
class QwQuestionnaire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private string $tenantId;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'ci_id', referencedColumnName: 'id', nullable: false)]
    private QwCi $ci;

    #[ORM\Column(length: 64)] private string $code;
    #[ORM\Column(length: 255)] private string $title;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $description = null;
    #[ORM\Column(enumType: QwQuestionnaireStatus::class)] private QwQuestionnaireStatus $status;
    #[ORM\Column(type: 'bigint', nullable: true, options: ['unsigned' => true])] private ?string $ownerUserId = null;
    #[ORM\Column(type: 'bigint', nullable: true, options: ['unsigned' => true])] private ?string $approverUserId = null;
    #[ORM\Column] private int $version = 1;
    #[ORM\Column(options: ['default' => 0])] private bool $isLocked = false;
    #[ORM\Column] private \DateTimeInterface $createdAt;
    #[ORM\Column] private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $this->status = QwQuestionnaireStatus::Draft;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    // getters/setters (essential subset)
    public function getId(): ?string { return $this->id; }
    public function getTenantId(): string { return $this->tenantId; }
    public function setTenantId(string $t): void { $this->tenantId = $t; }
    public function getCode(): string { return $this->code; }
    public function setCode(string $c): void { $this->code = $c; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $t): void { $this->title = $t; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $d): void { $this->description = $d; }
    public function getStatus(): QwQuestionnaireStatus { return $this->status; }
    public function setStatus(QwQuestionnaireStatus $s): void { $this->status = $s; }
    public function setCi(QwCi $ci): void { $this->ci = $ci; }
}
