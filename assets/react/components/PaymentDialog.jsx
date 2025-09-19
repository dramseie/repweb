import React, { useEffect, useMemo, useState } from "react";

/** Parse a SQL/ISO-like local timestamp into a local Date.
 * Supports "YYYY-MM-DD HH:MM:SS", "YYYY-MM-DDTHH:MM", "YYYY-MM-DDTHH:MM:SS".
 * Falls back to new Date(s) if pattern doesn't match.
 */
function toLocalDate(s) {
  if (!s) return null;
  if (s instanceof Date) return s;
  const str = String(s);
  const m = str.match(
    /^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})(?::(\d{2}))?$/
  );
  if (m) {
    const [_, Y, Mo, D, H, Mi, S] = m;
    return new Date(+Y, +Mo - 1, +D, +H, +Mi, +(S || 0)); // LOCAL time
  }
  const d = new Date(str);
  return isNaN(d) ? null : d; // last resort
}

const pad = (n) => String(n).padStart(2, "0");
const asCents = (v) => Math.round(Number(v || 0) * 100);

export default function PaymentDialog({
  show,
  onClose,
  onConfirm,
  amountDueCents = 0,
  rendezVousAtIso = null, // e.g. "2025-09-10T13:00:00"
}) {
  // Reduction state
  const [reducMode, setReducMode] = useState("amount"); // 'amount' | 'percent'
  const [reducValue, setReducValue] = useState("");

  // Money fields
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
    const rec = asCents(amountReceived);
    return Math.max(0, rec - dueAfterReducCents);
  }, [amountReceived, dueAfterReducCents]);

  const dueAfterReduc = (dueAfterReducCents / 100).toFixed(2);
  const tipPreview = (tipCents / 100).toFixed(2);

  // Format for <input type="datetime-local">
  const encaisseAtLocal = useMemo(() => {
    const d = toLocalDate(encaisseAt) || new Date();
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(
      d.getHours()
    )}:${pad(d.getMinutes())}`;
  }, [encaisseAt]);

  // On open: set "now" and default Montant reçu to due-after-reduction
  useEffect(() => {
    if (!show) return;
    const now = new Date();
    setEncaisseAt(now);
    setAmountReceived((dueAfterReducCents / 100).toFixed(2));
  }, [show, dueAfterReducCents]);

  // Robust elapsed computation (LOCAL) — recompute whenever input or RDV changes
  const recomputeElapsed = () => {
    const start = toLocalDate(rendezVousAtIso);  // RDV start (local)
    const end = toLocalDate(encaisseAt) || new Date();
    if (!start || !end) {
      setElapsedMinutes(0);
      return;
    }
    const diffMs = end.getTime() - start.getTime();
    setElapsedMinutes(Math.max(0, Math.floor(diffMs / 60000))); // e.g. 13:00 -> 13:59 = 59
  };

  useEffect(() => {
    if (!show) return;
    recomputeElapsed();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [show, rendezVousAtIso, encaisseAt]);

  const handleEncaisseAtChange = (e) => {
    const d = toLocalDate(e.target.value);
    if (d) setEncaisseAt(d);
  };

  const handleConfirm = async () => {
    await onConfirm?.({
      amountDueCents,
      reductionCents,
      amountReceivedCents: asCents(amountReceived),
      tipCents,
      encaisseAtIso: (toLocalDate(encaisseAt) || new Date()).toISOString(),
      elapsedMinutes: Number(elapsedMinutes) || 0,
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

              {/* Montant reçu */}
              <div className="mb-2">
                <label className="form-label">Montant reçu</label>
                <input
                  className="form-control"
                  type="number"
                  step="1.00"
                  min="0"
                  value={amountReceived}
                  onChange={(e) => setAmountReceived(e.target.value)}
                />
                <div className="form-text">
                  Pourboire calculé automatiquement: <strong>{tipPreview}</strong>
                </div>
              </div>

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
