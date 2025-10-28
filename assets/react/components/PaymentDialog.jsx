import React, { useEffect, useMemo, useState } from "react";

/** Parse a SQL/ISO-like local timestamp into a local Date. */
function toLocalDate(s) {
  if (!s) return null;
  if (s instanceof Date) return s;
  const str = String(s);
  const m = str.match(/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})(?::(\d{2}))?$/);
  if (m) {
    const [_, Y, Mo, D, H, Mi, S] = m;
    return new Date(+Y, +Mo - 1, +D, +H, +Mi, +(S || 0)); // LOCAL time
  }
  const d = new Date(str);
  return isNaN(d) ? null : d;
}

const pad = (n) => String(n).padStart(2, "0");
/** Robust cents parser: handles "1.50" and "1,50" */
const asCents = (v) => {
  const n = parseFloat(String(v ?? 0).replace(',', '.'));
  return Math.round((isNaN(n) ? 0 : n) * 100);
};

export default function PaymentDialog({
  show,
  onClose,
  onConfirm,
  amountDueCents = 0,
  rendezVousAtIso = null, // e.g. "2025-09-10T13:00:00"
  elapsedMinutesInitial = null,  // NEW
  orderId = null, // ← ADD THIS LINE
}) {


  // Reduction state
  const [reducMode, setReducMode] = useState("amount"); // 'amount' | 'percent'
  const [reducValue, setReducValue] = useState("");

  // Method + money
  const [method, setMethod] = useState("cash");         // cash|card|twint|voucher|transfer|other
  const [amountReceived, setAmountReceived] = useState("");

  // Time fields
  const [encaisseAt, setEncaisseAt] = useState(() => new Date());
  const [elapsedMinutes, setElapsedMinutes] = useState(0);

  // Derived amounts
  const reductionCents = useMemo(() => {
    const base = amountDueCents;
    if (reducMode === "percent") {
      const pct = Math.max(0, Math.min(100, Number(reducValue || 0)));
      return Math.round(base * (pct / 100));
    }
    return Math.max(0, Math.min(base, asCents(reducValue)));
  }, [reducMode, reducValue, amountDueCents]);

  const dueAfterReducCents = Math.max(0, amountDueCents - reductionCents);
  const tipCents = useMemo(() => {
    if (method !== 'cash') return 0;
    const rec = asCents(amountReceived);
    return Math.max(0, rec - dueAfterReducCents);
  }, [amountReceived, dueAfterReducCents, method]);

  const dueAfterReduc = (dueAfterReducCents / 100).toFixed(2);
  const tipPreview = (tipCents / 100).toFixed(2);

  // Format for <input type="datetime-local">
  const encaisseAtLocal = useMemo(() => {
    const d = toLocalDate(encaisseAt) || new Date();
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(
      d.getHours()
    )}:${pad(d.getMinutes())}`;
  }, [encaisseAt]);

  // On open: set "now" and default Montant reçu to due-after-reduction (for cash)
  useEffect(() => {
    if (!show) return;
    const now = new Date();
    setEncaisseAt(now);
    if (method === 'cash') setAmountReceived((dueAfterReducCents / 100).toFixed(2));
  }, [show, dueAfterReducCents, method]);

  // Robust elapsed computation (LOCAL) — recompute whenever input or RDV changes
  const recomputeElapsed = () => {
    const start = toLocalDate(rendezVousAtIso);  // RDV start (local)
    const end = toLocalDate(encaisseAt) || new Date();
    if (!start || !end) {
      setElapsedMinutes(0);
      return;
    }
    const diffMs = end.getTime() - start.getTime();
    setElapsedMinutes(Math.max(0, Math.floor(diffMs / 60000)));
  };

	// On open: if a timer value is provided, use it; otherwise compute from RDV start → encaisseAt
	useEffect(() => {
	  if (!show) return;
	  if (elapsedMinutesInitial != null && Number.isFinite(Number(elapsedMinutesInitial))) {
		setElapsedMinutes(Math.max(0, Math.round(Number(elapsedMinutesInitial))));
	  } else {
		recomputeElapsed();
	  }
	  // We intentionally DO NOT depend on encaisseAt here so we don't overwrite manual edits
	  // eslint-disable-next-line react-hooks/exhaustive-deps
	}, [show, rendezVousAtIso, elapsedMinutesInitial]);

	// If no initial timer value, keep the field in sync when the encaisse time changes
	useEffect(() => {
	  if (!show) return;
	  if (elapsedMinutesInitial == null) recomputeElapsed();
	  // eslint-disable-next-line react-hooks/exhaustive-deps
	}, [encaisseAt]);


  const handleEncaisseAtChange = (e) => {
    const d = toLocalDate(e.target.value);
    if (d) setEncaisseAt(d);
  };

const handleConfirm = async () => {
  const payAmountCents = dueAfterReducCents;
  const amountReceivedCents = method === 'cash' ? asCents(amountReceived) : payAmountCents;
  
  await onConfirm?.({
    orderId,  // ← ADD THIS LINE (at the beginning of the object)
    amountDueCents,
    reductionCents,
    amountReceivedCents,
    tipCents,
    encaisseAtIso: (toLocalDate(encaisseAt) || new Date()).toISOString(),
    elapsedMinutes: Number(elapsedMinutes) || 0,
    method,
    payments: [{ method, amount_cents: payAmountCents }]
  });
  onClose?.();
};

  if (!show) return null;

  return (
    <>
      <div className="modal fade show" style={{ display: "block", zIndex: 1055 }} role="dialog" aria-modal="true">
        <div className="modal-dialog">
          <div className="modal-content">

            <div className="modal-header">
              <h5 className="modal-title">Encaisser</h5>
              <button type="button" className="btn-close" onClick={onClose} aria-label="Close" />
            </div>

            <div className="modal-body">
              {/* À payer */}
              <div className="mb-2">
                <label className="form-label small">À payer (avant réduction)</label>
                <input className="form-control" value={(amountDueCents / 100).toFixed(2)} disabled />
              </div>

              {/* Réduction */}
              <div className="mb-2">
                <div className="d-flex align-items-center justify-content-between">
                  <label className="form-label mb-0">Réduction</label>
                  <div className="btn-group btn-group-sm">
                    <button
                      type="button"
                      className={`btn btn-outline-secondary ${reducMode === 'amount' ? 'active' : ''}`}
                      onClick={() => setReducMode('amount')}
                    >€</button>
                    <button
                      type="button"
                      className={`btn btn-outline-secondary ${reducMode === 'percent' ? 'active' : ''}`}
                      onClick={() => setReducMode('percent')}
                    >%</button>
                  </div>
                </div>
                <div className="input-group">
                  <input
                    className="form-control"
                    type="number"
                    step="0.01"
                    min="0"
                    value={reducValue}
                    onChange={(e) => setReducValue(e.target.value)}
                    placeholder={reducMode === 'amount' ? 'Ex: 5,00' : 'Ex: 10'}
                  />
                  <span className="input-group-text">{reducMode === 'amount' ? '€' : '%'}</span>
                </div>
                <div className="form-text">
                  À payer après réduction: <strong>{dueAfterReduc}</strong>
                </div>
              </div>

              {/* Méthode de paiement */}
              <div className="mb-2">
                <label className="form-label">Méthode de paiement</label>
                <select className="form-select" value={method} onChange={(e)=>setMethod(e.target.value)}>
                  <option value="cash">Espèces</option>
                  <option value="card">Carte</option>
                  <option value="twint">TWINT</option>
                  <option value="voucher">Bon / chèque-cadeau</option>
                  <option value="transfer">Virement</option>
                  <option value="other">Autre</option>
                </select>
              </div>

              {/* Montant reçu (cash only) */}
              {method === 'cash' && (
                <div className="mb-2">
                  <label className="form-label">Montant reçu</label>
                  <input
                    className="form-control"
                    type="number"
                    step="0.01"
                    min="0"
                    value={amountReceived}
                    onChange={(e) => setAmountReceived(e.target.value)}
                  />
                  <div className="form-text">
                    Pourboire calculé automatiquement: <strong>{tipPreview}</strong>
                  </div>
                </div>
              )}

              {/* Time */}
              <div className="row g-2">
                <div className="col-md-7">
                  <label className="form-label">Moment d’encaissement</label>
                  <input
                    className="form-control"
                    type="datetime-local"
                    value={encaisseAtLocal}
                    onChange={handleEncaisseAtChange}
                    onBlur={recomputeElapsed}
                  />
                </div>
                <div className="col-md-5">
                  <label className="form-label">Temps écoulé (minutes)</label>
                  <input
                    className="form-control"
                    type="number"
                    min="0"
                    value={elapsedMinutes}
                    onChange={(e) => setElapsedMinutes(e.target.value)}
                  />
                  <div className="form-text">
                    Calculé depuis le rendez-vous, modifiable avant sauvegarde.
                  </div>
                </div>
              </div>
            </div>

            <div className="modal-footer">
              <button type="button" className="btn btn-outline-secondary" onClick={onClose}>
                Annuler
              </button>
              <button type="button" className="btn btn-primary" onClick={handleConfirm}>
                Confirmer l’encaissement
              </button>
            </div>

          </div>
        </div>
      </div>
      <div className="modal-backdrop fade show" style={{ zIndex: 1040 }} onClick={onClose} />
    </>
  );
}
