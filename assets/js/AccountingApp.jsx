// assets/js/AccountingApp.jsx
import React, { useEffect, useMemo, useState, useCallback } from "react";

/* ——— helpers ——— */
const fmtMoney = (c) =>
  (c / 100).toLocaleString("fr-FR", { style: "currency", currency: "EUR" });

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

/* ======= PRESENTATIONAL SUBCOMPONENTS (top-level, stable identities) ======= */

const TableBlock = React.memo(function TableBlock({
  denoms,
  prevYM,
  monthYM,
  qtyPrev,
  qtyCurr,
  onChangeQty,
}) {
  return (
    <table className="table table-sm align-middle mb-4">
      <thead>
        <tr>
          <th style={{ width: 80 }}></th>
          <th colSpan={2} className="text-center">{prevYM} (lecture seule)</th>
          <th colSpan={2} className="text-center">{monthYM} (saisie)</th>
        </tr>
        <tr>
          <th className="text-muted small">
            {denoms === NOTE_DENOMS ? "Billets" : "Pièces"}
          </th>
          <th className="text-center small">#</th>
          <th className="text-end small">€</th>
          <th className="text-center small">#</th>
          <th className="text-end small">€</th>
        </tr>
      </thead>
      <tbody>
        {denoms.map((d) => {
          const prevCount = qtyPrev[d.c];
          const currCount = qtyCurr[d.c];
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
  const [qtyPrev, setQtyPrev] = useState(emptyQty()); // readonly
  const [qtyCurr, setQtyCurr] = useState(emptyQty()); // editable
  const [notes, setNotes] = useState("");
  const [prevLoaded, setPrevLoaded] = useState(false);
  const [monthLoaded, setMonthLoaded] = useState(false);

  const sumFromQty = (q) =>
    DENOMS.reduce((acc, d) => acc + d.c * (Number(q[d.c]) || 0), 0);
  const countedPrev = useMemo(() => sumFromQty(qtyPrev), [qtyPrev]);
  const countedCurr = useMemo(() => sumFromQty(qtyCurr), [qtyCurr]);
  const countedTotal = useMemo(
    () => countedPrev + countedCurr,
    [countedPrev, countedCurr]
  );

  const diffCents = useMemo(() => {
    const systemCash = summary?.totals?.cash_cents || 0;
    return countedTotal - (countedPrev + systemCash);
  }, [countedTotal, countedPrev, summary]);

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
    const breakdown =
      jj?.breakdown ||
      jj?.item?.breakdown ||
      jj?.item?.breakdown_json ||
      jj?.last_cash_count?.breakdown ||
      jj?.last_cash_count?.breakdown_json ||
      null;
    const nt = jj?.notes || jj?.item?.notes || jj?.last_cash_count?.notes || "";
    return { breakdown, notes: nt };
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
      }

      const currData = await fetchCashCountByMonth(monthYM);
      if (currData?.breakdown) {
        setQtyCurr(preloadQtyFromBreakdown(currData.breakdown));
        setNotes(currData.notes || "");
        setMonthLoaded(true);
      } else {
        setQtyCurr(emptyQty());
        setNotes("");
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

  const clearCount = () => {
    setQtyCurr(emptyQty());
    setNotes("");
    setMonthLoaded(false);
  };

  const save = async () => {
    setLoading(true);
    setErr(null);
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
  // Entries for month
  const [entries, setEntries] = useState([]);
  const [loadingCompta, setLoadingCompta] = useState(false);

  // Ledger (debit/credit per category)
  const [ledger, setLedger] = useState(null);
  const [loadingLedger, setLoadingLedger] = useState(false);

  const bankCats = useMemo(() => cats.filter((c) => c.kind === "bank"), [cats]);
  const expenseCats = useMemo(() => cats.filter((c) => c.kind === "expense"), [cats]);

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

  useEffect(() => {
    if (activeTab === "compta") {
      loadCompta();
      loadLedger();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [activeTab, monthYM]);

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
                />
                <TableBlock
                  denoms={COIN_DENOMS}
                  prevYM={prevYM}
                  monthYM={monthYM}
                  qtyPrev={qtyPrev}
                  qtyCurr={qtyCurr}
                  onChangeQty={onChangeQty}
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
    </div>
  );
}
