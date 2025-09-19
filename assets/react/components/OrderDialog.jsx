import React, { useMemo } from "react";

const fmtMoney = (c) => ((Number(c) || 0) / 100).toFixed(2) + " €";

function toDate(s) {
  if (!s) return null;
  const d = new Date(String(s).replace(" ", "T"));
  return isNaN(d) ? null : d;
}
const fmtDate = (s) => {
  const d = toDate(s);
  return d ? d.toLocaleDateString("fr-FR") : "—";
};
const fmtTime = (s) => {
  const d = toDate(s);
  return d ? d.toLocaleTimeString("fr-FR", { hour: "2-digit", minute: "2-digit" }) : "—";
};

export default function OrderDialog({ show, onClose, data }) {
  if (!show) return null;

  const order = data?.order || {};
  const items = data?.items || [];
  const customer = data?.customer || null;
  const appt = data?.appointment || null;
  const reals = useMemo(() => {
    try {
      const arr = JSON.parse(order?.realizations_json || "[]");
      return Array.isArray(arr) ? arr : [];
    } catch {
      return [];
    }
  }, [order]);

  const total = fmtMoney(order.total_cents);
  const totalTax = fmtMoney(order.total_tax_cents || 0);
  const amountReceived = order.amount_received_cents != null ? fmtMoney(order.amount_received_cents) : "—";
  const tip = order.tip_cents != null ? fmtMoney(order.tip_cents) : "—";
  const encaisseAt = order.encaisse_at ? `${fmtDate(order.encaisse_at)} ${fmtTime(order.encaisse_at)}` : "—";
  const elapsed = order.elapsed_minutes != null ? `${order.elapsed_minutes} min` : "—";

  return (
    <>
      <div className="modal fade show" style={{ display: "block", zIndex: 1055 }} role="dialog" aria-modal="true">
        <div className="modal-dialog modal-lg modal-dialog-scrollable">
          <div className="modal-content">

            <div className="modal-header">
              <h5 className="modal-title">Commande #{order.id}</h5>
              <button type="button" className="btn-close" onClick={onClose} aria-label="Close" />
            </div>

            <div className="modal-body">
              {/* Top: client + rendez-vous */}
              <div className="row g-3 mb-3">
                <div className="col-md-6">
                  <div className="card">
                    <div className="card-body">
                      <div className="fw-semibold mb-2">Client</div>
                      {customer ? (
                        <>
                          <div>{customer.first_name} {customer.last_name}</div>
                          <div className="text-muted small">{customer.phone || "—"} · {customer.email || "—"}</div>
                          <div className="mt-2">
                            <div className="small text-muted">Notes (publiques)</div>
                            <div>{customer.notes_public || "—"}</div>
                          </div>
                          <div className="mt-2">
                            <div className="small text-muted">Notes (privées)</div>
                            <div>{customer.notes_private || "—"}</div>
                          </div>
                        </>
                      ) : <div className="text-muted">—</div>}
                    </div>
                  </div>
                </div>

                <div className="col-md-6">
                  <div className="card">
                    <div className="card-body">
                      <div className="fw-semibold mb-2">Rendez-vous</div>
                      {appt ? (
                        <>
                          <div>Date: {fmtDate(appt.start_at)} · Heure: {fmtTime(appt.start_at)} → {fmtTime(appt.end_at)}</div>
                          <div className="small text-muted">Statut: {appt.status}</div>
                          <div className="mt-2">
                            <div className="small text-muted">Notes RDV (publiques)</div>
                            <div>{appt.notes_public || "—"}</div>
                          </div>
                          <div className="mt-2">
                            <div className="small text-muted">Notes RDV (privées)</div>
                            <div>{appt.notes_private || "—"}</div>
                          </div>
                        </>
                      ) : <div className="text-muted">—</div>}
                    </div>
                  </div>
                </div>
              </div>

              {/* Réalisations */}
              <div className="mb-3">
                <div className="fw-semibold mb-1">Réalisations</div>
                {reals.length ? (
                  <div className="d-flex flex-wrap gap-1">
                    {reals.map((r, i) => (
                      <span key={`${r.code || r.label || i}`} className="badge text-bg-secondary">
                        {r?.label || r?.code}
                      </span>
                    ))}
                  </div>
                ) : <div className="text-muted">—</div>}
              </div>

              {/* Items */}
              <div className="mb-3">
                <div className="fw-semibold mb-2">Articles & Services</div>
                <div className="table-responsive">
                  <table className="table table-sm align-middle">
                    <thead>
                      <tr>
                        <th style={{width: '45%'}}>Désignation</th>
                        <th className="text-center">Qté</th>
                        <th className="text-end">PU</th>
                        <th className="text-end">TVA</th>
                        <th className="text-end">Total</th>
                      </tr>
                    </thead>
                    <tbody>
                      {items.length ? items.map(it => (
                        <tr key={it.id}>
                          <td>{it.name_snapshot}</td>
                          <td className="text-center">{it.qty}</td>
                          <td className="text-end">{fmtMoney(it.unit_price_cents)}</td>
                          <td className="text-end">{Number(it.tax_rate || 0).toFixed(2)}%</td>
                          <td className="text-end">{fmtMoney(it.line_total_cents)}</td>
                        </tr>
                      )) : (
                        <tr><td colSpan={5} className="text-muted">—</td></tr>
                      )}
                    </tbody>
                    <tfoot>
                      <tr>
                        <th colSpan={4} className="text-end">TVA</th>
                        <th className="text-end">{totalTax}</th>
                      </tr>
                      <tr>
                        <th colSpan={4} className="text-end">Total</th>
                        <th className="text-end">{total}</th>
                      </tr>
                      <tr>
                        <th colSpan={4} className="text-end">Reçu</th>
                        <th className="text-end">{amountReceived}</th>
                      </tr>
                      <tr>
                        <th colSpan={4} className="text-end">Pourboire</th>
                        <th className="text-end">{tip}</th>
                      </tr>
                      <tr>
                        <th colSpan={4} className="text-end">Encaissement</th>
                        <th className="text-end">{encaisseAt}</th>
                      </tr>
                      <tr>
                        <th colSpan={4} className="text-end">Temps écoulé</th>
                        <th className="text-end">{elapsed}</th>
                      </tr>
                    </tfoot>
                  </table>
                </div>
              </div>

              {/* Notes commande */}
              <div>
                <div className="fw-semibold mb-1">Note (Commande)</div>
                <div>{order.note || "—"}</div>
              </div>
            </div>

            <div className="modal-footer">
              <button type="button" className="btn btn-outline-secondary" onClick={onClose}>
                Fermer
              </button>
            </div>

          </div>
        </div>
      </div>

      {/* Backdrop */}
      <div className="modal-backdrop fade show" />
    </>
  );
}
