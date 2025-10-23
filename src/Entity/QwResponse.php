<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'qw_response')]
class QwResponse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'questionnaire_id', referencedColumnName: 'id', nullable: false)]
    private QwQuestionnaire $questionnaire;

    #[ORM\Column(type: 'bigint', nullable: true, options: ['unsigned' => true])] private ?string $submittedByUserId = null;
    #[ORM\Column(enumType: QwResponseStatus::class)] private QwResponseStatus $status;
    #[ORM\Column] private \DateTimeInterface $startedAt;
    #[ORM\Column(nullable: true)] private ?\DateTimeInterface $submittedAt = null;
    #[ORM\Column(nullable: true)] private ?\DateTimeInterface $approvedAt = null;
    #[ORM\Column(nullable: true)] private ?\DateTimeInterface $rejectedAt = null;

    public function __construct() { $this->status = QwResponseStatus::InProgress; $this->startedAt = new \DateTimeImmutable(); }
}
