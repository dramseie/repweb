<?php
// src/Service/PosTimerManager.php
namespace App\Service;

use Doctrine\DBAL\Connection;

final class PosTimerManager
{
    public function __construct(private Connection $db) {}

    public function startForOrder(int $orderId): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        // Set running on items that can run
        $this->db->executeStatement(
            "UPDATE ongleri.order_items
                SET timer_status = 'running', timer_last_started_at = :now
              WHERE order_id = :oid AND timer_status IN ('idle','paused')",
            ['now' => $now, 'oid' => $orderId]
        );

        // Create intervals for each (re)started item
        $ids = $this->db->fetchFirstColumn(
            "SELECT id FROM ongleri.order_items
              WHERE order_id = :oid AND timer_status = 'running' AND timer_last_started_at = :now",
            ['oid' => $orderId, 'now' => $now]
        );
        if (!$ids) return;

        $sql = "INSERT INTO order_item_timer_intervals (order_item_id, started_at, created_at)
                VALUES (:iid, :now, :now)";
        foreach ($ids as $iid) {
            $this->db->executeStatement($sql, ['iid' => (int)$iid, 'now' => $now]);
        }
    }

    public function pauseForOrder(int $orderId): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        // Get running items with open interval
        $running = $this->db->fetchAllAssociative(
            "SELECT id, timer_last_started_at
               FROM ongleri.order_items
              WHERE order_id = :oid AND timer_status = 'running'",
            ['oid' => $orderId]
        );
        if (!$running) return;

        foreach ($running as $it) {
            $iid = (int)$it['id'];
            $interval = $this->db->fetchAssociative(
                "SELECT id, started_at
                   FROM order_item_timer_intervals
                  WHERE order_item_id = :iid AND ended_at IS NULL
                  ORDER BY id DESC LIMIT 1",
                ['iid' => $iid]
            );
            if (!$interval) continue;

            $startTs = (new \DateTimeImmutable($interval['started_at']))->getTimestamp();
            $endTs   = (new \DateTimeImmutable($now))->getTimestamp();
            $sec     = max(0, $endTs - $startTs);

            $this->db->executeStatement(
                "UPDATE order_item_timer_intervals
                    SET ended_at = :end, seconds = :sec
                  WHERE id = :id",
                ['end' => $now, 'sec' => $sec, 'id' => (int)$interval['id']]
            );

            $this->db->executeStatement(
                "UPDATE ongleri.order_items
                    SET timer_total_seconds = timer_total_seconds + :sec
                  WHERE id = :iid",
                ['sec' => $sec, 'iid' => $iid]
            );
        }

        $this->db->executeStatement(
            "UPDATE ongleri.order_items
                SET timer_status = 'paused', timer_last_started_at = NULL
              WHERE order_id = :oid AND timer_status = 'running'",
            ['oid' => $orderId]
        );
    }

    public function finishForOrder(int $orderId): void
    {
        $this->pauseForOrder($orderId);
        $this->db->executeStatement(
            "UPDATE ongleri.order_items
                SET timer_status = 'finished'
              WHERE order_id = :oid AND timer_status IN ('paused','idle')",
            ['oid' => $orderId]
        );
    }

    /** Aggregate current live total (seconds) and status */
    public function getStatusForOrder(int $orderId): array
    {
        $rows = $this->db->fetchAllAssociative(
            "SELECT timer_status, timer_total_seconds, timer_last_started_at
               FROM ongleri.order_items
              WHERE order_id = :oid",
            ['oid' => $orderId]
        );
        if (!$rows) return ['status' => 'idle', 'totalSeconds' => 0];

        $now = time();
        $total = 0; $anyRunning = false; $anyPaused = false; $anyFinished = false;

        foreach ($rows as $r) {
            $total += (int)$r['timer_total_seconds'];
            if ($r['timer_status'] === 'running' && $r['timer_last_started_at']) {
                $anyRunning = true;
                $total += max(0, $now - strtotime((string)$r['timer_last_started_at']));
            } elseif ($r['timer_status'] === 'paused') {
                $anyPaused = true;
            } elseif ($r['timer_status'] === 'finished') {
                $anyFinished = true;
            }
        }

        $status = $anyRunning ? 'running' : ($anyPaused ? 'paused' : ($anyFinished ? 'finished' : 'idle'));
        return ['status' => $status, 'totalSeconds' => $total];
    }

    /** Called before payment: pause running, return per-item + sum seconds */
    public function finalizeForOrder(int $orderId): array
    {
        $this->pauseForOrder($orderId);

        $items = $this->db->fetchAllAssociative(
            "SELECT id, name_snapshot, timer_total_seconds
               FROM ongleri.order_items
              WHERE order_id = :oid
              ORDER BY id ASC",
            ['oid' => $orderId]
        );

        $sum = 0;
        foreach ($items as &$it) {
            $it['seconds'] = (int)$it['timer_total_seconds'];
            $sum += $it['seconds'];
        }
        return ['orderSeconds' => $sum, 'items' => $items];
    }
}
