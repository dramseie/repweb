<?php
// src/EventSubscriber/ReportTileJsonValidator.php
namespace App\EventSubscriber;

use App\Entity\ReportTile;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;

class ReportTileJsonValidator implements EventSubscriber
{
    public function getSubscribedEvents(): array { return [Events::prePersist, Events::preUpdate]; }

    public function prePersist(LifecycleEventArgs $args) { $this->validate($args); }
    public function preUpdate(LifecycleEventArgs $args)  { $this->validate($args); }

    private function validate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof ReportTile) return;
        $cfg = $entity->getConfig();
        if ($cfg !== null && !is_array($cfg)) {
            // If someone pasted JSON string instead of decoded array, try to decode:
            if (is_string($cfg)) {
                $decoded = json_decode($cfg, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $entity->setConfig($decoded);
                    return;
                }
            }
            throw new \InvalidArgumentException('Config must be valid JSON (object/array).');
        }
    }
}
