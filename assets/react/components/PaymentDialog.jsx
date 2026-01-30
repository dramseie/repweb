import React, { useEffect, useMemo, useRef, useState } from "react";

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
  orderId = null,
}) {


  // Reduction state
  const [reducMode, setReducMode] = useState("amount"); // 'amount' | 'percent'
  const [reducValue, setReducValue] = useState("");

  // Method + money
  const [method, setMethod] = useState("cash");         // cash|card|twint|cheque|voucher|transfer|loyalty|other
  const [amountReceived, setAmountReceived] = useState("");

  // Invoice export
  const [invoiceLoading, setInvoiceLoading] = useState(false);
  const [invoiceError, setInvoiceError] = useState("");

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
    if (method === 'cash') {
      setAmountReceived((dueAfterReducCents / 100).toFixed(2));
    } else if (method === 'loyalty') {
      setAmountReceived('0');
    }
    setInvoiceError("");
    setInvoiceLoading(false);
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

  const previousReduction = useRef(null);

  useEffect(() => {
    if (method === 'loyalty') {
      if (!previousReduction.current) {
        previousReduction.current = { mode: reducMode, value: reducValue };
      }
      if (reducMode !== 'percent') setReducMode('percent');
      if (reducValue !== '100') setReducValue('100');
      setAmountReceived('0');
    } else if (previousReduction.current) {
      const { mode, value } = previousReduction.current;
      previousReduction.current = null;
      setReducMode(mode);
      setReducValue(value);
    }
  }, [method, reducMode, reducValue]);

  const handleMethodChange = (e) => {
    const value = e.target.value;
    setMethod(value);
    if (value !== 'cash') {
      setAmountReceived('0');
    }
  };

  const loyaltySelected = method === 'loyalty';

const handleConfirm = async () => {
  const payAmountCents = dueAfterReducCents;
  const amountReceivedCents = method === 'cash' ? asCents(amountReceived) : payAmountCents;
  
  const payments = [{ method, amount_cents: payAmountCents }];
  if (reductionCents > 0) {
    payments.push({ method: 'reduction', amount_cents: -reductionCents });
  }

  await onConfirm?.({
    orderId,
    amountDueCents,
    reductionCents,
    amountReceivedCents,
    tipCents,
    encaisseAtIso: (toLocalDate(encaisseAt) || new Date()).toISOString(),
    elapsedMinutes: Number(elapsedMinutes) || 0,
    method,
    payments,
  });
  onClose?.();
};

  const handleInvoiceDownload = async () => {
    if (!orderId || invoiceLoading) {
      return;
    }
    setInvoiceError("");
    setInvoiceLoading(true);
    let blobUrl = null;
    try {
      const res = await fetch(`/api/pos/orders/${orderId}/invoice.pdf`, {
        headers: { Accept: 'application/pdf' },
      });
      if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
      }
      const blob = await res.blob();
      blobUrl = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = blobUrl;
      link.download = `facture-${orderId}.pdf`;
      document.body.appendChild(link);
      link.click();
      link.remove();
    } catch (err) {
      console.error('Invoice download failed', err);
      const message = err?.message || 'Impossible de generer la facture';
      setInvoiceError(message);
    } finally {
      if (blobUrl) {
        URL.revokeObjectURL(blobUrl);
      }
      setInvoiceLoading(false);
    }
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
                      disabled={loyaltySelected}
                    >€</button>
                    <button
                      type="button"
                      className={`btn btn-outline-secondary ${reducMode === 'percent' ? 'active' : ''}`}
                      onClick={() => setReducMode('percent')}
                      disabled={loyaltySelected}
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
                    disabled={loyaltySelected}
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
                <select className="form-select" value={method} onChange={handleMethodChange}>
                  <option value="cash">Espèces</option>
                  <option value="card">Carte</option>
                  <option value="twint">TWINT</option>
                  <option value="cheque">Chèque</option>
                  <option value="voucher">Bon / chèque-cadeau</option>
                  <option value="transfer">Virement</option>
                  <option value="loyalty">Carte de fidélité</option>
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

            <div className="modal-footer flex-column align-items-stretch">
              {invoiceError && (
                <div className="alert alert-warning py-2 small w-100 mb-2">
                  {invoiceError}
                </div>
              )}
              <div className="d-flex w-100 flex-wrap align-items-center gap-2">
                <button
                  type="button"
                  className="btn btn-outline-secondary"
                  onClick={handleInvoiceDownload}
                  disabled={!orderId || invoiceLoading}
                >
                  {invoiceLoading ? 'Generation...' : 'Facture PDF'}
                </button>
                <div className="ms-auto d-flex gap-2">
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
        </div>
      </div>
      <div className="modal-backdrop fade show" style={{ zIndex: 1040 }} onClick={onClose} />
    </>
  );
}
