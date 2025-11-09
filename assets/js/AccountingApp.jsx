// assets/js/AccountingApp.jsx
import React, { useEffect, useMemo, useState, useCallback, useRef } from "react";
import Plotly from "plotly.js-dist-min";

/* ——— helpers ——— */
const fmtMoney = (c) =>
  (c / 100).toLocaleString("fr-FR", { style: "currency", currency: "EUR" });

const fmtDateFr = (s) => {
  if (!s) return "—";
  const iso = String(s).includes("T") ? String(s) : `${s}T00:00:00`;
  const d = new Date(iso);
  return Number.isNaN(d.getTime()) ? String(s) : d.toLocaleDateString("fr-FR");
};

const fmtDateTimeFr = (s) => {
  if (!s) return "—";
  const normalized = String(s).replace(" ", "T");
  const d = new Date(normalized);
  return Number.isNaN(d.getTime())
    ? String(s)
    : d.toLocaleString("fr-FR", { dateStyle: "short", timeStyle: "short" });
};

const euroToCents = (val) => {
  if (val == null) return 0;
  const normalized = String(val)
    .trim()
    .replace(/\s/g, "")
    .replace(/€/g, "")
    .replace(/,/g, ".");
  const n = Number(normalized);
  if (!Number.isFinite(n)) return 0;
  return Math.max(0, Math.round(n * 100));
};

const centsToEuroInput = (cents) => {
  if (!Number.isFinite(cents)) return "";
  return (cents / 100).toLocaleString("fr-FR", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
};

const pad2 = (n) => String(n).padStart(2, "0");
const daysInMonth = (y, m) => new Date(y, m, 0).getDate(); // m = 1..12
const monthBounds = (ym) => {
  const [Y, M] = ym.split("-").map(Number);
  const start = `${Y}-${pad2(M)}-01`;
  const end = `${Y}-${pad2(M)}-${pad2(daysInMonth(Y, M))}`;
  return { start, end };
};
const thisMonthYM = () => {
  const d = new Date();
  return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}`;
};
const prevOfYM = (ym) => {
  const [Y, M] = ym.split("-").map(Number);
  const d = new Date(Y, M - 1, 1);
  d.setMonth(d.getMonth() - 1);
  return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}`;
};
const monthEndDate = (ym) => monthBounds(ym).end;
/** Clamp today's day into the selected month (use last day if shorter). */
const clampTodayToYM = (ym) => {
  const d = new Date();
  const [Y, M] = ym.split("-").map(Number);
  const day = Math.min(d.getDate(), daysInMonth(Y, M));
  return `${ym}-${pad2(day)}`;
};

const YEAR_COLORS = [
  "#2563eb",
  "#16a34a",
  "#dc2626",
  "#f59e0b",
  "#a855f7",
  "#0ea5e9",
  "#f97316",
  "#22c55e",
  "#9333ea",
  "#ef4444",
];

const CUSTOMER_STATUS_LABELS = {
  active: "Actif",
  inactive: "Inactif",
  banned: "Banni",
  test: "Test",
};

const customerStatusLabel = (status) =>
  CUSTOMER_STATUS_LABELS[status] || (status ? String(status) : "—");

/* ——— Caisse denominations ——— */
const DENOMS = [
  { c: 20000, label: "200" },
  { c: 10000, label: "100" },
  { c: 5000, label: "50" },
  { c: 2000, label: "20" },
  { c: 1000, label: "10" },
  { c: 500, label: "5" },
  { c: 200, label: "2" },
  { c: 100, label: "1" },
  { c: 50, label: "0.5" },
  { c: 20, label: "0.2" },
  { c: 10, label: "0.1" },
  { c: 5, label: "0.05" },
  { c: 2, label: "0.02" },
  { c: 1, label: "0.01" },
];
const NOTE_DENOMS = DENOMS.filter((d) => d.c >= 500);
const COIN_DENOMS = DENOMS.filter((d) => d.c < 500);
const emptyQty = () => Object.fromEntries(DENOMS.map((d) => [d.c, 0]));

const sumFromQty = (qtyMap) =>
  DENOMS.reduce((acc, d) => acc + d.c * (Number(qtyMap?.[d.c]) || 0), 0);

const qtyEquals = (a, b) =>
  DENOMS.every(({ c }) => Number(a?.[c] || 0) === Number(b?.[c] || 0));

const computeFondGreedy = (targetCents, availableQty) => {
  const result = emptyQty();
  let remaining = Math.max(0, targetCents);
  for (const { c } of DENOMS) {
    if (remaining <= 0) break;
    const available = Math.max(0, Number(availableQty?.[c] || 0));
    if (!available) continue;
    const usable = Math.min(available, Math.floor(remaining / c));
    if (!usable) continue;
    result[c] = usable;
    remaining -= usable * c;
  }
  return result;
};

const clampQtyToAvailable = (candidateQty, maxAvailableQty) => {
  const next = emptyQty();
  let changed = false;
  for (const { c } of DENOMS) {
    const sourceVal = Math.max(0, Number(candidateQty?.[c] || 0));
    const maxVal = Math.max(0, Number(maxAvailableQty?.[c] || 0));
    const clamped = Math.min(sourceVal, maxVal);
    next[c] = clamped;
    if (clamped !== sourceVal) changed = true;
  }
  return { next, changed };
};

/* ======= PRESENTATIONAL SUBCOMPONENTS (top-level, stable identities) ======= */

const TableBlock = React.memo(function TableBlock({
  denoms,
  prevYM,
  monthYM,
  qtyPrev,
  qtyCurr,
  onChangeQty,
  fondQty,
  onChangeFondQty,
}) {
  return (
    <table className="table table-sm align-middle mb-4">
      <thead>
        <tr>
          <th style={{ width: 80 }}></th>
          <th colSpan={2} className="text-center">{prevYM} (lecture seule)</th>
          <th colSpan={4} className="text-center">{monthYM} (saisie)</th>
        </tr>
        <tr>
          <th className="text-muted small">
            {denoms === NOTE_DENOMS ? "Billets" : "Pièces"}
          </th>
          <th className="text-center small">#</th>
          <th className="text-end small">€</th>
          <th className="text-center small">#</th>
          <th className="text-end small">€</th>
          <th className="text-center small">Fond #</th>
          <th className="text-end small">Fond €</th>
        </tr>
      </thead>
      <tbody>
        {denoms.map((d) => {
          const prevCount = qtyPrev[d.c];
          const currCount = qtyCurr[d.c];
          const fondCount = Number(fondQty?.[d.c] || 0);
          const fondAmt = d.c * fondCount;
          return (
            <tr key={d.c}>
              <td className="fw-semibold">{d.label}</td>
              <td style={{ width: 90 }}>
                <input
                  type="number"
                  className="form-control form-control-sm text-center"
                  value={prevCount}
                  disabled
                />
              </td>
              <td className="text-end" style={{ width: 120 }}>
                {fmtMoney(d.c * (Number(prevCount) || 0))}
              </td>
              <td style={{ width: 90 }}>
                <input
                  type="number"
                  min="0"
                  step="1"
                  className="form-control form-control-sm text-center"
                  value={currCount}
                  onChange={(e) => onChangeQty(d.c, e.target.value)}
                />
              </td>
              <td className="text-end" style={{ width: 120 }}>
                {fmtMoney(d.c * (Number(currCount) || 0))}
              </td>
              <td style={{ width: 90 }}>
                <input
                  type="number"
                  min="0"
                  max={Number(currCount) || 0}
                  step="1"
                  className="form-control form-control-sm text-center"
                  value={fondCount}
                  onChange={(e) => onChangeFondQty(d.c, e.target.value)}
                />
              </td>
              <td
                className={`text-end${fondAmt > 0 ? " fw-semibold" : " text-muted"}`}
                style={{ width: 130 }}
                title={fondCount > 0 ? `${fondCount} × ${d.label} €` : "Aucun billet/pièce pour le fond"}
              >
                {fmtMoney(fondAmt)}
              </td>
            </tr>
          );
        })}
      </tbody>
    </table>
  );
});

