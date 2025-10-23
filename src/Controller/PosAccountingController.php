<?php
// src/Controller/PosAccountingController.php
namespace App\Controller;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
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

        return $this->json([
            'ok' => true,
            'period' => ['start' => $start, 'end' => $end],
            'totals' => $totals,
            'last_cash_count' => $last ? [
                'id' => (int)$last['id'],
                'created_at' => $last['created_at'],
                'total_cents' => (int)$last['total_cents'],
                'expected_cash_cents' => (int)$last['expected_cash_cents'],
                'diff_cents' => (int)$last['diff_cents'],
            ] : null,
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

        return $this->json([
            'ok' => true,
            'item' => [
                'id' => (int)$row['id'],
                'count_date' => $row['count_date'],
                'total_cents' => (int)$row['total_cents'],
                'expected_cash_cents' => (int)$row['expected_cash_cents'],
                'diff_cents' => (int)$row['diff_cents'],
                'breakdown' => json_decode($row['breakdown_json'] ?? '[]', true) ?: [],
                'notes' => $row['notes'],
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
        $breakdown = (array)($p['breakdown'] ?? []);
        $notes = $p['notes'] ?? null;

        $counted = 0;
        foreach ($breakdown as $denStr => $qty) {
            $den = (int)$denStr;
            $q   = max(0, (int)$qty);
            $counted += $den * $q;
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

        $db->executeStatement($sql, [
            'user_id' => $userId,
            'count_date' => $date,
            'total_cents' => $counted,
            'expected_cash_cents' => $expected,
            'diff_cents' => $diff,
            'breakdown_json' => json_encode($breakdown, JSON_UNESCAPED_UNICODE),
            'notes' => $notes,
            'created_at' => $now,
        ]);

        if ($scope === 'month') {
            $ym = substr($start, 0, 7);
            $req2 = new Request(['ym' => $ym]);
            return $this->month($req2, $db);
        } else {
            $req2 = new Request(['date' => $date]);
            return $this->day($req2, $db);
        }
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

        return $this->json([
            'date' => $date,
            'totals' => $totals,
            'last_cash_count' => $last ? [
                'id' => (int)$last['id'],
                'created_at' => $last['created_at'],
                'total_cents' => (int)$last['total_cents'],
                'expected_cash_cents' => (int)$last['expected_cash_cents'],
                'diff_cents' => (int)$last['diff_cents'],
                'notes' => $last['notes'],
                'breakdown' => json_decode($last['breakdown_json'] ?? '[]', true) ?: [],
            ] : null,
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
            SELECT id, code, name, kind
            FROM ongleri.accounting_categories
            ORDER BY kind, name
        ");

        $items = array_map(fn($r) => [
            'id'   => (int)$r['id'],
            'code' => (string)$r['code'],
            'name' => (string)$r['name'],
            'kind' => (string)$r['kind'], // 'bank' | 'expense'
        ], $rows ?? []);

        return $this->json($items);
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

        // Load all categories
        $cats = $db->fetchAllAssociative("
            SELECT id, name, kind
            FROM ongleri.accounting_categories
            ORDER BY kind, name
        ");
        $byId = [];
        foreach ($cats as $c) {
            $byId[(int)$c['id']] = [
                'id' => (int)$c['id'],
                'name' => (string)$c['name'],
                'kind' => (string)$c['kind'],
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

        return $this->json([
            'ym' => $ym,
            'categories' => array_values($byId),
            'totals' => ['debit_cents' => $totDebit, 'credit_cents' => $totCredit],
        ]);
    }
		
}
