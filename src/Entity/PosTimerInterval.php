<?php
// src/Entity/PosTimerInterval.php
#[ORM\Entity]
#[ORM\Table(name: 'pos_timer_interval')]
class PosTimerInterval {
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'bigint')]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: PosTimer::class)]
    #[ORM\JoinColumn(name: 'timer_id', nullable: false, onDelete: 'CASCADE')]
    private PosTimer $timer;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $startedAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $endedAt = null;

    #[ORM\Column(type: 'integer', nullable: true, options: ['unsigned' => true])]
    private ?int $seconds = null;

    // getters/setters ...
}