const TransferCard = React.memo(function TransferCard({
  monthYM,
  xfer,
  bankCats,
  expenseCats,
  onChangeXfer,
  onCreateTransfer,
}) {
  const allCats = useMemo(() => [...bankCats, ...expenseCats], [bankCats, expenseCats]);

  return (
    <div className="card shadow-sm mb-3">
      <div className="card-header">
        <strong>Virement (double écriture)</strong>
      </div>
      <div className="card-body">
        <div className="row g-2">
          <div className="col-md-2">
            <label className="form-label small">Date</label>
            <input
              type="date"
              className="form-control form-control-sm"
              value={xfer.date || ""}
              onChange={(e) => onChangeXfer("date", e.target.value)}
              max={monthEndDate(monthYM)}
              min={`${monthYM}-01`}
            />
          </div>
          <div className="col-md-3">
            <label className="form-label small">De</label>
            <select
              className="form-select form-select-sm"
              value={xfer.from_category_id ?? ""}
              onChange={(e) =>
                onChangeXfer("from_category_id", Number(e.target.value) || null)
              }
            >
              <option value="">— catégorie source —</option>
              {allCats.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.name}
                </option>
              ))}
            </select>
          </div>
          <div className="col-md-3">
            <label className="form-label small">Vers</label>
            <select
              className="form-select form-select-sm"
              value={xfer.to_category_id ?? ""}
              onChange={(e) =>
                onChangeXfer("to_category_id", Number(e.target.value) || null)
              }
            >
              <option value="">— catégorie destination —</option>
              {allCats.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.name}
                </option>
              ))}
            </select>
          </div>
          <div className="col-md-2">
            <label className="form-label small">Montant (€)</label>
            <input
              type="text"
              inputMode="decimal"
              className="form-control form-control-sm text-end"
              placeholder="0,00"
              value={xfer.amount ?? ""}
              onChange={(e) => onChangeXfer("amount", e.target.value)}
            />
          </div>
          <div className="col-md-2 d-flex align-items-end">
            <button
              className="btn btn-primary btn-sm w-100"
              onClick={onCreateTransfer}
            >
              Enregistrer le virement
            </button>
          </div>
          <div className="col-12">
            <label className="form-label small">Libellé</label>
            <input
              type="text"
              className="form-control form-control-sm"
              placeholder="Virement Caisse → Banque (par ex.)"
              value={xfer.label ?? ""}
              onChange={(e) => onChangeXfer("label", e.target.value)}
            />
          </div>
          <div className="col-12">
            <label className="form-label small">Notes</label>
            <input
              type="text"
              className="form-control form-control-sm"
              value={xfer.notes ?? ""}
              onChange={(e) => onChangeXfer("notes", e.target.value)}
            />
          </div>
        </div>
      </div>
    </div>
  );
});

const JournalCard = React.memo(function JournalCard({
  monthYM,
  entries,
  cats,
  newEntry,
  onChangeNewEntry,
  createEntry,
  deleteEntry,
}) {
  const rows = useMemo(
    () =>
      entries
        .slice()
        .sort((a, b) => (a.date === b.date ? a.id - b.id : a.date.localeCompare(b.date))),
    [entries]
  );

  let debit = 0,
    credit = 0;
  rows.forEach((e) => {
    const amt = Number(e.amount_cents || 0);
    if (amt < 0) debit += -amt;
    else credit += amt;
  });

  return (
    <div className="card shadow-sm mb-3">
      <div className="card-header d-flex justify-content-between align-items-center">
        <strong>Journal — {monthYM}</strong>
        <span className="small text-muted">Écritures du mois</span>
      </div>

      <div className="card-body">
        <table className="table table-sm align-middle mb-3">
          <thead>
            <tr>
              <th style={{ width: 110 }}>Date</th>
              <th>Libellé</th>
              <th style={{ width: 220 }}>Catégorie</th>
              <th className="text-end" style={{ width: 140 }}>
                Débit (−)
              </th>
              <th className="text-end" style={{ width: 140 }}>
                Crédit (+)
              </th>
              <th style={{ width: 60 }}></th>
            </tr>
          </thead>
          <tbody>
            {rows.map((e) => {
              const amt = Number(e.amount_cents || 0);
              const isDebit = amt < 0;
              return (
                <tr key={e.id}>
                  <td>{e.date}</td>
                  <td>{e.label}</td>
                  <td>{cats.find((c) => c.id === e.category_id)?.name || "—"}</td>
                  <td className="text-end">{isDebit ? fmtMoney(-amt) : "—"}</td>
                  <td className="text-end">{!isDebit ? fmtMoney(amt) : "—"}</td>
                  <td className="text-end">
                    <button
                      className="btn btn-outline-danger btn-sm"
                      onClick={() => deleteEntry(e.id)}
                    >
                      Suppr.
                    </button>
                  </td>
                </tr>
              );
            })}
          </tbody>
          <tfoot>
            <tr className="fw-semibold">
              <td colSpan={3}>Total</td>
              <td className="text-end">{fmtMoney(debit)}</td>
              <td className="text-end">{fmtMoney(credit)}</td>
              <td></td>
            </tr>
          </tfoot>
        </table>

        {/* Add form */}
        <div className="border-top pt-2">
          <div className="row g-2">
            <div className="col-md-2">
              <input
                type="date"
                className="form-control form-control-sm"
                value={newEntry.date || ""}
                onChange={(e) => onChangeNewEntry("date", e.target.value)}
                max={monthEndDate(monthYM)}
                min={`${monthYM}-01`}
              />
            </div>
            <div className="col-md-3">
              <input
                type="text"
                className="form-control form-control-sm"
                placeholder="Libellé"
                value={newEntry.label ?? ""}
                onChange={(e) => onChangeNewEntry("label", e.target.value)}
              />
            </div>
            <div className="col-md-3">
              <select
                className="form-select form-select-sm"
                value={newEntry.category_id ?? ""}
                onChange={(e) =>
                  onChangeNewEntry("category_id", Number(e.target.value) || null)
                }
              >
                <option value="">— catégorie —</option>
                {cats.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name}
                  </option>
                ))}
              </select>
            </div>
            <div className="col-md-2">
              <select
                className="form-select form-select-sm"
                value={newEntry.side}
                onChange={(e) => onChangeNewEntry("side", e.target.value)}
              >
                <option value="debit">Débit (−)</option>
                <option value="credit">Crédit (+)</option>
              </select>
            </div>
            <div className="col-md-1">
              <input
                type="text"
                inputMode="decimal"
                className="form-control form-control-sm text-end"
                placeholder="0,00"
                value={newEntry.amount ?? ""}
                onChange={(e) => onChangeNewEntry("amount", e.target.value)}
              />
            </div>
            <div className="col-md-1 d-grid">
              <button className="btn btn-primary btn-sm" onClick={createEntry}>
                Ajouter
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
});

const LedgerTable = React.memo(function LedgerTable({ ledger, monthYM }) {
  const cats = ledger?.categories || [];
  const tDebit = ledger?.totals?.debit_cents || 0;
  const tCredit = ledger?.totals?.credit_cents || 0;
  const balanced = tDebit === tCredit;

  return (
    <div className="card shadow-sm">
      <div className="card-header d-flex justify-content-between align-items-center">
        <strong>Grand livre — {monthYM}</strong>
        <span className={`badge ${balanced ? "text-bg-success" : "text-bg-danger"}`}>
          {balanced ? "Équilibré" : "Déséquilibré"}
        </span>
      </div>
      <div className="card-body">
        <table className="table table-sm">
          <thead>
            <tr>
              <th>Catégorie</th>
              <th className="text-end">Débit (−)</th>
              <th className="text-end">Crédit (+)</th>
            </tr>
          </thead>
          <tbody>
            {cats.map((c) => (
              <tr key={c.id}>
                <td>{c.name}</td>
                <td className="text-end">{fmtMoney(c.debit_cents || 0)}</td>
                <td className="text-end">{fmtMoney(c.credit_cents || 0)}</td>
              </tr>
            ))}
          </tbody>
          <tfoot>
            <tr className="fw-semibold">
              <td>Total</td>
              <td className="text-end">{fmtMoney(tDebit)}</td>
              <td className="text-end">{fmtMoney(tCredit)}</td>
            </tr>
          </tfoot>
        </table>
        <div className="form-text">
          Les virements créent automatiquement une écriture négative sur la source (débit) et positive sur la destination (crédit).
        </div>
      </div>
    </div>
  );
});

