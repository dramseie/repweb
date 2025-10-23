<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'qw_workflow_log')]
class QwWorkflowLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'questionnaire_id', referencedColumnName: 'id', nullable: false)]
    private QwQuestionnaire $questionnaire;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'response_id', referencedColumnName: 'id', nullable: true)]
    private ?QwResponse $response = null;

    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])] private string $actorUserId;
    #[ORM\Column(enumType: QwWorkflowAction::class)] private QwWorkflowAction $action;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $comment = null;
    #[ORM\Column] private \DateTimeInterface $createdAt;

    public function __construct() { $this->createdAt = new \DateTimeImmutable(); }
}
