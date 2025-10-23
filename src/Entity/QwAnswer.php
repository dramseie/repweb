<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'qw_answer')]
class QwAnswer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'response_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private QwResponse $response;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'item_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private QwItem $item;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'field_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?QwField $field = null;

    #[ORM\Column(type: 'text', nullable: true)] private ?string $valueText = null;
    #[ORM\Column(type: 'json', nullable: true)] private ?array $valueJson = null;
    #[ORM\Column] private \DateTimeInterface $createdAt;
    #[ORM\Column] private \DateTimeInterface $updatedAt;

    public function __construct() { $this->createdAt = new \DateTimeImmutable(); $this->updatedAt = new \DateTimeImmutable(); }
}