/* ======= Main Component ======= */
export default function AccountingApp() {
  const [activeTab, setActiveTab] = useState("caisse"); // "caisse" | "compta"

  // Shared month selector
  const [monthYM, setMonthYM] = useState(thisMonthYM());
  const prevYM = prevOfYM(monthYM);
  const { start: effStart, end: effEnd } = monthBounds(monthYM);

  const [loading, setLoading] = useState(false);
  const [err, setErr] = useState(null);

  /* ======= CAISSE ======= */
  const [summary, setSummary] = useState({
    totals: { cash_cents: 0, card_cents: 0, other_cents: 0, orders_total_cents: 0 },
    last_cash_count: null,
  });
  const [qtyPrev, setQtyPrev] = useState(emptyQty()); // read-only
  const [qtyCurr, setQtyCurr] = useState(emptyQty()); // editable
  const [fondQty, setFondQty] = useState(emptyQty());
  const [fondQtyManual, setFondQtyManual] = useState(false);
  const [notes, setNotes] = useState("");
  const [fondPreferenceCents, setFondPreferenceCents] = useState(20000);
  const [fondPreferenceInput, setFondPreferenceInput] = useState(centsToEuroInput(20000));
  const [fondFromId, setFondFromId] = useState(null);
  const [fondToId, setFondToId] = useState(null);
  const [prevLoaded, setPrevLoaded] = useState(false);
  const [monthLoaded, setMonthLoaded] = useState(false);
  const [customersYears, setCustomersYears] = useState(5);
  const [customersLimit, setCustomersLimit] = useState(15);
  const [customersData, setCustomersData] = useState(null);
  const [loadingCustomers, setLoadingCustomers] = useState(false);
  const [customersErr, setCustomersErr] = useState(null);
  const customersChartRef = useRef(null);

  useEffect(() => {
    try {
      const amt = localStorage.getItem('pos.fond.amount');
      if (amt) {
        const parsed = amt.includes('.') || amt.includes(',') ? euroToCents(amt) : Number(amt);
        if (Number.isFinite(parsed) && parsed >= 0) {
          setFondPreferenceCents(parsed);
        }
      }
      const fromStored = localStorage.getItem('pos.fond.from');
      if (fromStored) setFondFromId(Number(fromStored));
      const toStored = localStorage.getItem('pos.fond.to');
      if (toStored) setFondToId(Number(toStored));
    } catch {
      // ignore storage errors
    }
  }, []);

  useEffect(() => {
    try {
      localStorage.setItem('pos.fond.amount', String(fondPreferenceCents));
    } catch {
      // ignore
    }
  }, [fondPreferenceCents]);

  useEffect(() => {
    setFondPreferenceInput(centsToEuroInput(fondPreferenceCents));
  }, [fondPreferenceCents]);

  useEffect(() => {
    try {
      if (fondFromId) localStorage.setItem('pos.fond.from', String(fondFromId));
    } catch {
      // ignore
    }
  }, [fondFromId]);

  useEffect(() => {
    try {
      if (fondToId) localStorage.setItem('pos.fond.to', String(fondToId));
    } catch {
      // ignore
    }
  }, [fondToId]);
  const countedPrev = useMemo(() => sumFromQty(qtyPrev), [qtyPrev]);
  const countedCurr = useMemo(() => sumFromQty(qtyCurr), [qtyCurr]);
  const countedTotal = useMemo(
    () => countedPrev + countedCurr,
    [countedPrev, countedCurr]
  );

  const fondCaisseCents = useMemo(() => sumFromQty(fondQty), [fondQty]);
  const fondDesiredCents = useMemo(
    () => (fondQtyManual ? fondCaisseCents : Math.min(fondPreferenceCents, countedCurr)),
    [fondQtyManual, fondCaisseCents, fondPreferenceCents, countedCurr]
  );
  const systemCashCents = summary?.totals?.cash_cents || 0;
  const fondBreakdown = useMemo(() => {
    const countByDenom = {};
    let allocated = 0;
    for (const { c } of DENOMS) {
      const count = Math.max(0, Number(fondQty?.[c] || 0));
      countByDenom[c] = count;
      const amount = count * c;
      allocated += amount;
    }
    return {
      countByDenom,
      allocated,
      shortfall: Math.max(0, fondDesiredCents - allocated),
    };
  }, [fondQty, fondDesiredCents]);
  const fondAllocatedCents = fondBreakdown.allocated || 0;
  const fondShortfall = fondBreakdown.shortfall || 0;
  const fondExcess = Math.max(0, fondAllocatedCents - fondDesiredCents);
  const amountToTransfer = useMemo(
    () => Math.max(0, countedTotal - Math.min(fondAllocatedCents, countedTotal)),
    [countedTotal, fondAllocatedCents]
  );
  const fondTransferCents = useMemo(
    () => Math.max(0, countedCurr - fondAllocatedCents),
    [countedCurr, fondAllocatedCents]
  );
  const diffCents = useMemo(
    () => amountToTransfer - systemCashCents,
    [amountToTransfer, systemCashCents]
  );
  const fondConfigValid = fondAllocatedCents === 0 || (fondFromId && fondToId && fondFromId !== fondToId);
  const fondExceedsTotal = fondAllocatedCents > countedCurr;
  const handleFondPreferenceChange = useCallback((e) => {
    setFondPreferenceInput(e.target.value);
  }, []);
  const handleFondPreferenceBlur = useCallback(() => {
    const cents = euroToCents(fondPreferenceInput);
    setFondPreferenceCents(cents);
    setFondPreferenceInput(centsToEuroInput(cents));
    setFondQtyManual(false);
  }, [fondPreferenceInput]);
  const handleFondFromChange = useCallback((e) => {
    const val = e.target.value;
    setFondFromId(val ? Number(val) : null);
  }, []);
  const handleFondToChange = useCallback((e) => {
    const val = e.target.value;
    setFondToId(val ? Number(val) : null);
  }, []);

  useEffect(() => {
    if (fondQtyManual) return;
    const target = Math.min(fondPreferenceCents, countedCurr);
    const auto = computeFondGreedy(target, qtyCurr);
    if (!qtyEquals(fondQty, auto)) {
      setFondQty(auto);
    }
  }, [fondQtyManual, fondPreferenceCents, countedCurr, qtyCurr, fondQty]);

  useEffect(() => {
    if (!fondQtyManual) return;
    const { next, changed } = clampQtyToAvailable(fondQty, qtyCurr);
    if (changed && !qtyEquals(fondQty, next)) {
      setFondQty(next);
    }
  }, [fondQtyManual, fondQty, qtyCurr]);

  useEffect(() => () => {
    if (customersChartRef.current) {
      Plotly.purge(customersChartRef.current);
    }
  }, []);

  useEffect(() => {
    if (activeTab !== "clients") {
      return;
    }
    const el = customersChartRef.current;
    if (!el) {
      return;
    }
    const years = customersData?.years || [];
    const customersList = customersData?.customers || [];
    if (!years.length || !customersList.length) {
      Plotly.purge(el);
      return;
    }

    const names = customersList.map((cust) => cust.name || `Client #${cust.customer_id}`);
    const statusTexts = customersList.map((cust) => customerStatusLabel(cust.status));
    const traces = years.map((year, idx) => {
      const yearKey = String(year);
      const values = customersList.map((cust) => (Number(cust.year_totals?.[yearKey] || 0) / 100));
      return {
        type: 'bar',
        name: String(year),
        x: names,
        y: values,
        text: statusTexts,
        marker: {
          color: YEAR_COLORS[idx % YEAR_COLORS.length],
        },
        hovertemplate: `<b>%{x}</b><br>${year}: %{y:.2f} €<br>Statut: %{text}<extra></extra>`,
      };
    });

    const layout = {
      barmode: 'group',
      margin: { t: 40, r: 20, b: 120, l: 60 },
      legend: { orientation: 'h', x: 0, y: 1.1 },
      xaxis: {
        automargin: true,
      },
      yaxis: {
        title: 'Recettes (€)',
        hoverformat: '.2f',
        tickformat: ',.2f',
        rangemode: 'tozero',
      },
      hovermode: 'closest',
      title: {
        text: 'Top clients par recettes',
        font: { size: 16 },
      },
    };

    Plotly.react(el, traces, layout, { responsive: true, displayModeBar: false });
  }, [activeTab, customersData]);

  const preloadQtyFromBreakdown = (breakdown) => {
    const q = emptyQty();
    for (const [k, v] of Object.entries(breakdown || {})) {
      const key = Number(k);
      if (Object.prototype.hasOwnProperty.call(q, key)) q[key] = v;
    }
    return q;
  };

  const fetchCashCountByMonth = async (ym) => {
    const endDate = monthEndDate(ym);
    const rr = await fetch(
      `/api/pos/accounting/cash-count?date=${encodeURIComponent(endDate)}`
    );
    if (!rr.ok) return null;
    const jj = await rr.json().catch(() => ({}));
    const item = jj?.item || jj?.last_cash_count || jj || {};
    let breakdown = item?.breakdown || null;
    if (!breakdown && typeof item?.breakdown_json === "string") {
      try {
        breakdown = JSON.parse(item.breakdown_json);
      } catch {
        breakdown = null;
      }
    }
    const nt = item?.notes || "";
    const fondMeta = item?.fond_caisse || null;
    return { breakdown, notes: nt, fond: fondMeta };
  };

  const loadMonth = async () => {
    setLoading(true);
    setErr(null);
    setPrevLoaded(false);
    setMonthLoaded(false);
    try {
      const r = await fetch(
        `/api/pos/accounting/month?ym=${encodeURIComponent(monthYM)}`
      );
      const j = await r.json();
      if (!r.ok) throw new Error(j?.error || `HTTP ${r.status}`);
      setSummary(j);

      const prevData = await fetchCashCountByMonth(prevYM);
      if (prevData?.breakdown) {
        setQtyPrev(preloadQtyFromBreakdown(prevData.breakdown));
        setPrevLoaded(true);
      } else {
        setQtyPrev(emptyQty());
        setPrevLoaded(false);
      }

      const currData = await fetchCashCountByMonth(monthYM);
      const currQty = currData?.breakdown
        ? preloadQtyFromBreakdown(currData.breakdown)
        : emptyQty();
      setQtyCurr(currQty);
      setMonthLoaded(Boolean(currData?.breakdown));

      setNotes(currData?.notes || "");

      if (currData?.fond) {
        const amount = Math.max(0, Number(currData.fond.amount_cents || 0));
        setFondPreferenceCents(amount);
        setFondFromId(currData.fond.from_category_id || null);
        setFondToId(currData.fond.to_category_id || null);

        if (currData.fond.breakdown) {
          const loaded = preloadQtyFromBreakdown(currData.fond.breakdown);
          const { next: clamped } = clampQtyToAvailable(loaded, currQty);
          setFondQty(clamped);
          const hasFondValues = sumFromQty(clamped) > 0;
          setFondQtyManual(hasFondValues);
        } else {
          setFondQty(emptyQty());
          setFondQtyManual(false);
        }
      } else {
        setFondQty(emptyQty());
        setFondQtyManual(false);
      }
    } catch (e) {
      setErr(e.message || "Erreur");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadMonth();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [monthYM]);

  const onChangeQty = useCallback((den, value) => {
    const v = Math.max(0, Math.floor(Number(value) || 0));
    setQtyCurr((p) => ({ ...p, [den]: v }));
  }, []);

  const onChangeFondQty = useCallback(
    (den, value) => {
      const raw = Math.max(0, Math.floor(Number(value) || 0));
      const max = Math.max(0, Number(qtyCurr[den] || 0));
      const nextVal = Math.min(raw, max);
      setFondQtyManual(true);
      setFondQty((prev) => {
        if (Number(prev?.[den] || 0) === nextVal) return prev;
        return { ...prev, [den]: nextVal };
      });
    },
    [qtyCurr]
  );

  const clearCount = () => {
    setQtyCurr(emptyQty());
    setFondQty(emptyQty());
    setFondQtyManual(false);
    setNotes("");
    setMonthLoaded(false);
  };

  const save = async () => {
    setErr(null);
    if (!fondConfigValid) {
      setErr("Sélectionnez des catégories distinctes pour le fond de caisse.");
      return;
    }
    if (fondExceedsTotal) {
      setErr("Le fond de caisse dépasse le montant compté.");
      return;
    }

    setLoading(true);
    try {
      const { start: period_start, end: period_end } = monthBounds(monthYM);
      const payload = {
        date: period_end,
        period_start,
        period_end,
        scope: "month",
        breakdown: Object.fromEntries(
          DENOMS.map((d) => [String(d.c), Number(qtyCurr[d.c]) || 0])
        ),
        notes: (`[Comptage mensuel ${monthYM}]` + (notes ? ` ${notes}` : "")).trim(),
        fond_caisse_cents: fondCaisseCents,
        fond_from_category_id:
          fondCaisseCents > 0 && fondFromId ? Number(fondFromId) : null,
        fond_to_category_id:
          fondCaisseCents > 0 && fondToId ? Number(fondToId) : null,
        fond_breakdown: Object.fromEntries(
          DENOMS.map((d) => [String(d.c), Number(fondBreakdown.countByDenom[d.c]) || 0])
        ),
      };
      const r = await fetch(`/api/pos/accounting/cash-count`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      const j = await r.json().catch(() => ({}));
      if (!r.ok || j?.ok === false) throw new Error(j?.error || `HTTP ${r.status}`);
      await loadMonth();
    } catch (e) {
      setErr(e.message || "Erreur");
    } finally {
      setLoading(false);
    }
  };

  /* ======= COMPTABILITÉ (Journal + Virements + Grand livre) ======= */

  // Categories (DB-modifiable)
  const [cats, setCats] = useState([]);
  const fondCategoryOptions = useMemo(() => {
    if (!cats.length) return [];
    return [...cats].sort((a, b) => (a.name || "").localeCompare(b.name || "", "fr", { sensitivity: "base" }));
  }, [cats]);
  const fondFromCat = useMemo(
    () => cats.find((c) => c.id === fondFromId) || null,
    [cats, fondFromId]
  );
  const fondToCat = useMemo(
    () => cats.find((c) => c.id === fondToId) || null,
    [cats, fondToId]
  );
  const fondAutoLabel = fondFromCat && fondToCat ? `${fondFromCat.name} → ${fondToCat.name}` : "—";
  // Entries for month
  const [entries, setEntries] = useState([]);
  const [loadingCompta, setLoadingCompta] = useState(false);

  // Ledger (debit/credit per category)
  const [ledger, setLedger] = useState(null);
  const [loadingLedger, setLoadingLedger] = useState(false);

  // Rapport (income vs expense overview)
  const [report, setReport] = useState(null);
  const [loadingReport, setLoadingReport] = useState(false);
  const [reportErr, setReportErr] = useState(null);

  useEffect(() => {
    if (cats.length) return;
    (async () => {
      try {
        const resp = await fetch(`/api/accounting/categories`);
        if (resp.ok) {
          const data = await resp.json();
          setCats(Array.isArray(data) ? data : data?.items || []);
        }
      } catch {
        // ignore prefetch errors
      }
    })();
  }, [cats.length]);

  const bankCats = useMemo(() => cats.filter((c) => c.kind === "bank"), [cats]);
  const expenseCats = useMemo(() => cats.filter((c) => c.kind === "expense"), [cats]);
  const customersYearsList = useMemo(
    () => (Array.isArray(customersData?.years) ? customersData.years.map(Number) : []),
    [customersData]
  );
  const customersList = useMemo(
    () => (Array.isArray(customersData?.customers) ? customersData.customers : []),
    [customersData]
  );
  const customersYearRangeLabel = useMemo(() => {
    if (!customersYearsList.length) return null;
    const from = customersData?.from_year;
    const to = customersData?.to_year;
    if (from && to) {
      return `${from} – ${to}`;
    }
    return null;
  }, [customersData, customersYearsList]);
  const customersPerYearTotals = useMemo(
    () => (Array.isArray(customersData?.per_year_totals) ? customersData.per_year_totals : []),
    [customersData]
  );

  const fondSourceDefaultId = useMemo(() => {
    if (!cats.length) return null;
    const byCode = cats.find(
      (c) => (c.code && c.code.toLowerCase() === "caisse") || /caisse/i.test(c.name || "")
    );
    if (byCode) return byCode.id;
    const firstBank = cats.find((c) => c.kind === "bank");
    return firstBank ? firstBank.id : null;
  }, [cats]);

  const fondDestDefaultId = useMemo(() => {
    if (!cats.length) return null;
    const fondCat = cats.find((c) => /fond/i.test(c.name || ""));
    if (fondCat) return fondCat.id;
    const altBank = cats.find((c) => c.kind === "bank" && c.id !== fondSourceDefaultId);
    if (altBank) return altBank.id;
    const any = cats.find((c) => c.id !== fondSourceDefaultId);
    return any ? any.id : null;
  }, [cats, fondSourceDefaultId]);

  useEffect(() => {
    if (!fondFromId && fondSourceDefaultId) {
      setFondFromId(fondSourceDefaultId);
    }
  }, [fondFromId, fondSourceDefaultId]);

  useEffect(() => {
    if (!fondToId && fondDestDefaultId) {
      setFondToId(fondDestDefaultId);
    }
  }, [fondToId, fondDestDefaultId]);

  const totals = useMemo(() => {
    let bank = 0,
      expense = 0;
    for (const e of entries) {
      const cat = cats.find((c) => c.id === e.category_id);
      if (!cat) continue;
      if (cat.kind === "bank") bank += e.amount_cents || 0;
      else if (cat.kind === "expense") expense += e.amount_cents || 0;
    }
    return { bank, expense };
  }, [entries, cats]);

  const reportIncomeTotal = report?.incomes_total_cents || 0;
  const reportExpenseTotal = report?.expenses_total_cents || 0;
  const reportNetTotal =
    report?.net_cents !== undefined ? report.net_cents : reportIncomeTotal - reportExpenseTotal;
  const reportIncomes = report?.incomes || [];
  const reportExpenses = report?.expenses || [];
  const reportNetClass =
    reportNetTotal === 0 ? "text-muted" : reportNetTotal > 0 ? "text-success" : "text-danger";

  const loadCompta = async () => {
    setLoadingCompta(true);
    try {
      const [rc, re] = await Promise.all([
        fetch(`/api/accounting/categories`),
        fetch(`/api/accounting/entries?ym=${encodeURIComponent(monthYM)}`),
      ]);
      const jc = rc.ok ? await rc.json() : [];
      const je = re.ok ? await re.json() : [];
      setCats(Array.isArray(jc) ? jc : jc?.items || []);
      setEntries(Array.isArray(je) ? je : je?.items || []);
    } catch {
      // ignore
    } finally {
      setLoadingCompta(false);
    }
  };

  const loadLedger = async () => {
    setLoadingLedger(true);
    try {
      const r = await fetch(`/api/accounting/ledger?ym=${encodeURIComponent(monthYM)}`);
      const j = r.ok ? await r.json() : null;
      setLedger(j);
    } catch {
      setLedger(null);
    } finally {
      setLoadingLedger(false);
    }
  };

  const loadReport = useCallback(async () => {
    setLoadingReport(true);
    setReportErr(null);
    try {
      const r = await fetch(
        `/api/pos/accounting/report?ym=${encodeURIComponent(monthYM)}`
      );
      const j = await r.json().catch(() => ({}));
      if (!r.ok || j?.ok === false) {
        throw new Error(j?.error || `HTTP ${r.status}`);
      }
      setReport(j);
    } catch (e) {
      setReport(null);
      setReportErr(e.message || "Erreur");
    } finally {
      setLoadingReport(false);
    }
  }, [monthYM]);

  const loadCustomersIncome = useCallback(async () => {
    const untilYear = Number((monthYM || `${new Date().getFullYear()}-01`).split('-')[0]) || new Date().getFullYear();
    setLoadingCustomers(true);
    setCustomersErr(null);
    try {
      const url = `/api/pos/accounting/customer-income?years=${encodeURIComponent(customersYears)}&limit=${encodeURIComponent(customersLimit)}&until=${encodeURIComponent(untilYear)}`;
      const resp = await fetch(url);
      const data = await resp.json().catch(() => ({}));
      if (!resp.ok || data?.ok === false) {
        throw new Error(data?.error || `HTTP ${resp.status}`);
      }
      const customers = Array.isArray(data?.customers)
        ? data.customers.map((cust) => ({ ...cust, status: cust.status || "active" }))
        : [];
      setCustomersData({
        years: Array.isArray(data?.years) ? data.years : [],
        customers,
        from_year: data?.from_year ?? null,
        to_year: data?.to_year ?? null,
        per_year_totals: Array.isArray(data?.per_year_totals) ? data.per_year_totals : [],
      });
    } catch (e) {
      setCustomersData({ years: [], customers: [], per_year_totals: [] });
      setCustomersErr(e.message || 'Erreur');
    } finally {
      setLoadingCustomers(false);
    }
  }, [customersYears, customersLimit, monthYM]);

  useEffect(() => {
    if (activeTab === "compta") {
      loadCompta();
      loadLedger();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [activeTab, monthYM]);

  useEffect(() => {
    if (activeTab === "rapport") {
      loadReport();
    }
  }, [activeTab, loadReport]);

  useEffect(() => {
    if (activeTab === "clients") {
      loadCustomersIncome();
    }
  }, [activeTab, loadCustomersIncome]);

  // Journal input state (default date = today clamped)
  const [newEntry, setNewEntry] = useState({
    date: clampTodayToYM(monthYM),
    label: "",
    category_id: null,
    side: "credit", // 'debit'|'credit'
    amount: "", // €
    notes: "",
  });

  const onChangeNewEntry = useCallback((field, value) => {
    setNewEntry((p) => ({ ...p, [field]: value ?? "" }));
  }, []);

  useEffect(() => {
    const d = clampTodayToYM(monthYM);
    setNewEntry((p) => ({ ...p, date: d }));
    setXfer((p) => ({ ...p, date: d }));
  }, [monthYM]);

  const resetNew = () =>
    setNewEntry({
      date: clampTodayToYM(monthYM),
      label: "",
      category_id: cats[0]?.id || null,
      side: "credit",
      amount: "",
      notes: "",
    });

  const createEntry = async () => {
    const amountRaw = (newEntry.amount || "").replace(",", ".");
    const cents = Math.round(Number(amountRaw) * 100) || 0;
    if (!newEntry.category_id || !newEntry.label || cents <= 0) return;
    const signed = newEntry.side === "debit" ? -cents : cents;

    const payload = {
      date: newEntry.date,
      label: newEntry.label,
      amount_cents: signed,
      category_id: Number(newEntry.category_id),
      notes: newEntry.notes || "",
    };
    const r = await fetch(`/api/accounting/entries`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    if (r.ok) {
      await loadCompta();
      await loadLedger();
      resetNew();
    }
  };

  const deleteEntry = async (id) => {
    const r = await fetch(`/api/accounting/entries/${id}`, { method: "DELETE" });
    if (r.ok) {
      await loadCompta();
      await loadLedger();
    }
  };

  // Transfer state (default date = today clamped)
  const [xfer, setXfer] = useState({
    date: clampTodayToYM(monthYM),
    label: "",
    from_category_id: null,
    to_category_id: null,
    amount: "", // €
    notes: "",
  });

  const onChangeXfer = useCallback((field, value) => {
    setXfer((p) => ({ ...p, [field]: value ?? "" }));
  }, []);

  const createTransfer = async () => {
    const amount_cents =
      Math.round(Number((xfer.amount || "0").replace(",", ".")) * 100) || 0;
    if (
      !xfer.from_category_id ||
      !xfer.to_category_id ||
      xfer.from_category_id === xfer.to_category_id
    )
      return;
    if (amount_cents <= 0) return;

    const payload = {
      date: xfer.date,
      label: xfer.label || "Virement",
      from_category_id: Number(xfer.from_category_id),
      to_category_id: Number(xfer.to_category_id),
      amount_cents,
      notes: xfer.notes || "",
    };

    const r = await fetch(`/api/accounting/transfer`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });

    if (r.ok) {
      await loadCompta();
      await loadLedger();
      setXfer({
        date: clampTodayToYM(monthYM),
        label: "",
        from_category_id: null,
        to_category_id: null,
        amount: "",
        notes: "",
      });
    }
  };

  /* ======= RENDER ======= */
  return (
    <div className="container my-3">
      {/* Top: Month + Tabs */}
      <div className="d-flex align-items-center justify-content-between mb-2">
        <ul className="nav nav-tabs">
          <li className="nav-item">
            <button
              className={`nav-link ${activeTab === "caisse" ? "active" : ""}`}
              onClick={() => setActiveTab("caisse")}
            >
              Caisse
            </button>
          </li>
          <li className="nav-item">
            <button
              className={`nav-link ${activeTab === "compta" ? "active" : ""}`}
              onClick={() => setActiveTab("compta")}
            >
              Comptabilité
            </button>
          </li>
          <li className="nav-item">
            <button
              className={`nav-link ${activeTab === "rapport" ? "active" : ""}`}
              onClick={() => setActiveTab("rapport")}
            >
              Rapport
            </button>
          </li>
          <li className="nav-item">
            <button
              className={`nav-link ${activeTab === "clients" ? "active" : ""}`}
              onClick={() => setActiveTab("clients")}
            >
              Clients
            </button>
          </li>
        </ul>

        <div className="d-flex gap-2 align-items-center">
          <input
            type="month"
            className="form-control"
            value={monthYM}
            onChange={(e) => setMonthYM(e.target.value)}
          />
          <button
            className="btn btn-outline-secondary"
            onClick={() => setMonthYM(thisMonthYM())}
          >
            Mois courant
          </button>
          <button
            className="btn btn-outline-secondary"
            onClick={() => setMonthYM(prevOfYM(monthYM))}
          >
            Mois précédent
          </button>
        </div>
      </div>

      {err && <div className="alert alert-danger py-2">{err}</div>}
      {(loading || loadingCompta || loadingLedger) && (
        <div className="text-muted small mb-2">Chargement…</div>
      )}

      {/* ======= TAB: CAISSE ======= */}
      {activeTab === "caisse" && (
        <div className="row g-3">
          {/* left */}
          <div className="col-lg-7">
            <div className="card shadow-sm">
              <div className="card-header d-flex justify-content-between align-items-center">
                <strong>Billets & pièces</strong>
                <span className="ms-2 badge text-bg-light">
                  {prevYM} (lecture seule) · {monthYM} (saisie)
                </span>
              </div>
              <div className="card-body">
                <TableBlock
                  denoms={NOTE_DENOMS}
                  prevYM={prevYM}
                  monthYM={monthYM}
                  qtyPrev={qtyPrev}
                  qtyCurr={qtyCurr}
                  onChangeQty={onChangeQty}
                  fondQty={fondBreakdown.countByDenom}
                  onChangeFondQty={onChangeFondQty}
                />
                <TableBlock
                  denoms={COIN_DENOMS}
                  prevYM={prevYM}
                  monthYM={monthYM}
                  qtyPrev={qtyPrev}
                  qtyCurr={qtyCurr}
                  onChangeQty={onChangeQty}
                  fondQty={fondBreakdown.countByDenom}
                  onChangeFondQty={onChangeFondQty}
                />

                <div className="row">
                  <div className="col-6 text-end fw-semibold">Total fin {prevYM}:</div>
                  <div className="col-6 text-end">{fmtMoney(countedPrev)}</div>
                </div>
                <div className="row">
                  <div className="col-6 text-end fw-semibold">
                    Total saisi {monthYM}:
                  </div>
                  <div className="col-6 text-end">{fmtMoney(countedCurr)}</div>
                </div>

                <hr className="my-3" />
                <div className="p-3 border rounded bg-light">
                  <div className="fw-semibold mb-2">Fond de caisse automatique</div>
                  <div className="row g-2 align-items-end">
                    <div className="col-12 col-md-6 col-lg-3">
                      <label className="form-label small">Montant à transférer</label>
                      <div className="input-group input-group-sm">
                        <input
                          type="text"
                          className="form-control text-end"
                          value={centsToEuroInput(fondTransferCents)}
                          readOnly
                        />
                        <span className="input-group-text">€</span>
                      </div>
                      <div className="form-text">
                        Calculé = Saisie (€) − Fond (€). Ajustez la colonne « Fond # » pour modifier cette valeur.
                      </div>
                    </div>
                    <div className="col-12 col-md-6 col-lg-3">
                      <label className="form-label small">Cible auto (préférence)</label>
                      <div className="input-group input-group-sm">
                        <input
                          type="text"
                          inputMode="decimal"
                          className="form-control text-end"
                          value={fondPreferenceInput}
                          onChange={handleFondPreferenceChange}
                          onBlur={handleFondPreferenceBlur}
                          placeholder="200,00"
                        />
                        <span className="input-group-text">€</span>
                      </div>
                      <div className="form-text">
                        Utilisé pour le calcul automatique du fond à conserver.
                      </div>
                    </div>
                    <div className="col-12 col-md-6 col-lg-3">
                      <label className="form-label small">Catégorie source</label>
                      <select
                        className="form-select form-select-sm"
                        value={fondFromId ?? ""}
                        onChange={handleFondFromChange}
                        disabled={!fondCategoryOptions.length}
                      >
                        <option value="">— catégorie source —</option>
                        {fondCategoryOptions.map((c) => (
                          <option key={c.id} value={c.id}>
                            {c.name}
                          </option>
                        ))}
                      </select>
                    </div>
                    <div className="col-12 col-md-6 col-lg-3">
                      <label className="form-label small">Catégorie destination</label>
                      <select
                        className="form-select form-select-sm"
                        value={fondToId ?? ""}
                        onChange={handleFondToChange}
                        disabled={!fondCategoryOptions.length}
                      >
                        <option value="">— catégorie destination —</option>
                        {fondCategoryOptions.map((c) => (
                          <option key={c.id} value={c.id}>
                            {c.name}
                          </option>
                        ))}
                      </select>
                    </div>
                  </div>
                  <div className="d-flex justify-content-between align-items-center mt-3">
                    <span className="small text-muted">
                      Cible automatique actuelle : {fmtMoney(fondPreferenceCents)}
                    </span>
                    <div className="d-flex gap-2">
                      <button
                        type="button"
                        className="btn btn-link btn-sm px-0"
                        onClick={() => {
                          setFondPreferenceCents(fondAllocatedCents);
                          setFondQtyManual(false);
                        }}
                        disabled={fondAllocatedCents === fondPreferenceCents}
                      >
                        Utiliser ce fond comme cible auto
                      </button>
                      <button
                        type="button"
                        className="btn btn-link btn-sm px-0"
                        onClick={() => setFondQtyManual(false)}
                        disabled={!fondQtyManual}
                      >
                        Revenir au calcul auto
                      </button>
                    </div>
                  </div>
                  <div className="small mt-2 text-muted">
                    {fondCaisseCents > 0 ? (
                      <>
                        Lors de l&apos;enregistrement, un virement ajustera automatiquement la caisse pour conserver les montants sélectionnés ({fmtMoney(fondAllocatedCents)}). Ajustez la colonne « Fond # » ci-dessus pour modifier cette sélection.
                      </>
                    ) : (
                      "Aucun fond n'est conservé : l'intégralité du comptage sera transférée."
                    )}
                  </div>
                  {!fondConfigValid && fondCaisseCents > 0 && (
                    <div className="text-danger small mt-2">
                      Choisissez deux catégories distinctes pour ce virement automatique.
                    </div>
                  )}
                  {fondExceedsTotal && (
                    <div className="text-danger small mt-2">
                      Le fond sélectionné dépasse le montant saisi ({fmtMoney(countedCurr)}).
                    </div>
                  )}
                  {fondCaisseCents > 0 && fondConfigValid && (
                    <div className="small mt-2">
                      <strong>Fond sélectionné :</strong> {fmtMoney(fondAllocatedCents)} ({fondAutoLabel})
                    </div>
                  )}
                  {fondCaisseCents > 0 && fondConfigValid && (
                    <div className="small mt-1">
                      <strong>Virement prévu :</strong> {fondAutoLabel} · {fmtMoney(amountToTransfer)} à déposer
                    </div>
                  )}
                  {fondExcess > 0 && fondConfigValid && (
                    <div className="text-warning small mt-2">
                      Le fond sélectionné dépasse la cible automatique de {fmtMoney(fondExcess)}.
                    </div>
                  )}
                  {fondShortfall > 0 && fondConfigValid && (
                    <div className="text-warning small mt-2">
                      Il manque {fmtMoney(fondShortfall)} pour atteindre la cible automatique ({fmtMoney(fondDesiredCents)}).
                    </div>
                  )}
                </div>

                <div className="mt-3">
                  <label className="form-label small">Notes</label>
                  <textarea
                    className="form-control"
                    rows={2}
                    value={notes}
                    onChange={(e) => setNotes(e.target.value)}
                  />
                  <div className="form-text">
                    Le comptage sera enregistré au <strong>{effEnd}</strong> (fin de mois).
                  </div>
                </div>
              </div>
              <div className="card-footer d-flex justify-content-end gap-2">
                <button className="btn btn-outline-secondary" onClick={clearCount}>
                  Vider (mois courant)
                </button>
                <button className="btn btn-primary" onClick={save}>
                  Enregistrer le comptage du mois
                </button>
              </div>
            </div>
          </div>

          {/* right summary */}
          <div className="col-lg-5">
            <div className="card shadow-sm">
              <div className="card-header">
                <strong>
                  Comparatif du {effStart} au {effEnd}
                </strong>
              </div>
              <div className="card-body">
                <div className="d-flex justify-content-between">
                  <span>Recettes totales (toutes méthodes)</span>
                  <strong>
                    {fmtMoney(summary?.totals?.orders_total_cents || 0)}
                  </strong>
                </div>
                <div className="d-flex justify-content-between">
                  <span>Recettes en espèce (système)</span>
                  <strong>{fmtMoney(summary?.totals?.cash_cents || 0)}</strong>
                </div>
                <div className="d-flex justify-content-between">
                  <span>CB / autres (système)</span>
                  <strong>
                    {fmtMoney(
                      (summary?.totals?.card_cents || 0) +
                        (summary?.totals?.other_cents || 0)
                    )}
                  </strong>
                </div>

                <hr />
                <div className="d-flex justify-content-between">
                  <span>Comptage fin mois précédent ({prevYM})</span>
                  <strong>{fmtMoney(countedPrev)}</strong>
                </div>
                <div className="d-flex justify-content-between">
                  <span>Comptage fin mois courant (préc.+saisi)</span>
                  <strong>{fmtMoney(countedTotal)}</strong>
                </div>
                <div className="d-flex justify-content-between">
                  <span>Fond sélectionné (total)</span>
                  <strong>{fmtMoney(fondAllocatedCents)}</strong>
                </div>
                <div className="d-flex justify-content-between text-muted">
                  <span>Cible auto actuelle</span>
                  <strong>{fmtMoney(Math.min(fondPreferenceCents, countedCurr))}</strong>
                </div>
                <div className="d-flex justify-content-between">
                  <span>Montant à transférer (saisie − fond)</span>
                  <strong>{fmtMoney(fondTransferCents)}</strong>
                </div>
                <div className="d-flex justify-content-between">
                  <span>À déposer (comptage − fond)</span>
                  <strong>{fmtMoney(amountToTransfer)}</strong>
                </div>

                <div
                  className={`d-flex justify-content-between fs-5 mt-2 ${
                    diffCents === 0
                      ? "text-success"
                      : diffCents > 0
                      ? "text-warning"
                      : "text-danger"
                  }`}
                >
                  <span>
                    Écart = Fin courant − (Fin précédent + Espèces du mois)
                  </span>
                  <strong>{fmtMoney(diffCents)}</strong>
                </div>
                <div className="form-text mt-2">
                  {fondCaisseCents > 0 && fondConfigValid ? (
                    <>
                      Virement automatique : {fondAutoLabel}. Montant estimé à déposer&nbsp;: {fmtMoney(Math.max(0, amountToTransfer))}.
                    </>
                  ) : (
                    "Aucun virement automatique configuré pour le fond de caisse."
                  )}
                </div>
                {fondShortfall > 0 && fondConfigValid && (
                  <div className="text-warning small mt-2">
                    Il manque {fmtMoney(fondShortfall)} pour atteindre la cible automatique ({fmtMoney(fondDesiredCents)}).
                  </div>
                )}
                {fondExcess > 0 && fondConfigValid && (
                  <div className="text-warning small mt-2">
                    Le fond sélectionné dépasse la cible automatique de {fmtMoney(fondExcess)}.
                  </div>
                )}
                {fondExceedsTotal && (
                  <div className="text-danger small mt-2">
                    Le fond sélectionné dépasse le montant saisi ({fmtMoney(countedCurr)}).
                  </div>
                )}

                {summary?.last_cash_count && (
                  <div className="mt-3 small text-muted">
                    Dernier enregistrement:&nbsp;
                    {summary.last_cash_count?.created_at
                      ? new Date(summary.last_cash_count.created_at).toLocaleString(
                          "fr-FR"
                        )
                      : "—"}
                    &nbsp;· caisse {fmtMoney(summary.last_cash_count?.total_cents || 0)}
                    &nbsp;· écart {fmtMoney(summary.last_cash_count?.diff_cents || 0)}
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* ======= TAB: COMPTABILITÉ ======= */}
      {activeTab === "compta" && (
        <div className="row g-3">
          {/* LEFT: Virement on top, Journal below */}
          <div className="col-lg-8">
            <TransferCard
              monthYM={monthYM}
              xfer={xfer}
              bankCats={bankCats}
              expenseCats={expenseCats}
              onChangeXfer={onChangeXfer}
              onCreateTransfer={createTransfer}
            />

            <JournalCard
              monthYM={monthYM}
              entries={entries}
              cats={cats}
              newEntry={newEntry}
              onChangeNewEntry={onChangeNewEntry}
              createEntry={createEntry}
              deleteEntry={deleteEntry}
            />
          </div>

          {/* RIGHT: Résumé + Grand livre */}
          <div className="col-lg-4">
            <div className="card shadow-sm mb-3">
              <div className="card-header">
                <strong>Résumé {monthYM}</strong>
              </div>
              <div className="card-body">
                <div className="d-flex justify-content-between">
                  <span>Total Banque</span>
                  <strong>{fmtMoney(totals.bank || 0)}</strong>
                </div>
                <div className="d-flex justify-content-between">
                  <span>Total Dépenses</span>
                  <strong>{fmtMoney(totals.expense || 0)}</strong>
                </div>
                <hr />
                <div className="d-flex justify-content-between">
                  <span>Solde (Banque − Dépenses)</span>
                  <strong>
                    {fmtMoney((totals.bank || 0) - (totals.expense || 0))}
                  </strong>
                </div>
                <div className="form-text mt-2">
                  Les catégories sont modifiables dans la base.
                </div>
              </div>
            </div>

            {ledger && <LedgerTable ledger={ledger} monthYM={monthYM} />}
          </div>
        </div>
      )}

      {/* ======= TAB: RAPPORT ======= */}
      {activeTab === "rapport" && (
        <div className="row g-3">
          {reportErr ? (
            <div className="col-12">
              <div className="alert alert-danger py-2 mb-0">{reportErr}</div>
            </div>
          ) : (
            <>
              <div className="col-lg-4">
                <div className="card shadow-sm h-100">
                  <div className="card-header">
                    <strong>Vue d&apos;ensemble {monthYM}</strong>
                  </div>
                  <div className="card-body">
                    <div className="d-flex justify-content-between">
                      <span>Recettes (clients)</span>
                      <strong>{fmtMoney(reportIncomeTotal)}</strong>
                    </div>
                    <div className="d-flex justify-content-between">
                      <span>Dépenses</span>
                      <strong>{fmtMoney(reportExpenseTotal)}</strong>
                    </div>
                    <hr />
                    <div className={`d-flex justify-content-between ${reportNetClass}`}>
                      <span>Solde net</span>
                      <strong>{fmtMoney(reportNetTotal)}</strong>
                    </div>
                    {report?.period && (
                      <div className="form-text mt-2">
                        Période du {report.period.start} au {report.period.end}.
                      </div>
                    )}
                    {loadingReport && (
                      <div className="text-muted small mt-3">Mise à jour…</div>
                    )}
                  </div>
                </div>
              </div>

              <div className="col-lg-8">
                <div className="card shadow-sm mb-3">
                  <div className="card-header d-flex justify-content-between align-items-center">
                    <strong>Recettes — commandes clients</strong>
                    <span className="badge text-bg-light">{reportIncomes.length}</span>
                  </div>
                  <div className="card-body">
                    <div className="table-responsive">
                      <table className="table table-sm align-middle">
                        <thead>
                          <tr>
                            <th style={{ width: 150 }}>Date</th>
                            <th>Client</th>
                            <th style={{ width: 110 }}>Commande</th>
                            <th className="text-end" style={{ width: 140 }}>
                              Montant
                            </th>
                            <th style={{ width: 120 }}>Paiement</th>
                            <th>Note</th>
                          </tr>
                        </thead>
                        <tbody>
                          {reportIncomes.length ? (
                            reportIncomes.map((inc) => {
                              const nameFallback = `${inc.customer?.last_name || ""} ${inc.customer?.first_name || ""}`.trim();
                              return (
                                <tr key={inc.order_id}>
                                  <td>{fmtDateTimeFr(inc.encaisse_at)}</td>
                                  <td>{inc.customer?.full_name || nameFallback || "—"}</td>
                                  <td>#{inc.order_id}</td>
                                  <td className="text-end">{fmtMoney(inc.total_cents || 0)}</td>
                                  <td>{inc.payment_method || "—"}</td>
                                  <td
                                    className="text-truncate"
                                    style={{ maxWidth: 220 }}
                                    title={inc.note || ""}
                                  >
                                    {inc.note || "—"}
                                  </td>
                                </tr>
                              );
                            })
                          ) : (
                            <tr>
                              <td colSpan={6} className="text-muted">
                                Aucune commande encaissée avec client sur ce mois.
                              </td>
                            </tr>
                          )}
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>

                <div className="card shadow-sm">
                  <div className="card-header d-flex justify-content-between align-items-center">
                    <strong>Dépenses du mois</strong>
                    <span className="badge text-bg-light">{reportExpenses.length}</span>
                  </div>
                  <div className="card-body">
                    <div className="table-responsive">
                      <table className="table table-sm align-middle">
                        <thead>
                          <tr>
                            <th style={{ width: 120 }}>Date</th>
                            <th>Libellé</th>
                            <th style={{ width: 200 }}>Catégorie</th>
                            <th className="text-end" style={{ width: 140 }}>
                              Montant
                            </th>
                            <th>Notes</th>
                          </tr>
                        </thead>
                        <tbody>
                          {reportExpenses.length ? (
                            reportExpenses.map((exp) => (
                              <tr key={exp.id}>
                                <td>{fmtDateFr(exp.date)}</td>
                                <td>{exp.label || "—"}</td>
                                <td>{exp.category?.name || "—"}</td>
                                <td className="text-end">{fmtMoney(exp.amount_cents || 0)}</td>
                                <td
                                  className="text-truncate"
                                  style={{ maxWidth: 240 }}
                                  title={exp.notes || ""}
                                >
                                  {exp.notes || "—"}
                                </td>
                              </tr>
                            ))
                          ) : (
                            <tr>
                              <td colSpan={5} className="text-muted">
                                Aucune dépense saisie sur ce mois.
                              </td>
                            </tr>
                          )}
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            </>
          )}
        </div>
      )}

      {activeTab === "clients" && (
        <div className="row g-3">
          <div className="col-12">
            <div className="card shadow-sm">
              <div className="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <strong>Clients — recettes par année</strong>
                <div className="d-flex flex-wrap gap-2 align-items-center">
                  <div className="d-flex align-items-center gap-2">
                    <label className="form-label small mb-0" htmlFor="customers-years-select">
                      Nombre d&apos;années
                    </label>
                    <select
                      id="customers-years-select"
                      className="form-select form-select-sm"
                      value={customersYears}
                      onChange={(e) => {
                        const next = Number(e.target.value) || 1;
                        setCustomersYears(Math.max(1, Math.min(10, next)));
                      }}
                    >
                      {[1, 2, 3, 4, 5, 6, 7, 8, 9, 10].map((val) => (
                        <option key={val} value={val}>
                          {val}
                        </option>
                      ))}
                    </select>
                  </div>
                  <div className="d-flex align-items-center gap-2">
                    <label className="form-label small mb-0" htmlFor="customers-limit-select">
                      Top clients
                    </label>
                    <select
                      id="customers-limit-select"
                      className="form-select form-select-sm"
                      value={customersLimit}
                      onChange={(e) => {
                        const next = Number(e.target.value) || 5;
                        setCustomersLimit(Math.max(1, Math.min(50, next)));
                      }}
                    >
                      {[5, 10, 15, 20, 25, 30, 40, 50].map((val) => (
                        <option key={val} value={val}>
                          {val}
                        </option>
                      ))}
                    </select>
                  </div>
                </div>
              </div>
              <div className="card-body">
                {customersErr && (
                  <div className="alert alert-danger py-2">{customersErr}</div>
                )}
                <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                  <div className="small text-muted">
                    {customersYearRangeLabel
                      ? `Période analysée : ${customersYearRangeLabel}`
                      : "Aucune donnée disponible pour la période sélectionnée."}
                  </div>
                  {loadingCustomers && (
                    <div className="text-muted small">Chargement…</div>
                  )}
                </div>
                <div
                  ref={customersChartRef}
                  className="w-100"
                  style={{ minHeight: 420 }}
                />

                {!loadingCustomers && !customersErr && !customersList.length && (
                  <div className="text-muted small mt-2">
                    Aucune commande client enregistrée sur cette période.
                  </div>
                )}

                <div className="d-flex flex-wrap gap-3 mt-3">
                  {customersYearsList.map((year, idx) => (
                    <span
                      key={year}
                      className="badge"
                      style={{
                        backgroundColor: YEAR_COLORS[idx % YEAR_COLORS.length],
                        color: "#fff",
                      }}
                    >
                      {year}
                    </span>
                  ))}
                </div>

                <div className="table-responsive mt-3">
                  <table className="table table-sm align-middle">
                    <thead>
                      <tr>
                        <th>Client</th>
                        {customersYearsList.map((year) => (
                          <th key={year} className="text-end">
                            {year}
                          </th>
                        ))}
                        <th className="text-end">Total</th>
                        <th className="text-end">Commandes</th>
                      </tr>
                    </thead>
                    <tbody>
                      {customersList.length ? (
                        customersList.map((cust) => {
                          const name = cust.name || `Client #${cust.customer_id}`;
                          const statusLabel = customerStatusLabel(cust.status);
                          return (
                            <tr key={cust.customer_id}>
                              <td>
                                <div>{name}</div>
                                <div className="small text-muted">{statusLabel}</div>
                              </td>
                              {customersYearsList.map((year) => {
                                const key = String(year);
                                const cents = Number(cust.year_totals?.[key] || 0);
                                return (
                                  <td key={year} className="text-end">
                                    {fmtMoney(cents)}
                                  </td>
                                );
                              })}
                              <td className="text-end">{fmtMoney(cust.total_cents || 0)}</td>
                              <td className="text-end">{cust.total_orders || 0}</td>
                            </tr>
                          );
                        })
                      ) : (
                        <tr>
                          <td colSpan={customersYearsList.length + 3} className="text-muted">
                            Aucune donnée client pour la période demandée.
                          </td>
                        </tr>
                      )}
                    </tbody>
                    {customersList.length ? (
                      <tfoot>
                        <tr>
                          <th>Total annuel (tous clients)</th>
                          {customersYearsList.map((year) => {
                            const info = customersPerYearTotals.find((item) => Number(item.year) === year);
                            return (
                              <th key={year} className="text-end">
                                {fmtMoney(info?.total_cents || 0)}
                              </th>
                            );
                          })}
                          <th className="text-end">—</th>
                          <th className="text-end">
                            {customersPerYearTotals.reduce((acc, item) => acc + (item?.orders || 0), 0)}
                          </th>
                        </tr>
                      </tfoot>
                    ) : null}
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
