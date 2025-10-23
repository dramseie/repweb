<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'qw_attachment')]
class QwAttachment
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

    #[ORM\Column(length: 512)] private string $storagePath;
    #[ORM\Column(length: 255, nullable: true)] private ?string $originalName = null;
    #[ORM\Column(length: 127, nullable: true)] private ?string $mimeType = null;
    #[ORM\Column(type: 'bigint', nullable: true, options: ['unsigned' => true])] private ?string $sizeBytes = null;
    #[ORM\Column] private \DateTimeInterface $createdAt;

    public function __construct() { $this->createdAt = new \DateTimeImmutable(); }
}
