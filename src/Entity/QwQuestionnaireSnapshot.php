<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'qw_questionnaire_snapshot')]
class QwQuestionnaireSnapshot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'questionnaire_id', referencedColumnName: 'id', nullable: false)]
    private QwQuestionnaire $questionnaire;

    #[ORM\Column] private int $version;
    #[ORM\Column(type: 'text')] private string $payload;
    #[ORM\Column] private \DateTimeInterface $createdAt;

    public function __construct() { $this->createdAt = new \DateTimeImmutable(); }
}
