import React, { useMemo, useState, useEffect } from "react";

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

// UI labels for methods
const PM_LABEL = {
  "": "—",
  cash: "Espèces",
  card: "CB / Carte",
  other: "Autre",
};

export default function OrderDialog({ show, onClose, data }) {
  if (!show) return null;

  const order = data?.order || {};
  const items = data?.items || [];
  const customer = data?.customer || null;
  const appt = data?.appointment || null;

  // Divers: prefer data.custom_items (API), fallback to order.custom_items_json
  const customItems = useMemo(() => {
    if (Array.isArray(data?.custom_items)) return data.custom_items;
    try {
      const arr = JSON.parse(order?.custom_items_json || "[]");
      return Array.isArray(arr) ? arr : [];
    } catch {
      return [];
    }
  }, [data?.custom_items, order?.custom_items_json]);

  const reals = useMemo(() => {
    try {
      const arr = JSON.parse(order?.realizations_json || "[]");
      return Array.isArray(arr) ? arr : [];
    } catch {
      return [];
    }
  }, [order?.realizations_json]);

  const payments = useMemo(() => {
    try {
      const arr = JSON.parse(order?.payments_json || "null");
      return Array.isArray(arr) ? arr : [];
    } catch {
      return [];
    }
  }, [order?.payments_json]);

  const total = fmtMoney(order.total_cents);
  const totalTax = fmtMoney(order.total_tax_cents || 0);
  const amountReceived = order.amount_received_cents != null ? fmtMoney(order.amount_received_cents) : "—";
  const tip = order.tip_cents != null ? fmtMoney(order.tip_cents) : "—";
  const encaisseAt = order.encaisse_at ? `${fmtDate(order.encaisse_at)} ${fmtTime(order.encaisse_at)}` : "—";
  const elapsed = order.elapsed_minutes != null ? `${order.elapsed_minutes} min` : "—";

  // --- Editable notes state (public only)
  const [clientNotesPublic, setClientNotesPublic] = useState(customer?.notes_public || "");
  const [apptNotesPublic, setApptNotesPublic] = useState(appt?.notes_public || "");
  const [orderNote, setOrderNote] = useState(order?.note || "");
  const [saving, setSaving] = useState(false);

  // --- Editable payment method
  const [paymentMethod, setPaymentMethod] = useState(order?.payment_method || "");
  const [savingPM, setSavingPM] = useState(false);

  // Sync when data changes
  useEffect(() => { setClientNotesPublic(customer?.notes_public || ""); }, [customer?.notes_public, customer?.id]);
  useEffect(() => { setApptNotesPublic(appt?.notes_public || ""); }, [appt?.notes_public, appt?.id]);
  useEffect(() => { setOrderNote(order?.note || ""); }, [order?.note, order?.id]);
  useEffect(() => { setPaymentMethod(order?.payment_method || ""); }, [order?.payment_method, order?.id]);

  async function deleteOrder() {
    const encashed = !!order.encaisse_at;
    const msg = encashed
      ? "Cette commande a déjà été encaissée. Effacer quand même ?"
      : "Effacer définitivement cette commande ?";
    if (!window.confirm(msg)) return;

    try {
      setSaving(true);
      const res = await fetch(`/api/pos/orders/${order.id}?force=1`, {
        method: "POST",
        headers: { "X-HTTP-Method-Override": "DELETE" },
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.ok) throw new Error(data.error || `HTTP ${res.status}`);
      onClose?.();
      // eslint-disable-next-line no-restricted-globals
      location.reload();
    } catch (e) {
      console.error(e);
      alert("Échec de la suppression de la commande.");
    } finally {
      setSaving(false);
    }
  }

  async function saveNotes() {
    try {
      setSaving(true);
      const res = await fetch(`/api/pos/orders/${order.id}/notes`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-HTTP-Method-Override": "PATCH",
        },
        body: JSON.stringify({
          order_note: orderNote,
          customer_notes_public: clientNotesPublic,
          appointment_notes_public: apptNotesPublic,
        }),
      });
      if (!res.ok) {
        const t = await res.json().catch(() => ({}));
        throw new Error(t.error || `HTTP ${res.status}`);
      }
    } catch (e) {
      console.error(e);
      alert("Échec de l’enregistrement des notes.");
    } finally {
      setSaving(false);
    }
  }

  async function savePaymentMethod() {
    try {
      setSavingPM(true);
      const res = await fetch(`/api/pos/orders/${order.id}/meta`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ payment_method: paymentMethod || null }),
      });
      if (!res.ok) {
        const t = await res.json().catch(() => ({}));
        throw new Error(t.error || `HTTP ${res.status}`);
      }
    } catch (e) {
      console.error(e);
      alert("Échec de la mise à jour du mode de paiement.");
    } finally {
      setSavingPM(false);
    }
  }

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
                            <textarea
                              className="form-control"
                              rows={2}
                              placeholder="Ajouter une note visible"
                              value={clientNotesPublic}
                              onChange={(e) => setClientNotesPublic(e.target.value)}
                            />
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
                            <textarea
                              className="form-control"
                              rows={2}
                              placeholder="Notes du RDV (publiques)"
                              value={apptNotesPublic}
                              onChange={(e) => setApptNotesPublic(e.target.value)}
                            />
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

              {/* Items + Divers */}
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
                        <tr key={`it-${it.id}`}>
                          <td>{it.name_snapshot}</td>
                          <td className="text-center">{it.qty}</td>
                          <td className="text-end">{fmtMoney(it.unit_price_cents)}</td>
                          <td className="text-end">{Number(it.tax_rate || 0).toFixed(2)}%</td>
                          <td className="text-end">{fmtMoney(it.line_total_cents)}</td>
                        </tr>
                      )) : null}

                      {customItems.length ? (
                        <>
                          <tr><td colSpan={5} className="table-light fw-semibold">Divers</td></tr>
                          {customItems.map((c, idx) => (
                            <tr key={`custom-${idx}`}>
                              <td>{c.label || "Divers"}</td>
                              <td className="text-center">{c.qty || 1}</td>
                              <td className="text-end">{fmtMoney(c.unit_cents)}</td>
                              <td className="text-end">{Number(c.tax_rate || 0).toFixed(2)}%</td>
                              <td className="text-end">{fmtMoney(c.line_cents != null ? c.line_cents : (Number(c.unit_cents||0) * Number(c.qty||1)))}</td>
                            </tr>
                          ))}
                        </>
                      ) : null}

                      {!items.length && !customItems.length && (
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

                {/* Payments breakdown (optional) */}
                {!!payments.length && (
                  <div className="small text-muted">
                    Détail des paiements:&nbsp;
                    {payments.map((p, i) => (
                      <span key={i} className="me-2">
                        {PM_LABEL[p.method] || p.method || "—"}: {fmtMoney(p.amount_cents)}
                      </span>
                    ))}
                  </div>
                )}
              </div>

              {/* Payment method (editable) */}
              <div className="mb-3">
                <div className="fw-semibold mb-1">Mode de paiement</div>
                <div className="d-flex gap-2 align-items-center">
                  <select
                    className="form-select"
                    style={{ maxWidth: 240 }}
                    value={paymentMethod || ""}
                    onChange={(e) => setPaymentMethod(e.target.value)}
                  >
                    <option value="">— non défini —</option>
                    <option value="cash">Espèces</option>
                    <option value="card">CB / Carte</option>
                    <option value="other">Autre</option>
                  </select>
                  <button
                    className="btn btn-outline-primary"
                    onClick={savePaymentMethod}
                    disabled={savingPM}
                    title="Enregistrer le mode de paiement"
                  >
                    {savingPM ? "Enregistrement…" : "Enregistrer"}
                  </button>
                  {!!order.payment_method && (
                    <span className="badge text-bg-light ms-2">
                      Actuel: {PM_LABEL[order.payment_method] || order.payment_method}
                    </span>
                  )}
                </div>
              </div>

              {/* Notes commande */}
              <div>
                <div className="fw-semibold mb-1">Notes concernant la prestation</div>
                <textarea
                  className="form-control"
                  rows={2}
                  placeholder="Note liée à la prestation"
                  value={orderNote}
                  onChange={(e) => setOrderNote(e.target.value)}
                />
              </div>
            </div>

            <div className="modal-footer">
              <button type="button" className="btn btn-outline-secondary" onClick={onClose}>
                Fermer
              </button>
              <button
                type="button"
                className="btn btn-outline-danger"
                onClick={deleteOrder}
                disabled={saving}
                title="Effacer définitivement la commande"
              >
                Effacer la commande
              </button>
              <button
                type="button"
                className="btn btn-primary"
                onClick={saveNotes}
                disabled={saving}
              >
                {saving ? "Enregistrement…" : "Enregistrer les notes"}
              </button>
            </div>

          </div>
        </div>
      </div>

      <div className="modal-backdrop fade show" />
    </>
  );
}
