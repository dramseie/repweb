<?php
// src/Controller/PosAccountingController.php
namespace App\Controller;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class PosAccountingController extends AbstractController
{
    #[Route('/pos/accounting', name: 'pos_accounting_page', methods: ['GET'])]
    public function page(): \Symfony\Component\HttpFoundation\Response
    {
        return $this->render('pos/accounting.html.twig');
    }

    /* ------------------------------------------------------------------
       INTERNAL: summarize orders/payments over a [start, end] (inclusive)
       Returns: ['cash_cents'=>..,'card_cents'=>..,'other_cents'=>..,'orders_total_cents'=>..]
       ------------------------------------------------------------------ */
    private function summarizePeriod(Connection $db, string $start, string $end): array
    {
        // We consider encaisse_at >= start AND < (end + 1 day)
        $sql = "
            SELECT
              COALESCE(SUM(CASE WHEN pm='cash'  THEN amt END), 0) AS cash_cents,
              COALESCE(SUM(CASE WHEN pm='card'  THEN amt END), 0) AS card_cents,
              COALESCE(SUM(CASE WHEN pm NOT IN ('cash','card') THEN amt END), 0) AS other_cents,
              COALESCE(SUM(o.total_cents), 0) AS orders_total_cents
            FROM ongleri.orders o
            LEFT JOIN (
              -- explode payments_json if present, else fallback to single payment_method
              SELECT
                oi.id,
                CASE
                  WHEN JSON_VALID(oi.payments_json) THEN JSON_UNQUOTE(JSON_EXTRACT(jj.elem, '$.method'))
                  WHEN oi.payment_method IS NOT NULL THEN oi.payment_method
                  ELSE NULL
                END AS pm_raw,
                CASE
                  WHEN JSON_VALID(oi.payments_json) THEN CAST(JSON_EXTRACT(jj.elem, '$.amount_cents') AS SIGNED)
                  WHEN oi.payment_method IS NOT NULL THEN oi.total_cents
                  ELSE 0
                END AS amt
              FROM ongleri.orders oi
              LEFT JOIN JSON_TABLE(
                CASE WHEN JSON_VALID(oi.payments_json) THEN oi.payments_json ELSE JSON_ARRAY() END,
                '$[*]' COLUMNS ( elem JSON PATH '$' )
              ) AS jj ON JSON_VALID(oi.payments_json)
            ) p ON p.id = o.id
            WHERE o.encaisse_at >= :start
              AND o.encaisse_at <  DATE_ADD(:end, INTERVAL 1 DAY)
        ";

        try {
            $row = $db->fetchAssociative($sql, ['start' => $start, 'end' => $end]) ?: [];
        } catch (\Throwable $e) {
            // Fallback: no JSON_TABLE on this server → classify by payment_method only
            $row = $db->fetchAssociative("
                SELECT
                  COALESCE(SUM(CASE WHEN LOWER(o.payment_method) IN ('cash','especes','espèces') THEN o.total_cents END), 0) AS cash_cents,
                  COALESCE(SUM(CASE WHEN LOWER(o.payment_method) IN ('card','cb','carte','carte_bleue','credit','débit') THEN o.total_cents END), 0) AS card_cents,
                  COALESCE(SUM(CASE WHEN LOWER(o.payment_method) NOT IN ('cash','especes','espèces','card','cb','carte','carte_bleue','credit','débit') THEN o.total_cents END), 0) AS other_cents,
                  COALESCE(SUM(o.total_cents), 0) AS orders_total_cents
                FROM ongleri.orders o
                WHERE o.encaisse_at >= :start
                  AND o.encaisse_at <  DATE_ADD(:end, INTERVAL 1 DAY)
            ", ['start' => $start, 'end' => $end]) ?: [];
        }

        $cash = (int)($row['cash_cents'] ?? 0);
        $card = (int)($row['card_cents'] ?? 0);
        $other = (int)($row['other_cents'] ?? 0);
        $ordersTotal = (int)($row['orders_total_cents'] ?? 0);

        return [
            'cash_cents' => $cash,
            'card_cents' => $card,
            'other_cents' => $other,
            'orders_total_cents' => $ordersTotal,
        ];
    }

    private function ensureCategoryByCode(Connection $db, string $code, string $name, string $kind, string $mainKind): array
    {
        $row = $db->fetchAssociative(
            "SELECT id, code, name, kind, main_kind
             FROM ongleri.accounting_categories
             WHERE code = :code OR name = :name
             LIMIT 1",
            ['code' => $code, 'name' => $name]
        );

        if ($row) {
            $updates = [];
            if ((string)($row['code'] ?? '') !== $code) {
                $updates['code'] = $code;
            }
            if ((string)($row['name'] ?? '') !== $name) {
                $updates['name'] = $name;
            }
            if ((string)($row['kind'] ?? '') !== $kind) {
                $updates['kind'] = $kind;
            }
            $currentMain = strtolower((string)($row['main_kind'] ?? ''));
            if ($currentMain !== strtolower($mainKind)) {
                $updates['main_kind'] = $mainKind;
            }

            if (!empty($updates)) {
                $db->update('ongleri.accounting_categories', $updates, ['id' => (int)$row['id']]);
                $row = array_merge($row, $updates);
            }

            return [
                'id' => (int)$row['id'],
                'code' => (string)($row['code'] ?? $code),
                'name' => (string)($row['name'] ?? $name),
                'kind' => (string)($row['kind'] ?? $kind),
                'main_kind' => (string)($row['main_kind'] ?? $mainKind),
            ];
        }

        try {
            $db->executeStatement(
                "INSERT INTO ongleri.accounting_categories (code, name, kind, main_kind)
                 VALUES (:code, :name, :kind, :main_kind)",
                ['code' => $code, 'name' => $name, 'kind' => $kind, 'main_kind' => $mainKind]
            );
        } catch (UniqueConstraintViolationException $e) {
            $row = $db->fetchAssociative(
                "SELECT id, code, name, kind, main_kind
                 FROM ongleri.accounting_categories
                 WHERE code = :code OR name = :name
                 LIMIT 1",
                ['code' => $code, 'name' => $name]
            );
            if (!$row) {
                throw $e;
            }

            return [
                'id' => (int)$row['id'],
                'code' => (string)($row['code'] ?? $code),
                'name' => (string)($row['name'] ?? $name),
                'kind' => (string)($row['kind'] ?? $kind),
                'main_kind' => (string)($row['main_kind'] ?? $mainKind),
            ];
        }

        $id = (int)$db->lastInsertId();

        return [
            'id' => $id,
            'code' => $code,
            'name' => $name,
            'kind' => $kind,
            'main_kind' => $mainKind,
        ];
    }

    private function upsertAutoEntry(Connection $db, string $date, int $categoryId, string $label, int $amountCents, string $marker): void
    {
        $notes = trim($marker);
        if ($notes === '') {
            throw new \InvalidArgumentException('Auto entry marker is required.');
        }

        if ($amountCents === 0) {
            $db->executeStatement(
                "DELETE FROM ongleri.accounting_entries WHERE notes = :notes",
                ['notes' => $notes]
            );
            return;
        }

        $existing = $db->fetchAssociative(
            "SELECT id FROM ongleri.accounting_entries WHERE notes = :notes LIMIT 1",
            ['notes' => $notes]
        );

        if ($existing) {
            $db->executeStatement(
                "UPDATE ongleri.accounting_entries
                    SET date = :date,
                        label = :label,
                        amount_cents = :amount_cents,
                        category_id = :category_id
                  WHERE id = :id",
                [
                    'date' => $date,
                    'label' => $label,
                    'amount_cents' => $amountCents,
                    'category_id' => $categoryId,
                    'id' => (int)$existing['id'],
                ]
            );
            return;
        }

        $db->executeStatement(
            "INSERT INTO ongleri.accounting_entries (date, label, amount_cents, category_id, notes)
             VALUES (:date, :label, :amount_cents, :category_id, :notes)",
            [
                'date' => $date,
                'label' => $label,
                'amount_cents' => $amountCents,
                'category_id' => $categoryId,
                'notes' => $notes,
            ]
        );
    }

    private function collectCustomerOrderSummary(Connection $db, string $start, string $end): array
    {
        $rows = $db->fetchAllAssociative(
            "SELECT o.id,
                    o.encaisse_at,
                    o.total_cents,
                    o.tip_cents,
                    o.payments_json,
                    o.customer_id,
                    o.note,
                    o.payment_method,
                    c.first_name,
                    c.last_name,
                    COALESCE(c.status, 'active') AS customer_status
               FROM ongleri.orders o
          LEFT JOIN ongleri.customers c ON c.id = o.customer_id
              WHERE o.encaisse_at IS NOT NULL
                AND o.encaisse_at >= :start
                AND o.encaisse_at < DATE_ADD(:end, INTERVAL 1 DAY)
                AND o.customer_id IS NOT NULL
           ORDER BY o.encaisse_at ASC, o.id ASC",
            ['start' => $start, 'end' => $end]
        );

        $incomes = [];
        $incomeTotal = 0;
        $tipTotal = 0;
        $reductionTotal = 0;

        foreach ($rows as $row) {
            $total = (int)($row['total_cents'] ?? 0);
            $incomeTotal += $total;

            $firstName = (string)($row['first_name'] ?? '');
            $lastName  = (string)($row['last_name'] ?? '');
            $fullName  = trim(sprintf('%s %s', $lastName, $firstName));

            $tip = max(0, (int)($row['tip_cents'] ?? 0));
            $tipTotal += $tip;

            $reducCents = 0;
            if (!empty($row['payments_json']) && is_string($row['payments_json'])) {
                $decoded = json_decode($row['payments_json'], true);
                if (is_array($decoded)) {
                    foreach ($decoded as $entry) {
                        if (!is_array($entry)) {
                            continue;
                        }
                        $method = strtolower((string)($entry['method'] ?? ''));
                        if ($method !== 'reduction') {
                            continue;
                        }
                        $raw = (int)($entry['amount_cents'] ?? 0);
                        $reducCents += $raw < 0 ? -$raw : $raw;
                    }
                }
            }
            $reductionTotal += $reducCents;

            $incomes[] = [
                'order_id'       => (int)$row['id'],
                'encaisse_at'    => $row['encaisse_at'],
                'total_cents'    => $total,
                'payment_method' => $row['payment_method'],
                'note'           => $row['note'],
                'tip_cents'      => $tip,
                'reduction_cents'=> $reducCents,
                'customer'       => [
                    'id'         => $row['customer_id'] !== null ? (int)$row['customer_id'] : null,
                    'first_name' => $firstName,
                    'last_name'  => $lastName,
                    'full_name'  => $fullName !== '' ? $fullName : null,
                    'status'     => (string)($row['customer_status'] ?? 'active'),
                ],
            ];
        }

        return [
            'incomes' => $incomes,
            'income_total_cents' => $incomeTotal,
            'tip_total_cents' => $tipTotal,
            'reduction_total_cents' => $reductionTotal,
        ];
    }

    private function syncMonthlyOrderEntries(Connection $db, string $ym, string $start, string $end, ?array $summary = null): array
    {
        $data = $summary ?? $this->collectCustomerOrderSummary($db, $start, $end);

        $category = $this->ensureCategoryByCode($db, 'RECETTE_CLIENT', 'Recette Client', 'bank', 'income');
        $dateForEntries = $end;

        $this->upsertAutoEntry(
            $db,
            $dateForEntries,
            $category['id'],
            sprintf('Recettes clients %s', $ym),
            (int)($data['income_total_cents'] ?? 0),
            "[AUTO_RECETTE_CLIENT:$ym]"
        );

        $this->upsertAutoEntry(
            $db,
            $dateForEntries,
            $category['id'],
            sprintf('Pourboires %s', $ym),
            (int)($data['tip_total_cents'] ?? 0),
            "[AUTO_POURBOIRES:$ym]"
        );

        $reductions = (int)($data['reduction_total_cents'] ?? 0);
        $this->upsertAutoEntry(
            $db,
            $dateForEntries,
            $category['id'],
            sprintf('Réductions clients %s', $ym),
            $reductions > 0 ? -$reductions : 0,
            "[AUTO_REDUCTIONS:$ym]"
        );

        return $data;
    }

    private function splitRecetteClientCategory(array &$byId, int $categoryId): void
    {
        if (!isset($byId[$categoryId])) {
            return;
        }

        $original = $byId[$categoryId];
        $lines = $original['lines'] ?? [];
        if (empty($lines)) {
            return;
        }

        $kind = (string)($original['kind'] ?? 'bank');
        $mainKind = (string)($original['main_kind'] ?? 'income');

        $groups = [
            'Recette Client' => ['debit' => 0, 'credit' => 0, 'lines' => []],
            'Pourboires' => ['debit' => 0, 'credit' => 0, 'lines' => []],
            'Réductions' => ['debit' => 0, 'credit' => 0, 'lines' => []],
        ];

        foreach ($lines as $line) {
            $label = (string)($line['label'] ?? '');
            $target = 'Recette Client';

            if (str_starts_with($label, 'Pourboires ')) {
                $target = 'Pourboires';
            } elseif (str_starts_with($label, 'Réductions clients ')) {
                $target = 'Réductions';
            } elseif (str_starts_with($label, 'Recettes clients ')) {
                $target = 'Recette Client';
            }

            $groups[$target]['lines'][] = $line;

            $amt = (int)($line['amount_cents'] ?? 0);
            if ($amt < 0) {
                $groups[$target]['debit'] += -$amt;
            } elseif ($amt > 0) {
                $groups[$target]['credit'] += $amt;
            }
        }

        $virtualCats = [];
        foreach ($groups as $name => $data) {
            $debit = (int)$data['debit'];
            $credit = (int)$data['credit'];
            if ($debit === 0 && $credit === 0) {
                continue;
            }

            $virtualCats[] = [
                'id' => 0, // placeholder to be replaced
                'name' => $name,
                'kind' => $kind,
                'main_kind' => $mainKind,
                'debit_cents' => $debit,
                'credit_cents' => $credit,
                'lines' => $data['lines'],
            ];
        }

        if (empty($virtualCats)) {
            return;
        }

        $virtualId = -1000;
        foreach ($virtualCats as &$virtual) {
            while (isset($byId[$virtualId]) || $virtualId === $categoryId) {
                $virtualId--;
            }
            $virtual['id'] = $virtualId;
            $virtualId--;
        }
        unset($virtual);

        $newById = [];
        foreach ($byId as $id => $cat) {
            if ($id === $categoryId) {
                foreach ($virtualCats as $virtual) {
                    $newById[$virtual['id']] = $virtual;
                }
                continue;
            }
            $newById[$id] = $cat;
        }

        $byId = $newById;
    }

    /**
     * Strip optional fond-de-caisse metadata from notes and return both the clean note and metadata.
     * Metadata format (JSON): {"amount_cents":12345,"from_category_id":1,"to_category_id":2}
     */
    private function splitFondNotes(?string $notesInput): array
    {
        $meta = [
            'amount_cents' => 0,
            'from_category_id' => null,
            'to_category_id' => null,
            'breakdown' => [],
        ];

        if (!is_string($notesInput) || trim($notesInput) === '') {
            return ['notes' => '', 'meta' => $meta];
        }

        $notes = trim($notesInput);
        if (preg_match('/FOND_CAISSE=(\{.*\})$/s', $notes, $m)) {
            $json = json_decode($m[1] ?? '', true);
            if (is_array($json)) {
                $meta['amount_cents'] = isset($json['amount_cents']) ? (int)$json['amount_cents'] : 0;
                $meta['from_category_id'] = isset($json['from_category_id']) ? (int)$json['from_category_id'] : null;
                $meta['to_category_id'] = isset($json['to_category_id']) ? (int)$json['to_category_id'] : null;
                $meta['breakdown'] = $this->sanitizeFondBreakdownMeta($json['breakdown'] ?? []);
            }
            $notes = trim(preg_replace('/(\|\|\s*)?FOND_CAISSE=\{.*\}$/s', '', $notes));
        }

        return ['notes' => $notes, 'meta' => $meta];
    }

    /**
     * Append fond metadata (if any) back to the notes string.
     */
    private function sanitizeFondBreakdownMeta($raw): array
    {
        if (!is_iterable($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $den => $qty) {
            $denInt = (int)$den;
            if ($denInt <= 0) {
                continue;
            }
            $qtyInt = max(0, (int)$qty);
            if ($qtyInt <= 0) {
                continue;
            }
            $out[(string)$denInt] = $qtyInt;
        }

        ksort($out, SORT_NUMERIC);

        return $out;
    }

    /**
     * Append fond metadata (if any) back to the notes string.
     */
    private function appendFondMeta(string $cleanNotes, int $amountCents, ?int $fromId, ?int $toId, array $breakdown = []): string
    {
        $breakdownClean = $this->sanitizeFondBreakdownMeta($breakdown);

        if (!empty($breakdownClean) && $amountCents <= 0) {
            $recomputed = 0;
            foreach ($breakdownClean as $den => $qty) {
                $recomputed += ((int)$den) * (int)$qty;
            }
            if ($recomputed > 0) {
                $amountCents = $recomputed;
            }
        }

        if ($amountCents <= 0 && empty($breakdownClean)) {
            return trim($cleanNotes);
        }

        $meta = [
            'amount_cents' => $amountCents,
            'from_category_id' => $fromId,
            'to_category_id' => $toId,
        ];

        if (!empty($breakdownClean)) {
            $meta['breakdown'] = $breakdownClean;
        }

        $payload = 'FOND_CAISSE=' . json_encode($meta, JSON_UNESCAPED_UNICODE);

        $base = trim($cleanNotes);
        if ($base === '') {
            return $payload;
        }

        return $base . ' || ' . $payload;
    }

    private function ensureCategoryExists(Connection $db, int $categoryId): array
    {
        $row = $db->fetchAssociative(
            "SELECT id, name FROM ongleri.accounting_categories WHERE id = :id",
            ['id' => $categoryId]
        );

        if (!$row) {
            throw new \InvalidArgumentException('Unknown accounting category id ' . $categoryId);
        }

        return [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
        ];
    }

    private function insertFondTransfer(Connection $db, string $date, int $amountCents, array $fromCat, array $toCat, string $direction): void
    {
        if ($amountCents <= 0) {
            return;
        }

        $labelBase = sprintf('Fond de caisse %s', $direction);
        $noteMarker = sprintf('[FOND_CAISSE_AUTO:%s]', $date);

        $db->executeStatement(
            "INSERT INTO ongleri.accounting_entries (date, label, amount_cents, category_id, notes)
             VALUES (:date, :label, :amount_cents, :category_id, :notes)",
            [
                'date' => $date,
                'label' => $labelBase . ' (' . $fromCat['name'] . ' → ' . $toCat['name'] . ')',
                'amount_cents' => -$amountCents,
                'category_id' => $fromCat['id'],
                'notes' => $noteMarker,
            ]
        );

        $db->executeStatement(
            "INSERT INTO ongleri.accounting_entries (date, label, amount_cents, category_id, notes)
             VALUES (:date, :label, :amount_cents, :category_id, :notes)",
            [
                'date' => $date,
                'label' => $labelBase . ' (contrepartie)',
                'amount_cents' => $amountCents,
                'category_id' => $toCat['id'],
                'notes' => $noteMarker,
            ]
        );
    }

    private function adjustFondCaisseTransfers(
        Connection $db,
        string $date,
        int $newAmount,
        ?int $newFromId,
        ?int $newToId,
        array $previousMeta
    ): void {
        $oldAmount = (int)($previousMeta['amount_cents'] ?? 0);
        $oldFromId = $previousMeta['from_category_id'] ?? null;
        $oldToId = $previousMeta['to_category_id'] ?? null;

        // Nothing to do if both old and new are zero.
        if ($oldAmount === 0 && $newAmount === 0) {
            return;
        }

        // Validate new categories if we have a new amount.
        if ($newAmount > 0) {
            if (!$newFromId || !$newToId || $newFromId === $newToId) {
                throw new \InvalidArgumentException('Fond de caisse requires distinct source and destination categories.');
            }
        }

        // If we need to reverse previous fond (categories changed or amount set to zero), do it first.
        if ($oldAmount > 0 && ($newAmount === 0 || $oldFromId !== $newFromId || $oldToId !== $newToId)) {
            if (!$oldFromId || !$oldToId) {
                throw new \RuntimeException('Impossible de réajuster le fond de caisse : catégories précédentes introuvables.');
            }

            $fromCat = $this->ensureCategoryExists($db, $oldToId);
            $toCat = $this->ensureCategoryExists($db, $oldFromId);
            $this->insertFondTransfer($db, $date, $oldAmount, $fromCat, $toCat, '-');

            $oldAmount = 0;
            $oldFromId = $newFromId;
            $oldToId = $newToId;
        }

        $delta = $newAmount - $oldAmount;

        if ($delta > 0) {
            $fromCat = $this->ensureCategoryExists($db, (int)$newFromId);
            $toCat = $this->ensureCategoryExists($db, (int)$newToId);
            $this->insertFondTransfer($db, $date, $delta, $fromCat, $toCat, '+');
        } elseif ($delta < 0) {
            if (!$oldToId || !$oldFromId) {
                throw new \RuntimeException('Impossible de diminuer le fond de caisse : catégories précédentes manquantes.');
            }
            $amount = abs($delta);
            $fromCat = $this->ensureCategoryExists($db, (int)$oldToId);
            $toCat = $this->ensureCategoryExists($db, (int)$oldFromId);
            $this->insertFondTransfer($db, $date, $amount, $fromCat, $toCat, '-');
        }
    }

    /* ---------------------------------------------------------
       MONTH SUMMARY
       GET /api/pos/accounting/month?ym=YYYY-MM
       --------------------------------------------------------- */
    #[Route('/api/pos/accounting/month', name: 'api_pos_accounting_month', methods: ['GET'])]
    public function month(Request $req, Connection $db): JsonResponse
    {
        $ym = (string)$req->query->get('ym', '');
        if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
            return $this->json(['error' => 'Invalid ym (YYYY-MM)'], 400);
        }
        [$Y, $M] = array_map('intval', explode('-', $ym));
        $start = sprintf('%04d-%02d-01', $Y, $M);
        $end   = (new \DateTimeImmutable("$start 00:00:00"))->modify('last day of this month')->format('Y-m-d');

        $totals = $this->summarizePeriod($db, $start, $end);

        $last = $db->fetchAssociative("
            SELECT id, created_at, total_cents, expected_cash_cents, diff_cents
            FROM ongleri.cash_counts
            WHERE count_date = :eom
            ORDER BY id DESC
            LIMIT 1
        ", ['eom' => $end]) ?: null;

        $lastData = null;
        if ($last) {
            $split = $this->splitFondNotes($last['notes'] ?? '');
            $lastData = [
                'id' => (int)$last['id'],
                'created_at' => $last['created_at'],
                'total_cents' => (int)$last['total_cents'],
                'expected_cash_cents' => (int)$last['expected_cash_cents'],
                'diff_cents' => (int)$last['diff_cents'],
                'notes' => $split['notes'],
                'fond_caisse' => $split['meta'],
            ];
        }

        return $this->json([
            'ok' => true,
            'period' => ['start' => $start, 'end' => $end],
            'totals' => $totals,
            'last_cash_count' => $lastData,
        ]);
    }

    /* ---------------------------------------------------------
       READ a specific cash-count by exact date
       GET /api/pos/accounting/cash-count?date=YYYY-MM-DD
       --------------------------------------------------------- */
    #[Route('/api/pos/accounting/cash-count', name: 'api_pos_accounting_cash_count_get', methods: ['GET'])]
    public function getCount(Request $req, Connection $db): JsonResponse
    {
        $date = (string)$req->query->get('date', '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $this->json(['error' => 'Invalid date (YYYY-MM-DD)'], 400);
        }

        $row = $db->fetchAssociative("
            SELECT id, count_date, total_cents, expected_cash_cents, diff_cents,
                   breakdown_json, notes, created_at
            FROM ongleri.cash_counts
            WHERE count_date = :d
            ORDER BY id DESC
            LIMIT 1
        ", ['d' => $date]);

        if (!$row) return $this->json(['ok' => true, 'item' => null]);

        $split = $this->splitFondNotes($row['notes'] ?? '');

        return $this->json([
            'ok' => true,
            'item' => [
                'id' => (int)$row['id'],
                'count_date' => $row['count_date'],
                'total_cents' => (int)$row['total_cents'],
                'expected_cash_cents' => (int)$row['expected_cash_cents'],
                'diff_cents' => (int)$row['diff_cents'],
                'breakdown' => json_decode($row['breakdown_json'] ?? '[]', true) ?: [],
                'notes' => $split['notes'],
                'fond_caisse' => $split['meta'],
                'created_at' => $row['created_at'],
            ]
        ]);
    }

    /* ---------------------------------------------------------
       SAVE / UPSERT CASH COUNT  (supports scope="month")
       POST /api/pos/accounting/cash-count
       --------------------------------------------------------- */
    #[Route('/api/pos/accounting/cash-count', name: 'api_pos_accounting_cash_count', methods: ['POST'])]
    public function saveCount(Request $req, Connection $db): JsonResponse
    {
        $p = json_decode($req->getContent() ?: '{}', true);

    $scope = (string)($p['scope'] ?? 'day');
    $date  = (string)($p['date']  ?? (new DateTimeImmutable('today'))->format('Y-m-d'));
    $breakdownInput = $p['breakdown'] ?? [];
        $rawNotes = is_string($p['notes'] ?? null) ? trim((string)$p['notes']) : '';

        $fondAmountCents = (int)($p['fond_caisse_cents'] ?? $p['float_cents'] ?? 0);
        $fondAmountCents = max(0, $fondAmountCents);
        $fondFromId = isset($p['fond_from_category_id']) ? (int)$p['fond_from_category_id'] : (isset($p['float_from_category_id']) ? (int)$p['float_from_category_id'] : null);
        $fondToId = isset($p['fond_to_category_id']) ? (int)$p['fond_to_category_id'] : (isset($p['float_to_category_id']) ? (int)$p['float_to_category_id'] : null);

        $counted = 0;
        $breakdown = [];
        if (!is_iterable($breakdownInput)) {
            return $this->json(['error' => 'Invalid breakdown'], 400);
        }
        foreach ($breakdownInput as $denStr => $qty) {
            $den = (int)$denStr;
            if ($den <= 0) {
                continue;
            }
            $q   = max(0, (int)$qty);
            $breakdown[(string)$den] = $q;
            $counted += $den * $q;
        }
        ksort($breakdown, SORT_NUMERIC);

        $fondBreakdown = $this->sanitizeFondBreakdownMeta($p['fond_breakdown'] ?? []);
        if (!empty($fondBreakdown)) {
            foreach ($fondBreakdown as $denKey => $qty) {
                $available = (int)($breakdown[$denKey] ?? 0);
                if ($qty > $available) {
                    $fondBreakdown[$denKey] = $available;
                }
                if ($fondBreakdown[$denKey] <= 0) {
                    unset($fondBreakdown[$denKey]);
                }
            }
            ksort($fondBreakdown, SORT_NUMERIC);
        }

        $fondBreakdownAmount = 0;
        foreach ($fondBreakdown as $denKey => $qty) {
            $fondBreakdownAmount += ((int)$denKey) * (int)$qty;
        }
        if ($fondBreakdownAmount > 0) {
            $fondAmountCents = min($fondBreakdownAmount, $counted);
        }

        if ($fondAmountCents > 0 && (!$fondFromId || !$fondToId || $fondFromId === $fondToId)) {
            return $this->json([
                'error' => 'Veuillez sélectionner des catégories distinctes pour le fond de caisse.',
            ], 400);
        }

        if ($scope === 'month') {
            $start = (string)($p['period_start'] ?? '');
            $end   = (string)($p['period_end']   ?? $date);
            if (!$start || !$end) {
                return $this->json(['error' => 'period_start / period_end required for scope=month'], 400);
            }
        } else {
            $start = $date;
            $end   = $date;
        }

        $totals = $this->summarizePeriod($db, $start, $end);
        $expected = (int)($totals['cash_cents'] ?? 0);
        $diff = $counted - $expected;

        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $userId = $this->getUser()?->getId();

        $existing = $db->fetchAssociative(
            "SELECT notes FROM ongleri.cash_counts WHERE count_date = :d",
            ['d' => $date]
        );
        $previousMeta = $existing ? ($this->splitFondNotes($existing['notes'] ?? '')['meta'] ?? []) : [
            'amount_cents' => 0,
            'from_category_id' => null,
            'to_category_id' => null,
            'breakdown' => [],
        ];

    $notesFinal = $this->appendFondMeta($rawNotes, $fondAmountCents, $fondFromId, $fondToId, $fondBreakdown);

        $sql = "
            INSERT INTO ongleri.cash_counts
                (user_id, count_date, total_cents, expected_cash_cents, diff_cents, breakdown_json, notes, created_at)
            VALUES
                (:user_id, :count_date, :total_cents, :expected_cash_cents, :diff_cents, :breakdown_json, :notes, :created_at)
            ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                total_cents = VALUES(total_cents),
                expected_cash_cents = VALUES(expected_cash_cents),
                diff_cents = VALUES(diff_cents),
                breakdown_json = VALUES(breakdown_json),
                notes = VALUES(notes),
                created_at = VALUES(created_at)
        ";

        $db->beginTransaction();
        try {
            $db->executeStatement($sql, [
                'user_id' => $userId,
                'count_date' => $date,
                'total_cents' => $counted,
                'expected_cash_cents' => $expected,
                'diff_cents' => $diff,
                'breakdown_json' => json_encode($breakdown, JSON_UNESCAPED_UNICODE),
                'notes' => $notesFinal,
                'created_at' => $now,
            ]);

            if ($scope === 'month') {
                $this->adjustFondCaisseTransfers($db, $end, $fondAmountCents, $fondFromId, $fondToId, $previousMeta);
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            return $this->json([
                'error' => 'Echec enregistrement comptage',
                'detail' => $e->getMessage(),
            ], 500);
        }

        if ($scope === 'month') {
            $ym = substr($start, 0, 7);
            $req2 = new Request(['ym' => $ym]);
            return $this->month($req2, $db);
        }

        $req2 = new Request(['date' => $date]);
        return $this->day($req2, $db);
    }

    /* ---------------------------------------------------------
       (LEGACY) DAY SUMMARY — kept for compatibility.
       --------------------------------------------------------- */
    #[Route('/api/pos/accounting/day', name: 'api_pos_accounting_day', methods: ['GET'])]
    public function day(Request $req, Connection $db): JsonResponse
    {
        $date = $req->query->get('date', (new DateTimeImmutable('today'))->format('Y-m-d'));
        $totals = $this->summarizePeriod($db, $date, $date);

        $last = $db->fetchAssociative("
            SELECT id, count_date, total_cents, expected_cash_cents, diff_cents, notes, breakdown_json, created_at
            FROM ongleri.cash_counts
            WHERE count_date = :d
            ORDER BY id DESC
            LIMIT 1
        ", ['d' => $date]) ?: null;

        $lastData = null;
        if ($last) {
            $split = $this->splitFondNotes($last['notes'] ?? '');
            $lastData = [
                'id' => (int)$last['id'],
                'created_at' => $last['created_at'],
                'total_cents' => (int)$last['total_cents'],
                'expected_cash_cents' => (int)$last['expected_cash_cents'],
                'diff_cents' => (int)$last['diff_cents'],
                'notes' => $split['notes'],
                'fond_caisse' => $split['meta'],
                'breakdown' => json_decode($last['breakdown_json'] ?? '[]', true) ?: [],
            ];
        }

        return $this->json([
            'date' => $date,
            'totals' => $totals,
            'last_cash_count' => $lastData,
        ]);
    }

    /* ======================================================================
       ============  NEW:  COMPTABILITÉ (Banque & Dépenses) API  =============
       ====================================================================== */

    /** List categories (editable in DB)
     * GET /api/accounting/categories
     * Returns: [{id, code, name, kind}]
     */
    #[Route('/api/accounting/categories', name: 'api_accounting_categories', methods: ['GET'])]
    public function categories(Connection $db): JsonResponse
    {
        $rows = $db->fetchAllAssociative("
            SELECT id, code, name, kind, main_kind
            FROM ongleri.accounting_categories
            ORDER BY main_kind, name
        ");

        $items = array_map(function ($r) {
            $mainKind = strtolower((string)($r['main_kind'] ?? ''));
            if ($mainKind === '') {
                $mainKind = 'asset';
            }

            return [
                'id' => (int)$r['id'],
                'code' => (string)$r['code'],
                'name' => (string)$r['name'],
                'kind' => (string)$r['kind'],
                'main_kind' => $mainKind,
            ];
        }, $rows ?? []);

        return $this->json($items);
    }

    #[Route('/api/accounting/categories', name: 'api_accounting_categories_create', methods: ['POST'])]
    public function createCategory(Request $req, Connection $db): JsonResponse
    {
        $payload = json_decode($req->getContent() ?: '{}', true);
        if (!is_array($payload)) {
            return $this->json(['error' => 'Corps JSON invalide.'], 400);
        }

        $code = trim((string)($payload['code'] ?? ''));
        $name = trim((string)($payload['name'] ?? ''));
        $kind = strtolower(trim((string)($payload['kind'] ?? '')));
        $mainKind = strtolower(trim((string)($payload['main_kind'] ?? '')));

        if ($code === '' || $name === '') {
            return $this->json(['error' => 'Code et nom sont requis.'], 400);
        }
        if (strlen($code) > 64) {
            return $this->json(['error' => 'Code trop long (64 max).'], 400);
        }
        if (strlen($name) > 128) {
            return $this->json(['error' => 'Nom trop long (128 max).'], 400);
        }
        if (!in_array($kind, ['bank', 'expense'], true)) {
            return $this->json(['error' => 'Type invalide (bank ou expense).'], 400);
        }

        if ($mainKind === '') {
            $mainKind = $kind === 'expense' ? 'expense' : 'asset';
        }
        if (!in_array($mainKind, ['asset', 'liability', 'income', 'expense'], true)) {
            return $this->json(['error' => 'Catégorie principale invalide.'], 400);
        }

        try {
            $db->executeStatement(
                "INSERT INTO ongleri.accounting_categories (code, name, kind, main_kind)
                 VALUES (:code, :name, :kind, :main_kind)",
                ['code' => $code, 'name' => $name, 'kind' => $kind, 'main_kind' => $mainKind]
            );
        } catch (UniqueConstraintViolationException $e) {
            return $this->json(['error' => 'Ce code est déjà utilisé.'], 409);
        }

        $id = (int)$db->lastInsertId();

        return $this->json([
            'ok' => true,
            'item' => [
                'id'   => $id,
                'code' => $code,
                'name' => $name,
                'kind' => $kind,
                'main_kind' => $mainKind,
            ],
        ], 201);
    }

    #[Route('/api/accounting/categories/{id<\d+>}', name: 'api_accounting_categories_update', methods: ['PATCH'])]
    public function updateCategory(int $id, Request $req, Connection $db): JsonResponse
    {
        if ($id <= 0) {
            return $this->json(['error' => 'Identifiant invalide.'], 400);
        }

        $exists = $db->fetchAssociative(
            "SELECT id, code, name, kind, main_kind FROM ongleri.accounting_categories WHERE id = :id",
            ['id' => $id]
        );
        if (!$exists) {
            return $this->json(['error' => 'Catégorie introuvable.'], 404);
        }

        $payload = json_decode($req->getContent() ?: '{}', true);
        if (!is_array($payload)) {
            return $this->json(['error' => 'Corps JSON invalide.'], 400);
        }

        $updates = [];

        if (array_key_exists('code', $payload)) {
            $code = trim((string)$payload['code']);
            if ($code === '') {
                return $this->json(['error' => 'Code requis.'], 400);
            }
            if (strlen($code) > 64) {
                return $this->json(['error' => 'Code trop long (64 max).'], 400);
            }
            $updates['code'] = $code;
        }

        if (array_key_exists('name', $payload)) {
            $name = trim((string)$payload['name']);
            if ($name === '') {
                return $this->json(['error' => 'Nom requis.'], 400);
            }
            if (strlen($name) > 128) {
                return $this->json(['error' => 'Nom trop long (128 max).'], 400);
            }
            $updates['name'] = $name;
        }

        if (array_key_exists('kind', $payload)) {
            $kind = strtolower(trim((string)$payload['kind'] ?? ''));
            if (!in_array($kind, ['bank', 'expense'], true)) {
                return $this->json(['error' => 'Type invalide (bank ou expense).'], 400);
            }
            $updates['kind'] = $kind;
        }

        if (array_key_exists('main_kind', $payload)) {
            $mainKind = strtolower(trim((string)$payload['main_kind'] ?? ''));
            $effectiveKind = $updates['kind'] ?? $exists['kind'];
            if ($mainKind === '') {
                $mainKind = $effectiveKind === 'expense' ? 'expense' : 'asset';
            }
            if (!in_array($mainKind, ['asset', 'liability', 'income', 'expense'], true)) {
                return $this->json(['error' => 'Catégorie principale invalide.'], 400);
            }
            $updates['main_kind'] = $mainKind;
        }

        if (!$updates) {
            return $this->json([
                'ok' => true,
                'item' => [
                    'id'   => (int)$exists['id'],
                    'code' => (string)$exists['code'],
                    'name' => (string)$exists['name'],
                    'kind' => (string)$exists['kind'],
                ],
            ]);
        }

        try {
            $db->update('ongleri.accounting_categories', $updates, ['id' => $id]);
        } catch (UniqueConstraintViolationException $e) {
            return $this->json(['error' => 'Ce code est déjà utilisé.'], 409);
        }

        $row = $db->fetchAssociative(
            "SELECT id, code, name, kind, main_kind FROM ongleri.accounting_categories WHERE id = :id",
            ['id' => $id]
        );

        $mainKind = strtolower((string)($row['main_kind'] ?? ''));
        if ($mainKind === '') {
            $mainKind = 'asset';
        }

        return $this->json([
            'ok' => true,
            'item' => [
                'id' => (int)$row['id'],
                'code' => (string)$row['code'],
                'name' => (string)$row['name'],
                'kind' => (string)$row['kind'],
                'main_kind' => $mainKind,
            ],
        ]);
    }

    #[Route('/api/accounting/categories/{id<\d+>}', name: 'api_accounting_categories_delete', methods: ['DELETE'])]
    public function deleteCategory(int $id, Connection $db): JsonResponse
    {
        if ($id <= 0) {
            return $this->json(['error' => 'Identifiant invalide.'], 400);
        }

        $exists = $db->fetchOne(
            "SELECT id FROM ongleri.accounting_categories WHERE id = :id",
            ['id' => $id]
        );
        if (!$exists) {
            return $this->json(['error' => 'Catégorie introuvable.'], 404);
        }

        try {
            $deleted = $db->executeStatement(
                "DELETE FROM ongleri.accounting_categories WHERE id = :id",
                ['id' => $id]
            );
        } catch (ForeignKeyConstraintViolationException $e) {
            return $this->json(['error' => 'Impossible de supprimer cette catégorie car elle est utilisée.'], 409);
        }

        return $this->json(['ok' => true, 'deleted' => (int)$deleted]);
    }

    /** List entries for a given month
     * GET /api/accounting/entries?ym=YYYY-MM
     * Returns: [{id,date,label,amount_cents,category_id,notes}]
     */
    #[Route('/api/accounting/entries', name: 'api_accounting_entries_list', methods: ['GET'])]
    public function entries(Request $req, Connection $db): JsonResponse
    {
        $ym = (string)$req->query->get('ym', '');
        if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
            return $this->json(['error' => 'Invalid ym (YYYY-MM)'], 400);
        }

        // Filter by month via BETWEEN
        [$Y, $M] = array_map('intval', explode('-', $m = $ym));
        $start = sprintf('%04d-%02d-01', $Y, $M);
        $end   = (new \DateTimeImmutable("$start 00:00:00"))->modify('last day of this month')->format('Y-m-d');

        $rows = $db->fetchAllAssociative("
            SELECT id, date, label, amount_cents, category_id, notes
            FROM ongleri.accounting_entries
            WHERE date >= :start AND date <= :end
            ORDER BY date ASC, id ASC
        ", ['start' => $start, 'end' => $end]);

        $items = array_map(fn($r) => [
            'id'           => (int)$r['id'],
            'date'         => (string)$r['date'],
            'label'        => (string)$r['label'],
            'amount_cents' => (int)$r['amount_cents'],
            'category_id'  => (int)$r['category_id'],
            'notes'        => $r['notes'],
        ], $rows ?? []);

        return $this->json($items);
    }

    /** Create an entry
     * POST /api/accounting/entries
     * Body: { date: 'YYYY-MM-DD', label: string, amount_cents?: int, amount?: '12,34', category_id: int, notes?: string }
     */
    #[Route('/api/accounting/entries', name: 'api_accounting_entries_create', methods: ['POST'])]
    public function createEntry(Request $req, Connection $db): JsonResponse
    {
        $p = json_decode($req->getContent() ?: '{}', true);

        $date = (string)($p['date'] ?? '');
        $label = trim((string)($p['label'] ?? ''));
        $notes = $p['notes'] ?? null;
        $categoryId = (int)($p['category_id'] ?? 0);

        // amount: accept amount_cents or "amount" in €
        if (isset($p['amount_cents'])) {
            $amountCents = (int)$p['amount_cents'];
        } else {
            $amountStr = str_replace(',', '.', (string)($p['amount'] ?? '0'));
            $amountCents = (int)round(((float)$amountStr) * 100);
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $this->json(['error' => 'Invalid date (YYYY-MM-DD)'], 400);
        }
        if ($label === '' || $categoryId <= 0 || $amountCents === 0) {
            return $this->json(['error' => 'label, category_id and non-zero amount required'], 400);
        }

        // Validate category exists
        $cat = $db->fetchAssociative("
            SELECT id FROM ongleri.accounting_categories WHERE id = :id
        ", ['id' => $categoryId]);
        if (!$cat) return $this->json(['error' => 'Unknown category_id'], 400);

        $db->executeStatement("
            INSERT INTO ongleri.accounting_entries (date, label, amount_cents, category_id, notes)
            VALUES (:date, :label, :amount_cents, :category_id, :notes)
        ", [
            'date' => $date,
            'label' => $label,
            'amount_cents' => $amountCents,
            'category_id' => $categoryId,
            'notes' => $notes,
        ]);

        $id = (int)$db->lastInsertId();

        return $this->json([
            'ok' => true,
            'id' => $id,
            'item' => [
                'id' => $id,
                'date' => $date,
                'label' => $label,
                'amount_cents' => $amountCents,
                'category_id' => $categoryId,
                'notes' => $notes,
            ],
        ], 201);
    }

    /** Delete an entry
     * DELETE /api/accounting/entries/{id}
     */
    #[Route('/api/accounting/entries/{id}', name: 'api_accounting_entries_delete', methods: ['DELETE'])]
    public function deleteEntry(int $id, Connection $db): JsonResponse
    {
        if ($id <= 0) return $this->json(['error' => 'Invalid id'], 400);

        $affected = $db->executeStatement("
            DELETE FROM ongleri.accounting_entries WHERE id = :id
        ", ['id' => $id]);

        return $this->json(['ok' => true, 'deleted' => (int)$affected]);
        }
		
		
    /** ------------------------------------------------------------------
     * CREATE a transfer (double-entry)
     * POST /api/accounting/transfer
     * Body JSON:
     * {
     *   "date": "YYYY-MM-DD",
     *   "label": "Virement Caisse -> Banque",
     *   "from_category_id": 1,          // e.g. Caisse
     *   "to_category_id": 2,            // e.g. Banque or URSSAF
     *   "amount_cents": 9500,           // OR "amount": "95,00"
     *   "notes": "optional"
     * }
     * Behavior:
     *   - inserts two rows in ongleri.accounting_entries:
     *       from: amount_cents = -A
     *       to:   amount_cents = +A
     * ------------------------------------------------------------------ */
    #[Route('/api/accounting/transfer', name: 'api_accounting_transfer', methods: ['POST'])]
    public function createTransfer(Request $req, Connection $db): JsonResponse
    {
        $p = json_decode($req->getContent() ?: '{}', true);

        $date = (string)($p['date'] ?? '');
        $label = trim((string)($p['label'] ?? 'Virement'));
        $fromId = (int)($p['from_category_id'] ?? 0);
        $toId   = (int)($p['to_category_id'] ?? 0);
        $notes  = $p['notes'] ?? null;

        if (isset($p['amount_cents'])) {
            $amountCents = (int)$p['amount_cents'];
        } else {
            $amountStr = str_replace(',', '.', (string)($p['amount'] ?? '0'));
            $amountCents = (int)round(((float)$amountStr) * 100);
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $this->json(['error' => 'Invalid date (YYYY-MM-DD)'], 400);
        }
        if ($fromId <= 0 || $toId <= 0 || $fromId === $toId) {
            return $this->json(['error' => 'from_category_id and to_category_id must be different and > 0'], 400);
        }
        if ($amountCents <= 0) {
            return $this->json(['error' => 'amount must be > 0'], 400);
        }

        // Validate categories exist
        $from = $db->fetchAssociative("SELECT id, name FROM ongleri.accounting_categories WHERE id=:id", ['id' => $fromId]);
        $to   = $db->fetchAssociative("SELECT id, name FROM ongleri.accounting_categories WHERE id=:id", ['id' => $toId]);
        if (!$from || !$to) return $this->json(['error' => 'Unknown category id(s)'], 400);

        // Use a soft link key to relate both lines (no schema change)
        $linkKey = bin2hex(random_bytes(8)); // e.g. "a1b2c3d4e5f6g7h8"
        $labelFrom = $label !== '' ? $label : sprintf('Virement -> %s', $to['name']);
        $labelTo   = $label !== '' ? $label : sprintf('Virement <- %s', $from['name']);
        $notesFrom = trim('[xfer:' . $linkKey . '] ' . (string)$notes);
        $notesTo   = $notesFrom;

        $db->beginTransaction();
        try {
            // FROM (debit / outflow)
            $db->executeStatement("
                INSERT INTO ongleri.accounting_entries (date, label, amount_cents, category_id, notes)
                VALUES (:date, :label, :amount_cents, :category_id, :notes)
            ", [
                'date' => $date,
                'label' => $labelFrom,
                'amount_cents' => -$amountCents,  // negative on source
                'category_id' => $fromId,
                'notes' => $notesFrom,
            ]);

            // TO (credit / inflow)
            $db->executeStatement("
                INSERT INTO ongleri.accounting_entries (date, label, amount_cents, category_id, notes)
                VALUES (:date, :label, :amount_cents, :category_id, :notes)
            ", [
                'date' => $date,
                'label' => $labelTo,
                'amount_cents' => $amountCents,   // positive on destination
                'category_id' => $toId,
                'notes' => $notesTo,
            ]);

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            return $this->json(['error' => 'transfer failed', 'details' => $e->getMessage()], 500);
        }

        return $this->json(['ok' => true, 'link_key' => $linkKey], 201);
    }

    /** ------------------------------------------------------------------
     * LEDGER view per month (for left/right columns in UI)
     * GET /api/accounting/ledger?ym=YYYY-MM
     * Returns:
     *  {
     *    ym: "YYYY-MM",
     *    categories: [
     *      { id, name, kind, debit_cents, credit_cents,
     *        lines: [{id,date,label,amount_cents,side:'debit'|'credit'}] }
     *    ],
     *    totals: { debit_cents, credit_cents }
     *  }
     *  - side is computed from sign: amount_cents<0 => 'debit', >0 => 'credit'
     * ------------------------------------------------------------------ */
    #[Route('/api/accounting/ledger', name: 'api_accounting_ledger', methods: ['GET'])]
    public function ledger(Request $req, Connection $db): JsonResponse
    {
        $ym = (string)$req->query->get('ym', '');
        if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
            return $this->json(['error' => 'Invalid ym (YYYY-MM)'], 400);
        }

        // Month bounds
        [$Y, $M] = array_map('intval', explode('-', $ym));
        $start = sprintf('%04d-%02d-01', $Y, $M);
        $end   = (new \DateTimeImmutable("$start 00:00:00"))->modify('last day of this month')->format('Y-m-d');

        $this->syncMonthlyOrderEntries($db, $ym, $start, $end);

        // Load all categories
        $cats = $db->fetchAllAssociative("
            SELECT id, name, kind, main_kind
            FROM ongleri.accounting_categories
            ORDER BY main_kind, name
        ");
        $byId = [];
        foreach ($cats as $c) {
            $mainKind = strtolower((string)($c['main_kind'] ?? ''));
            if ($mainKind === '') {
                $mainKind = 'asset';
            }

            $byId[(int)$c['id']] = [
                'id' => (int)$c['id'],
                'name' => (string)$c['name'],
                'kind' => (string)$c['kind'],
                'main_kind' => $mainKind,
                'debit_cents' => 0,
                'credit_cents' => 0,
                'lines' => [],
            ];
        }

        // Lines in period
        $rows = $db->fetchAllAssociative("
            SELECT id, date, label, amount_cents, category_id
            FROM ongleri.accounting_entries
            WHERE date >= :start AND date <= :end
            ORDER BY date ASC, id ASC
        ", ['start' => $start, 'end' => $end]);

        $totDebit = 0; $totCredit = 0;

        foreach ($rows as $r) {
            $cid = (int)$r['category_id'];
            if (!isset($byId[$cid])) continue;

            $amt = (int)$r['amount_cents'];
            $side = $amt < 0 ? 'debit' : 'credit';

            if ($amt < 0) {
                $byId[$cid]['debit_cents'] += -$amt; // store absolute for totals
                $totDebit += -$amt;
            } elseif ($amt > 0) {
                $byId[$cid]['credit_cents'] += $amt;
                $totCredit += $amt;
            }

            $byId[$cid]['lines'][] = [
                'id' => (int)$r['id'],
                'date' => (string)$r['date'],
                'label' => (string)$r['label'],
                'amount_cents' => $amt,
                'side' => $side,
            ];
        }

        // Carry forward balances for Actif/Passif categories only.
        $carryRows = $db->fetchAllAssociative(
            "SELECT category_id, COALESCE(SUM(amount_cents), 0) AS balance
               FROM ongleri.accounting_entries
              WHERE date < :start
           GROUP BY category_id",
            ['start' => $start]
        );

        $carryLabel = sprintf('Solde antérieur au %s', $start);
        $carryDate = (new \DateTimeImmutable($start))->format('Y-m-d');
        $virtualId = -5000;

        foreach ($carryRows as $carry) {
            $cid = (int)($carry['category_id'] ?? 0);
            if (!isset($byId[$cid])) {
                continue;
            }

            $mainKind = strtolower((string)($byId[$cid]['main_kind'] ?? ''));
            if (!in_array($mainKind, ['asset', 'liability'], true)) {
                continue; // produce carry only for Actif/Passif
            }

            $amt = (int)($carry['balance'] ?? 0);
            if ($amt === 0) {
                continue;
            }

            if ($amt < 0) {
                $byId[$cid]['debit_cents'] += -$amt;
                $totDebit += -$amt;
            } else {
                $byId[$cid]['credit_cents'] += $amt;
                $totCredit += $amt;
            }

            $side = $amt < 0 ? 'debit' : 'credit';
            array_unshift($byId[$cid]['lines'], [
                'id' => $virtualId,
                'date' => $carryDate,
                'label' => $carryLabel,
                'amount_cents' => $amt,
                'side' => $side,
            ]);

            $virtualId--;
        }

        foreach ($byId as $catId => $catData) {
            if (strcasecmp((string)($catData['name'] ?? ''), 'Recette Client') === 0) {
                $this->splitRecetteClientCategory($byId, (int)$catId);
                break;
            }
        }

        return $this->json([
            'ym' => $ym,
            'categories' => array_values($byId),
            'totals' => ['debit_cents' => $totDebit, 'credit_cents' => $totCredit],
        ]);
    }

    /** ------------------------------------------------------------------
     * Monthly income vs expense breakdown used by the “Rapport” tab.
     * GET /api/pos/accounting/report?ym=YYYY-MM
     * ------------------------------------------------------------------ */
    #[Route('/api/pos/accounting/report', name: 'api_pos_accounting_report', methods: ['GET'])]
    public function report(Request $req, Connection $db): JsonResponse
    {
        $ym = (string)$req->query->get('ym', '');
        if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
            return $this->json(['error' => 'Invalid ym (YYYY-MM)'], 400);
        }

        [$Y, $M] = array_map('intval', explode('-', $ym));
        $start = sprintf('%04d-%02d-01', $Y, $M);
        $end   = (new \DateTimeImmutable("$start 00:00:00"))->modify('last day of this month')->format('Y-m-d');

        $orderSummary = $this->syncMonthlyOrderEntries($db, $ym, $start, $end);
        $incomes = $orderSummary['incomes'] ?? [];
        $incomeTotal = (int)($orderSummary['income_total_cents'] ?? 0);
        $tipTotal = (int)($orderSummary['tip_total_cents'] ?? 0);
        $reductionTotal = (int)($orderSummary['reduction_total_cents'] ?? 0);

        // Expense entries for the month (categories flagged as "expense")
        $expenseRows = $db->fetchAllAssociative(
            "SELECT e.id,
                    e.date,
                    e.label,
                    e.amount_cents,
                    e.notes,
                    c.id   AS category_id,
                    c.name AS category_name,
                    c.kind AS category_kind
               FROM ongleri.accounting_entries e
               JOIN ongleri.accounting_categories c ON c.id = e.category_id
              WHERE e.date >= :start
                AND e.date <= :end
                AND c.kind = 'expense'
           ORDER BY e.date ASC, e.id ASC",
            ['start' => $start, 'end' => $end]
        );

        $expenses = [];
        $expenseTotal = 0;
        foreach ($expenseRows as $row) {
            $amount = (int)($row['amount_cents'] ?? 0);
            $expenseTotal += abs($amount);
            $expenses[] = [
                'id'           => (int)$row['id'],
                'date'         => $row['date'],
                'label'        => $row['label'],
                'amount_cents' => $amount,
                'notes'        => $row['notes'],
                'side'         => $amount < 0 ? 'debit' : 'credit',
                'category'     => [
                    'id'   => (int)$row['category_id'],
                    'name' => (string)$row['category_name'],
                    'kind' => (string)$row['category_kind'],
                ],
            ];
        }

        return $this->json([
            'ok' => true,
            'ym' => $ym,
            'period' => ['start' => $start, 'end' => $end],
            'incomes' => $incomes,
            'incomes_total_cents' => $incomeTotal,
            'expenses' => $expenses,
            'expenses_total_cents' => $expenseTotal,
            'net_cents' => $incomeTotal - $expenseTotal,
            'tips_total_cents' => $tipTotal,
            'reductions_total_cents' => $reductionTotal,
        ]);
    }

    /** ------------------------------------------------------------------
     * Customer income aggregation for charting (color-coded per year).
     * GET /api/pos/accounting/customer-income?years=5&limit=20&until=2025
     * ------------------------------------------------------------------ */
    #[Route('/api/pos/accounting/customer-income', name: 'api_pos_accounting_customer_income', methods: ['GET'])]
    public function customerIncome(Request $req, Connection $db): JsonResponse
    {
        $yearsQty = (int)$req->query->get('years', 5);
        if ($yearsQty < 1) {
            $yearsQty = 1;
        } elseif ($yearsQty > 10) {
            $yearsQty = 10;
        }

        $limit = (int)$req->query->get('limit', 20);
        if ($limit < 1) {
            $limit = 1;
        } elseif ($limit > 50) {
            $limit = 50;
        }

        $nowYear = (int)(new DateTimeImmutable('now'))->format('Y');
        $untilYear = (int)$req->query->get('until', $nowYear);
        if ($untilYear < 2000 || $untilYear > 9999) {
            $untilYear = $nowYear;
        }

        $fromYear = $untilYear - $yearsQty + 1;
        if ($fromYear < 2000) {
            $fromYear = 2000;
        }

        $startDate = sprintf('%04d-01-01', $fromYear);
        $endDate   = sprintf('%04d-12-31', $untilYear);

        $rows = $db->fetchAllAssociative(
            "SELECT YEAR(o.encaisse_at) AS year_num,
                    o.customer_id,
                    COALESCE(c.last_name, '')  AS last_name,
                    COALESCE(c.first_name, '') AS first_name,
                    COALESCE(c.status, 'active') AS status,
                    COUNT(*) AS orders_count,
                    SUM(o.total_cents) AS total_cents
               FROM ongleri.orders o
          LEFT JOIN ongleri.customers c ON c.id = o.customer_id
              WHERE o.encaisse_at IS NOT NULL
                AND o.customer_id IS NOT NULL
                AND o.encaisse_at >= :start
                AND o.encaisse_at <= :end
           GROUP BY YEAR(o.encaisse_at), o.customer_id",
            ['start' => $startDate, 'end' => $endDate]
        );

        $years = [];
        $customers = [];
        $perYearTotals = [];

        foreach ($rows as $row) {
            $year = (int)($row['year_num'] ?? 0);
            if ($year <= 0) {
                continue;
            }
            if ($year < $fromYear || $year > $untilYear) {
                continue;
            }
            $years[$year] = true;

            $customerId = (int)($row['customer_id'] ?? 0);
            if ($customerId <= 0) {
                continue;
            }

            $amount = (int)($row['total_cents'] ?? 0);
            $orderCount = (int)($row['orders_count'] ?? 0);
            $firstName = trim((string)($row['first_name'] ?? ''));
            $lastName  = trim((string)($row['last_name'] ?? ''));
            $fullName  = trim(sprintf('%s %s', $lastName, $firstName));

            if (!isset($customers[$customerId])) {
                $customers[$customerId] = [
                    'customer_id'  => $customerId,
                    'name'         => $fullName !== '' ? $fullName : sprintf('Client #%d', $customerId),
                    'first_name'   => $firstName,
                    'last_name'    => $lastName,
                    'status'       => (string)($row['status'] ?? 'active'),
                    'year_totals'  => [],
                    'order_counts' => [],
                    'total_cents'  => 0,
                    'total_orders' => 0,
                ];
            }

            $key = (string)$year;
            $customers[$customerId]['year_totals'][$key] = $amount;
            $customers[$customerId]['order_counts'][$key] = $orderCount;
            $customers[$customerId]['total_cents'] += $amount;
            $customers[$customerId]['total_orders'] += $orderCount;

            if (!isset($perYearTotals[$year])) {
                $perYearTotals[$year] = ['total_cents' => 0, 'orders' => 0];
            }
            $perYearTotals[$year]['total_cents'] += $amount;
            $perYearTotals[$year]['orders'] += $orderCount;
        }

        $yearsList = array_keys($years);
        sort($yearsList, SORT_NUMERIC);

        $customersList = array_values($customers);
        usort($customersList, fn($a, $b) => $b['total_cents'] <=> $a['total_cents']);
        if (count($customersList) > $limit) {
            $customersList = array_slice($customersList, 0, $limit);
        }

        foreach ($customersList as &$cust) {
            foreach ($yearsList as $year) {
                $key = (string)$year;
                if (!isset($cust['year_totals'][$key])) {
                    $cust['year_totals'][$key] = 0;
                }
                if (!isset($cust['order_counts'][$key])) {
                    $cust['order_counts'][$key] = 0;
                }
            }
            ksort($cust['year_totals'], SORT_STRING);
            ksort($cust['order_counts'], SORT_STRING);
        }
        unset($cust);

        $perYearTotalsOut = [];
        foreach ($yearsList as $year) {
            $perYearTotalsOut[] = [
                'year' => $year,
                'total_cents' => (int)($perYearTotals[$year]['total_cents'] ?? 0),
                'orders' => (int)($perYearTotals[$year]['orders'] ?? 0),
            ];
        }

        return $this->json([
            'ok' => true,
            'from_year' => $fromYear,
            'to_year' => $untilYear,
            'years' => $yearsList,
            'customers' => $customersList,
            'per_year_totals' => $perYearTotalsOut,
        ]);
    }
}
