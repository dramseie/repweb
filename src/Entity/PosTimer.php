<?php
// src/Entity/PosTimer.php
#[ORM\Entity]
#[ORM\Table(name: 'pos_timer')]
class PosTimer {
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'bigint')]
    private ?string $id = null;

    #[ORM\OneToOne(targetEntity: OrderItem::class)]
    #[ORM\JoinColumn(name: 'order_item_id', nullable: false, unique: true)]
    private OrderItem $orderItem;

    #[ORM\Column(type: 'string', enumType: PosTimerStatus::class, options: ['default' => 'idle'])]
    private PosTimerStatus $status = PosTimerStatus::Idle;

    #[ORM\Column(type: 'integer', options: ['unsigned' => true, 'default' => 0])]
    private int $totalSeconds = 0;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastStartedAt = null;

    // getters/setters ...
}
