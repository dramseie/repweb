import React, { useEffect, useMemo, useState } from 'react';
import PaymentDialog from './PaymentDialog';

import OrderDialog from './OrderDialog'; // 👈 NEW


const cents = n => (Number(n) || 0);
const fmtMoney = c => (c / 100).toFixed(2) + ' €';

// ---- Read realizations from the mount div (set by Twig) ----
// Expecting objects like: { code, label, enabled, sort_order, colour_code, color_hex }
const initialRealisations = (() => {
  try {
    const mount = document.getElementById('pos-root');
    if (!mount) return [];
    const raw = mount.dataset.realisations || '[]';
    const arr = JSON.parse(raw);
    if (!Array.isArray(arr)) return [];
    return arr.map(x => ({
      code: String(x.code ?? ''),
      label: String(x.label ?? ''),
      enabled: Number(x.enabled ?? 1),
      sort_order: Number(x.sort_order ?? 9999),
      colour_code: String(x.colour_code ?? '').toLowerCase(), // rose/bleu/violet/jaune/vert/orange/blanc
      color_hex: x.color_hex || null,
    }));
  } catch {
    return [];
  }
})();

// ---- French date helpers ----
const parseSqlDate = (s) => {
  if (!s) return null;
  const d = new Date(String(s).replace(' ', 'T'));
  return isNaN(d) ? null : d;
};
const fmtDateFr = (s) => {
  const d = parseSqlDate(s);
  return d ? d.toLocaleDateString('fr-FR') : '—';
};
const fmtTimeFr = (s) => {
  const d = parseSqlDate(s);
  return d ? d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) : '—';
};

// Parse realizations from an order row (supports either `realizations` or `realizations_json`)
const getOrderReals = (o) => {
  if (Array.isArray(o?.realizations)) return o.realizations;
  try {
    const arr = JSON.parse(o?.realizations_json || '[]');
    return Array.isArray(arr) ? arr : [];
  } catch { return []; }
};

const mkDt = (dateStr, timeStr) => {
  const d = new Date(`${dateStr}T${timeStr}`);
  const pad = n => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:00`;
};
const nowSql = () =>
  new Date(Date.now() - new Date().getTimezoneOffset() * 60000)
    .toISOString().slice(0, 19).replace('T', ' ');

// ---------- NEW: Pastel color mapping + hook to sort/filter ----------
const REAL_COLOR_HEX = {
  rose:   '#F7D6E0',
  bleu:   '#B5E0FA',
  violet: '#E2C2F0',
  jaune:  '#FFFACD',
  vert:   '#BDECB6',
  orange: '#FFDEB4',
  blanc:  '#FFFFFF'
};

const useSortedReals = (reals) => {
  return useMemo(() => {
    return [...(reals || [])]
      .filter(r => (r.enabled ?? 1) === 1)
      .sort((a, b) => {
        const sa = a.sort_order ?? 9999;
        const sb = b.sort_order ?? 9999;
        if (sa !== sb) return sa - sb;
        return String(a.label || '').localeCompare(String(b.label || ''), 'fr');
      });
  }, [reals]);
};
// ---------- /NEW ----------

export default function PosApp() {
  const [loading, setLoading] = useState(true);
  const [cats, setCats] = useState({});
  const [cart, setCart] = useState([]);

  // 👤 Customer state
  const [customer, setCustomer] = useState(null);
  const [custTab, setCustTab] = useState('search');
  const [custQuery, setCustQuery] = useState('');
  const [custResults, setCustResults] = useState([]);

  // 👥 All customers tab state
  const [allCustomers, setAllCustomers] = useState([]);
  const [allLoading, setAllLoading] = useState(false);
  const [allErr, setAllErr] = useState(null);
  const [allFilter, setAllFilter] = useState('');

  // 🔹 Customer detail & history
  const [custDetail, setCustDetail] = useState(null);
  const [activeAppointmentId, setActiveAppointmentId] = useState(null);

  // 🔹 New RDV form
  const [rdv, setRdv] = useState({ date: '', time: '', durationMin: 60, notes_public: '' });
  const setR = (k, v) => setRdv(prev => ({ ...prev, [k]: v }));
  const [rdvErr, setRdvErr] = useState(null);

  // 🧩 Réalisations selection (metadata only, no price)
  const [selectedReals, setSelectedReals] = useState([]); // [{code,label}]
  const allReals = initialRealisations; // from Twig
  const sortedReals = useSortedReals(allReals); // <-- NEW

  // 📝 Order notes
  const [techNotes, setTechNotes] = useState('');

  // 💳 Payment dialog state
  const [payOpen, setPayOpen] = useState(false);
  const [currentOrder, setCurrentOrder] = useState(null);

  // ✏️ EDIT MODAL STATE
  const [editOpen, setEditOpen] = useState(false);
  const [editId, setEditId] = useState(null);
  const [editForm, setEditForm] = useState({
    first_name: '', last_name: '', phone: '', email: '', notes_public: '', gdpr_ok: true
  });
  const [editErr, setEditErr] = useState(null);
  const EF = (k, v) => setEditForm(p => ({ ...p, [k]: v }));


	// 🔎 Order dialog state
	const [showOrderDlg, setShowOrderDlg] = useState(false);
	const [orderDlgData, setOrderDlgData] = useState(null);
	const openOrderDialog = async (orderId) => {
	  try {
		const r = await fetch(`/api/pos/orders/${orderId}`);
		const d = await r.json();
		if (d && d.ok) {
		  setOrderDlgData(d);
		  setShowOrderDlg(true);
		}
	  } catch (e) {
		console.error('Failed to load order', e);
	  }
	};


  const selectedAppt = useMemo(() => {
    if (!custDetail?.appointments?.length || !activeAppointmentId) return null;
    return custDetail.appointments.find(a => a.id === activeAppointmentId) || null;
  }, [custDetail, activeAppointmentId]);

  const rendezVousAtIso = useMemo(() => {
    if (!selectedAppt) return null;
    return selectedAppt.start_at ? selectedAppt.start_at.replace(' ', 'T') : null;
  }, [selectedAppt]);

  useEffect(() => {
    (async () => {
      setLoading(true);
      const r = await fetch('/api/pos/items');
      const data = await r.json();
      setCats(data.categories || {});
      setLoading(false);
    })();
  }, []);

  async function searchCustomers(q) {
    const r = await fetch('/api/pos/customers?q=' + encodeURIComponent(q));
    const data = await r.json();
    setCustResults(data.items || []);
  }

  async function loadAllCustomers() {
    setAllLoading(true); setAllErr(null);
    try {
      let r = await fetch('/api/pos/customers?all=1');
      let data = await r.json();
      if (!r.ok || !data?.items) {
        r = await fetch('/api/pos/customers');
        data = await r.json();
      }
      if (!data?.items) {
        r = await fetch('/api/pos/customers?q=');
        data = await r.json();
      }
      setAllCustomers(Array.isArray(data?.items) ? data.items : []);
    } catch (e) {
      setAllErr(e.message || 'Erreur chargement clients');
    } finally {
      setAllLoading(false);
    }
  }

  async function loadCustomerDetail(id) {
    const r = await fetch(`/api/pos/customers/${id}`);
    const d = await r.json();
    if (d.customer) {
      setCustDetail(d);
      if (Object.prototype.hasOwnProperty.call(d, 'active_appointment_id')) {
        setActiveAppointmentId(d.active_appointment_id || null);
      }
    }
  }

  async function createCustomer(payload) {
    const r = await fetch('/api/pos/customers', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const d = await r.json();
    if (d.ok) {
      const picked = { id: d.id, first_name: payload.first_name, last_name: payload.last_name, phone: payload.phone, email: payload.email };
      setCustomer(picked);
      setCustTab('search'); setCustResults([]); setCustQuery('');
      await loadCustomerDetail(d.id);
      return d.id;
    } else {
      throw new Error(d.error || 'Erreur création client');
    }
  }

  async function createRdv() {
    setRdvErr(null);
    if (!customer?.id) { setRdvErr('Sélectionnez un client.'); return; }
    if (!rdv.date || !rdv.time) { setRdvErr('Date et heure sont requises.'); return; }
    const start = mkDt(rdv.date, rdv.time);
    const startDate = new Date(`${rdv.date}T${rdv.time}`);
    const end = mkDt(
      rdv.date,
      new Date(startDate.getTime() + (Number(rdv.durationMin) || 60) * 60000).toTimeString().slice(0, 5)
    );
    const payload = {
      customer_id: customer.id,
      start_at: start,
      end_at: end,
      status: 'booked',
      notes_public: rdv.notes_public || null
    };
    const r = await fetch('/api/pos/appointments', {
      method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload)
    });
    const d = await r.json();
    if (!d.ok) { setRdvErr(d.error || 'Erreur création RDV'); return; }
    setActiveAppointmentId(d.id || null);
    await loadCustomerDetail(customer.id);
    setRdv(prev => ({ ...prev, notes_public: '' }));
  }

  const selectCustomer = async (c) => {
    setCustomer(c);
    setActiveAppointmentId(null);
    await loadCustomerDetail(c.id);
  };

  async function markRealStart(apptId) {
    if (!apptId) return;
    await fetch(`/api/pos/appointments/${apptId}`, {
      method:'PATCH', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ real_start_at: nowSql(), status: 'booked' })
    });
    if (customer?.id) await loadCustomerDetail(customer.id);
  }

  // ----- POS cart logic -----
  const addItem = (it) => {
    setCart(prev => {
      const wasEmpty = prev.length === 0;
      const idx = prev.findIndex(x => x.id === it.id);
      const next = idx >= 0
        ? (() => { const copy = [...prev]; copy[idx] = { ...copy[idx], qty: copy[idx].qty + 1 }; return copy; })()
        : [...prev, { id: it.id, name: it.name, unit: it.price_cents, rate: it.tax_rate, qty: 1 }];

      if (wasEmpty && activeAppointmentId) markRealStart(activeAppointmentId);
      return next;
    });
  };
  const removeLine = id => setCart(prev => prev.filter(x => x.id !== id));
  const inc = id => setCart(prev => prev.map(x => x.id === id ? { ...x, qty: x.qty + 1 } : x));
  const dec = id => setCart(prev => prev.map(x => x.id === id ? { ...x, qty: Math.max(1, x.qty - 1) } : x));

  const totals = useMemo(() => {
    let net = 0, tax = 0;
    for (const l of cart) {
      const line = cents(l.unit) * l.qty;
      net += line;
      tax += Math.round(line * (Number(l.rate) / 100));
    }
    return { net, tax, total: net + tax };
  }, [cart]);

  // --- Réalisations helpers ---
  const isRealSelected = code => selectedReals.some(r => r.code === code);
  const toggleReal = (code, label) => {
    setSelectedReals(prev => {
      const idx = prev.findIndex(r => r.code === code);
      if (idx >= 0) {
        const copy = [...prev]; copy.splice(idx, 1); return copy;
      }
      return [...prev, { code, label }];
    });
  };
  const clearReals = () => setSelectedReals([]);

  // Create order, then open modal
  const saveOrder = async () => {
    if (!cart.length) return;

    if (activeAppointmentId) {
      await fetch(`/api/pos/appointments/${activeAppointmentId}`, {
        method:'PATCH', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ real_end_at: nowSql(), status: 'done' })
      });
    }

    const payload = {
      items: cart.map(l => ({ item_id: l.id, qty: l.qty })),
      customer_id: customer?.id || null,
      appointment_id: activeAppointmentId || null,
      note: techNotes || null,
      realizations: selectedReals,
    };
    const r = await fetch('/api/pos/orders', {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload)
    });
    const data = await r.json();
    if (data.ok) {
      const apptId = data.appointment_id ?? activeAppointmentId ?? null;
      let startIso = null;
      if (apptId && custDetail?.appointments?.length) {
        const a = custDetail.appointments.find(x => x.id === apptId);
        const start = a?.start_at || null;
        if (start) startIso = start.replace(' ', 'T');
      }
      setCurrentOrder({
        id: data.order_id,
        total_cents: data.total_cents,
        appointment_id: apptId,
        rendezVousStartIso: startIso,
      });
      setPayOpen(true);
    } else {
      alert('Erreur: ' + (data.error || 'inconnue'));
    }
  };

  // Confirm payment
  const confirmPayment = async (payload) => {
    if (!currentOrder?.id) throw new Error('Order missing');
    const r = await fetch(`/api/pos/orders/${currentOrder.id}/encaisser`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    if (!r.ok) throw new Error(await r.text());

    setPayOpen(false);
    setCart([]);
    setTechNotes('');
    setSelectedReals([]);
    if (customer?.id) await loadCustomerDetail(customer.id);

    window.location.reload();
  };

  const isActiveAppt = a => a.id === activeAppointmentId;
  const selectAppt = a => setActiveAppointmentId(a.id);

  const orderRows = useMemo(() => {
    const orders = custDetail?.orders ?? [];
    const appts  = custDetail?.appointments ?? [];
    const apptById = new Map(appts.map(a => [a.id, a]));

    const rows = orders.map(o => {
      let em = o.elapsed_minutes ?? null;
      if (em == null && o.encaisse_at && o.appointment_id && apptById.has(o.appointment_id)) {
        const a = apptById.get(o.appointment_id);
        const start = a?.start_at;
        const s = parseSqlDate(start)?.getTime();
        const e = parseSqlDate(o.encaisse_at)?.getTime();
        if (s && e && e >= s) em = Math.round((e - s) / 60000);
      }
      return {
        key: `order-${o.id}`,
        date: (o.encaisse_at || o.created_at),
        total_cents: o.total_cents ?? 0,
        elapsed_minutes: em,
        note: o.note || '—',
      };
    });

    const hasOrderForAppt = new Set(orders.filter(o => o.appointment_id != null).map(o => o.appointment_id));
    appts
      .filter(a => a.status === 'done' && !hasOrderForAppt.has(a.id))
      .forEach(a => {
        const dt = a.end_at || a.start_at;
        let em = null;
        if (a.start_at && a.end_at) {
          const s = parseSqlDate(a.start_at)?.getTime();
          const e = parseSqlDate(a.end_at)?.getTime();
          if (s && e && e >= s) em = Math.round((e - s) / 60000);
        }
        rows.push({
          key: `appt-${a.id}`,
          date: dt,
          total_cents: null,
          elapsed_minutes: em,
          note: '(RDV terminé, pas encore de commande)',
        });
      });

    rows.sort((a, b) => {
      const da = parseSqlDate(a.date)?.getTime() ?? 0;
      const db = parseSqlDate(b.date)?.getTime() ?? 0;
      return db - da;
    });

    return rows;
  }, [custDetail]);

  // ===== ✏️ EDIT CUSTOMER HELPERS =====
  async function openEditCustomer(id) {
    setEditErr(null);
    const r = await fetch(`/api/pos/customers/${id}`);
    const d = await r.json();
    if (!r.ok || !d?.customer) {
      setEditErr(d?.error || 'Chargement du client impossible.');
      setEditOpen(true);
      return;
    }
    const c = d.customer;
    setEditId(c.id);
    setEditForm({
      first_name: c.first_name || '',
      last_name: c.last_name || '',
      phone: c.phone || '',
      email: c.email || '',
      notes_public: c.notes_public || '',
      gdpr_ok: !!c.gdpr_ok
    });
    setEditOpen(true);
  }

  async function saveEditCustomer() {
    setEditErr(null);
    const fn = editForm.first_name.trim();
    const ln = editForm.last_name.trim();
    if (!fn || !ln) {
      setEditErr('Prénom et nom sont requis.');
      return;
    }
    const r = await fetch(`/api/pos/customers/${editId}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        first_name: fn,
        last_name: ln,
        phone: editForm.phone.trim() || null,
        email: editForm.email.trim() || null,
        notes_public: editForm.notes_public.trim() || null,
        gdpr_ok: !!editForm.gdpr_ok
      })
    });
    const d = await r.json().catch(() => ({}));
    if (!r.ok) {
      setEditErr(d?.error || 'Enregistrement impossible.');
      return;
    }

    // Refresh selected customer + lists
    const updated = d;
    if (customer?.id === editId) {
      setCustomer({
        id: updated.id,
        first_name: updated.first_name,
        last_name: updated.last_name,
        phone: updated.phone,
        email: updated.email
      });
      await loadCustomerDetail(editId);
    }

    setCustResults(prev =>
      prev.map(x => x.id === editId ? {
        ...x,
        first_name: updated.first_name,
        last_name: updated.last_name,
        phone: updated.phone,
        email: updated.email
      } : x)
    );
    setAllCustomers(prev =>
      prev.map(x => x.id === editId ? {
        ...x,
        first_name: updated.first_name,
        last_name: updated.last_name,
        phone: updated.phone,
        email: updated.email
      } : x)
    );

    setEditOpen(false);
  }

  return (
    <div className="row g-3">
      {/* LEFT: Articles & Services + Panier */}
      <div className="col-lg-7">
        {/* Articles */}
        <div className="card shadow-sm mb-3">
          <div className="card-header d-flex justify-content-between align-items-center">
            <strong>Articles & Services</strong>
            {loading && <span className="small text-muted">Chargement…</span>}
          </div>
          <div className="card-body">
            {Object.keys(cats).length === 0 && !loading && <div className="text-muted">Aucun article.</div>}
            {Object.entries(cats).map(([cat, items]) => (
              <div key={cat} className="mb-3">
                <div className="fw-semibold mb-2">{cat}</div>
                <div className="d-flex flex-wrap gap-2">
                  {items.map(it => (
                    <button key={it.id}
                      onClick={() => addItem(it)}
                      className="btn btn-light border"
                      style={{ minWidth: 130, minHeight: 60, backgroundColor: it.color_hex || undefined }}>
                      <div className="small fw-semibold">{it.name}</div>
                      <div className="small">{fmtMoney(it.price_cents)}</div>
                    </button>
                  ))}
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Panier */}
        <div className="card shadow-sm">
          <div className="card-header d-flex justify-content-between align-items-center">
            <strong>Panier</strong>
            <button className="btn btn-sm btn-outline-secondary" onClick={() => setCart([])} disabled={!cart.length}>Vider</button>
          </div>
          <div className="card-body">
            {!cart.length && <div className="text-muted">Ajoutez des articles à gauche.</div>}
            {cart.map(line => (
              <div key={line.id} className="d-flex align-items-center border-bottom py-2">
                <div className="flex-grow-1">
                  <div className="fw-semibold">{line.name}</div>
                  <div className="small text-muted">{fmtMoney(line.unit)} • TVA {line.rate}%</div>
                </div>
                <div className="d-flex align-items-center gap-2">
                  <button className="btn btn-sm btn-outline-secondary" onClick={() => dec(line.id)}>-</button>
                  <span className="px-2">{line.qty}</span>
                  <button className="btn btn-sm btn-outline-secondary" onClick={() => inc(line.id)}>+</button>
                </div>
                <div className="ms-3 fw-semibold" style={{ width: 90, textAlign: 'right' }}>
                  {fmtMoney(line.unit * line.qty)}
                </div>
                <button className="btn btn-sm btn-outline-danger ms-2" onClick={() => removeLine(line.id)} title="Retirer">×</button>
              </div>
            ))}

            {/* Réalisations block */}
            <div className="mt-3 border rounded p-2">
              <div className="d-flex justify-content-between align-items-center mb-2">
                <div className="fw-semibold">Réalisations</div>
                <button className="btn btn-sm btn-outline-secondary" onClick={clearReals} disabled={!selectedReals.length}>Vider</button>
              </div>
              {sortedReals.length ? (
                <div className="d-flex flex-wrap gap-2">
                  {sortedReals.map(r => {
                    const active = isRealSelected(r.code);
                    const bg = r.color_hex || REAL_COLOR_HEX[r.colour_code] || '#EEE';
                    return (
                      <button
                        key={r.code}
                        type="button"
                        onClick={() => toggleReal(r.code, r.label)}
                        title={`${r.label} • ${r.colour_code || 'n/a'} • ordre ${r.sort_order ?? '-'}`}
                        className={`btn btn-sm border ${active ? 'btn-primary' : ''}`}
                        style={{
                          backgroundColor: active ? undefined : bg,
                          borderColor: 'rgba(0,0,0,.12)',
                          boxShadow: active ? 'inset 0 0 0 2px rgba(255,255,255,.6)' : 'none'
                        }}
                      >
                        {r.label}
                      </button>
                    );
                  })}
                </div>
              ) : (
                <div className="text-muted small">Aucune réalisation définie.</div>
              )}
              {!!selectedReals.length && (
                <div className="mt-2 small">
                  {selectedReals.map(r => (
                    <span key={r.code} className="badge text-bg-secondary me-1 mb-1">{r.label}</span>
                  ))}
                </div>
              )}
            </div>

            {/* Notes techniques */}
            <div className="mt-3">
              <label className="form-label small">Notes techniques (après RDV)</label>
              <textarea className="form-control" rows={2} value={techNotes} onChange={e => setTechNotes(e.target.value)} placeholder="Ex: ongles fragiles, allergies, préférences…" />
            </div>

            <div className="mt-3">
              <div className="d-flex justify-content-between"><span>Sous-total</span><strong>{fmtMoney(totals.net)}</strong></div>
              <div className="d-flex justify-content-between"><span>TVA</span><strong>{fmtMoney(totals.tax)}</strong></div>
              <div className="d-flex justify-content-between fs-5 border-top pt-2"><span>Total</span><strong>{fmtMoney(totals.total)}</strong></div>
            </div>
          </div>
          <div className="card-footer d-flex gap-2">
            <button className="btn btn-primary" onClick={saveOrder} disabled={!cart.length}>Encaisser</button>
            <button className="btn btn-outline-secondary" onClick={() => window.print()} disabled={!cart.length}>Imprimer</button>
          </div>
        </div>
      </div>

      {/* RIGHT: Client + RDV + Historique */}
      <div className="col-lg-5">
        <div className="card shadow-sm mb-3">
          <div className="card-header d-flex justify-content-between align-items-center">
            <strong>Client</strong>
            {!!customer && (
              <div className="d-flex gap-2">
                <button
                  className="btn btn-sm btn-outline-secondary"
                  title="Éditer ce client"
                  onClick={() => openEditCustomer(customer.id)}
                >
                  ✏️ Éditer
                </button>
                <button className="btn btn-sm btn-outline-danger"
                        onClick={() => { setCustomer(null); setCustDetail(null); setActiveAppointmentId(null); }}>
                  Changer
                </button>
              </div>
            )}
          </div>
          <div className="card-body">
            {!customer ? (
              <>
                <ul className="nav nav-tabs mb-3">
                  <li className="nav-item">
                    <button className={`nav-link ${custTab==='search'?'active':''}`} onClick={()=>setCustTab('search')}>Rechercher</button>
                  </li>
                  <li className="nav-item">
                    <button
                      className={`nav-link ${custTab==='tous'?'active':''}`}
                      onClick={()=>{
                        setCustTab('tous');
                        if (!allCustomers.length && !allLoading) loadAllCustomers();
                      }}
                    >
                      Tous
                    </button>
                  </li>
                  <li className="nav-item">
                    <button className={`nav-link ${custTab==='create'?'active':''}`} onClick={()=>setCustTab('create')}>Nouveau</button>
                  </li>
                </ul>

                {custTab === 'search' && (
                  <>
                    <div className="input-group mb-2">
                      <input className="form-control" placeholder="Nom, téléphone, email…"
                        value={custQuery}
                        onChange={e => {
                          const v = e.target.value;
                          setCustQuery(v);
                          if (v.trim().length >= 2) searchCustomers(v.trim());
                          else setCustResults([]);
                        }} />
                      <button className="btn btn-outline-secondary" onClick={() => searchCustomers(custQuery)}>OK</button>
                    </div>
                    <div className="list-group small" style={{ maxHeight: 220, overflowY: 'auto' }}>
                      {custResults.map(c => (
                        <div key={c.id} className="list-group-item d-flex justify-content-between align-items-start">
                          <button
                            className="btn btn-link p-0 text-start flex-grow-1"
                            onClick={() => selectCustomer(c)}
                            title="Sélectionner ce client"
                          >
                            {c.last_name} {c.first_name} — {c.phone || c.email || '—'}
                          </button>
                          <button
                            className="btn btn-sm btn-outline-secondary ms-2"
                            title="Éditer"
                            onClick={(e) => { e.stopPropagation(); openEditCustomer(c.id); }}
                          >
                            ✏️
                          </button>
                        </div>
                      ))}
                      {!custResults.length && custQuery.trim().length >= 2 && (
                        <div className="text-muted small p-2">Aucun résultat…</div>
                      )}
                    </div>
                  </>
                )}

                {custTab === 'tous' && (
                  <>
                    <div className="d-flex gap-2 align-items-center mb-2">
                      <input
                        className="form-control"
                        placeholder="Filtrer (nom, téléphone, email)…"
                        value={allFilter}
                        onChange={e => setAllFilter(e.target.value)}
                      />
                      <button
                        className="btn btn-outline-secondary"
                        onClick={() => loadAllCustomers()}
                        disabled={allLoading}
                        title="Rafraîchir"
                      >
                        {allLoading ? '...' : '↻'}
                      </button>
                    </div>

                    {allErr && <div className="alert alert-danger py-1 small mb-2">{allErr}</div>}

                    <div className="list-group small" style={{ maxHeight: 260, overflowY: 'auto' }}>
                      {(allCustomers
                        .filter(c => {
                          const f = allFilter.trim().toLowerCase();
                          if (!f) return true;
                          const hay = `${c.last_name||''} ${c.first_name||''} ${c.phone||''} ${c.email||''}`.toLowerCase();
                          return hay.includes(f);
                        })
                      ).map(c => (
                        <div key={c.id} className="list-group-item">
                          <div className="d-flex justify-content-between align-items-center">
                            <button
                              className="btn btn-link p-0 text-start flex-grow-1"
                              onClick={() => selectCustomer(c)}
                              title="Sélectionner ce client"
                            >
                              <div className="d-flex justify-content-between">
                                <span className="fw-semibold">{c.last_name} {c.first_name}</span>
                                <span className="text-muted">#{c.id}</span>
                              </div>
                              <div className="text-muted small">
                                {c.phone || '—'} · {c.email || '—'}
                              </div>
                            </button>
                            <button
                              className="btn btn-sm btn-outline-secondary ms-2"
                              title="Éditer"
                              onClick={(e) => { e.stopPropagation(); openEditCustomer(c.id); }}
                            >
                              ✏️
                            </button>
                          </div>
                        </div>
                      ))}
                      {!allLoading && !allErr && allCustomers.length === 0 && (
                        <div className="text-muted small p-2">Aucun client à afficher.</div>
                      )}
                      {allLoading && (
                        <div className="text-muted small p-2">Chargement…</div>
                      )}
                    </div>
                  </>
                )}

                {custTab === 'create' && (
                  <CreateCustomerForm onCreated={async (payload) => { await createCustomer(payload); }}/>
                )}
              </>
            ) : (
              <>
                <div className="d-flex justify-content-between align-items-center mb-2">
                  <div>
                    <div className="fw-semibold">{customer.last_name} {customer.first_name}</div>
                    <div className="small text-muted">{customer.phone || customer.email || '—'}</div>
                  </div>
                  <span className="badge text-bg-light">#{customer.id}</span>
                </div>

                {/* Nouveau RDV */}
                <div className="border rounded p-2 mb-3">
                  <div className="fw-semibold mb-2">Nouveau rendez-vous</div>
                  {rdvErr && <div className="alert alert-danger py-1 small">{rdvErr}</div>}
                  <div className="row g-2">
                    <div className="col-5">
                      <label className="form-label small">Date</label>
                      <input type="date" className="form-control" value={rdv.date} onChange={e=>setR('date', e.target.value)} />
                    </div>
                    <div className="col-4">
                      <label className="form-label small">Heure</label>
                      <input type="time" className="form-control" step="300" value={rdv.time} onChange={e=>setR('time', e.target.value)} />
                    </div>
                    <div className="col-3">
                      <label className="form-label small">Durée</label>
                      <div className="input-group">
                        <input type="number" min="15" step="15" className="form-control" value={rdv.durationMin}
                               onChange={e=>setR('durationMin', e.target.value)} />
                        <span className="input-group-text">min</span>
                      </div>
                    </div>
                    <div className="col-12">
                      <label className="form-label small">Notes (visibles)</label>
                      <textarea className="form-control" rows={2} value={rdv.notes_public}
                                onChange={e=>setR('notes_public', e.target.value)} />
                    </div>
                    <div className="col-12 d-flex justify-content-end">
                      <button className="btn btn-sm btn-primary" onClick={createRdv}>Créer RDV</button>
                      <a className="btn btn-sm btn-outline-secondary ms-2" href="/pos/agenda" target="_blank" rel="noreferrer">Ouvrir l’agenda</a>
                    </div>
                  </div>
                </div>

                {/* Historique */}
                <div className="fw-semibold mb-1">Historique</div>
                {!custDetail ? (
                  <div className="text-muted small">Chargement…</div>
                ) : (
                  <div className="small">
                    {activeAppointmentId && (
                      <div className="mb-2">
                        <span className="badge text-bg-info">RDV sélectionné : #{activeAppointmentId}</span>
                        <button className="btn btn-sm btn-link ms-2 p-0" onClick={()=>setActiveAppointmentId(null)}>Retirer le lien</button>
                      </div>
                    )}

                    <div className="mb-2">
                      <div className="fw-semibold">Rendez-vous</div>
                      <div className="table-responsive">
                        <table className="table table-sm align-middle mb-2">
                          <thead>
                            <tr><th>Date</th><th>Heure</th><th>Statut</th><th>Notes</th></tr>
                          </thead>
                          <tbody>
                            {(custDetail.appointments?.filter(a => a.status !== 'done') ?? []).length
                              ? custDetail.appointments
                                  .filter(a => a.status !== 'done')
                                  .map(a => (
                                    <tr key={a.id}
                                        onClick={() => selectAppt(a)}
                                        className={a.id === activeAppointmentId ? 'table-primary' : ''}
                                        style={{ cursor:'pointer' }}
                                        title="Sélectionner ce rendez-vous">
                                      <td>{fmtDateFr(a.start_at)}</td>
                                      <td>{fmtTimeFr(a.start_at)}</td>
                                      <td>{a.status}</td>
                                      <td className="text-truncate" style={{maxWidth:220}} title={a.notes_public || ''}>{a.notes_public || '—'}</td>
                                    </tr>
                                  ))
                              : <tr><td colSpan={4} className="text-muted">Aucun RDV</td></tr>}
                          </tbody>
                        </table>
                      </div>
                    </div>

                    <div>
                      <div className="fw-semibold">Commandes</div>
                      <div className="table-responsive">
                        <table className="table table-sm align-middle">
                          <thead>
                            <tr><th>Date</th><th>Total</th><th>Réalisations</th><th>Temps écoulé</th><th>Note</th></tr>
                          </thead>
                          <tbody>
                            {orderRows.length
                              ? orderRows.map(row => (
								<tr
								  key={row.key}
								  style={{ cursor: 'pointer' }}
								  onClick={() => {
									// row.key is "order-<id>" or "appt-<appointment_id>"
									const m = String(row.key).match(/^order-(\d+)$/);
									if (m) {
									  openOrderDialog(Number(m[1]));
									}
								  }}
								>

                                    <td>{`${fmtDateFr(row.date)} ${fmtTimeFr(row.date)}`}</td>
                                    <td>{row.total_cents == null ? '—' : fmtMoney(row.total_cents)}</td>
                                    <td style={{maxWidth:220}}>
                                      {(() => {
                                        const reals = getOrderReals((custDetail?.orders || []).find(o =>
                                          (`order-${o.id}` === row.key) || (`appt-${o.appointment_id}` === row.key)
                                        ) || {});
                                        if (!reals.length) return '—';
                                        return (
                                          <div className="d-flex flex-wrap gap-1">
                                            {reals.map((r, i) => (
                                              <span key={`${r.code || r.label || i}`} className="badge text-bg-secondary">
                                                {r?.label || r?.code}
                                              </span>
                                            ))}
                                          </div>
                                        );
                                      })()}
                                    </td>
                                    <td>{row.elapsed_minutes == null ? '—' : `${row.elapsed_minutes} min`}</td>
                                    <td className="text-truncate" style={{maxWidth:220}} title={row.note}>{row.note}</td>
                                  </tr>
                                ))
                              : <tr><td colSpan={4} className="text-muted">Aucune commande</td></tr>}
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                )}
              </>
            )}
          </div>
        </div>
      </div>

		 <OrderDialog
		  show={showOrderDlg}
		  data={orderDlgData}
		  onClose={() => setShowOrderDlg(false)}
		/>

      {/* 💳 Payment Modal */}
      <PaymentDialog
        show={payOpen}
        onClose={() => setPayOpen(false)}
        onConfirm={confirmPayment}
        amountDueCents={currentOrder?.total_cents ?? 0}
        rendezVousAtIso={currentOrder?.rendezVousStartIso || rendezVousAtIso}
      />

      {/* ✏️ Customer Edit Modal */}
      <div className={`modal ${editOpen ? 'd-block show' : ''}`} tabIndex="-1" style={{ background: editOpen ? 'rgba(0,0,0,.5)' : 'transparent' }} role="dialog" aria-modal={editOpen ? 'true' : undefined}>
        <div className="modal-dialog modal-lg">
          <div className="modal-content">
            <div className="modal-header">
              <h5 className="modal-title">Éditer le client {editId ? `#${editId}` : ''}</h5>
              <button type="button" className="btn-close" onClick={() => setEditOpen(false)} aria-label="Fermer"></button>
            </div>
            <div className="modal-body">
              {editErr && <div className="alert alert-danger py-1 small mb-2">{editErr}</div>}
              <div className="row g-2">
                <div className="col-6">
                  <label className="form-label small">Prénom *</label>
                  <input className="form-control" value={editForm.first_name} onChange={e=>EF('first_name', e.target.value)} />
                </div>
                <div className="col-6">
                  <label className="form-label small">Nom *</label>
                  <input className="form-control" value={editForm.last_name} onChange={e=>EF('last_name', e.target.value)} />
                </div>
                <div className="col-6">
                  <label className="form-label small">Téléphone</label>
                  <input className="form-control" value={editForm.phone} onChange={e=>EF('phone', e.target.value)} />
                </div>
                <div className="col-6">
                  <label className="form-label small">Email</label>
                  <input type="email" className="form-control" value={editForm.email} onChange={e=>EF('email', e.target.value)} />
                </div>
                <div className="col-12">
                  <label className="form-label small">Notes (visibles)</label>
                  <textarea className="form-control" rows={2} value={editForm.notes_public} onChange={e=>EF('notes_public', e.target.value)} />
                </div>
                <div className="col-12">
                  <div className="form-check">
                    <input className="form-check-input" type="checkbox" id="gdpr_edit" checked={!!editForm.gdpr_ok} onChange={e=>EF('gdpr_ok', e.target.checked)} />
                    <label className="form-check-label small" htmlFor="gdpr_edit">Consentement RGPD</label>
                  </div>
                </div>
              </div>
            </div>
            <div className="modal-footer">
              <button className="btn btn-secondary" onClick={() => setEditOpen(false)}>Annuler</button>
              <button className="btn btn-primary" onClick={saveEditCustomer}>Enregistrer</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

/* --- Small embedded component: customer creation form --- */
function CreateCustomerForm({ onCreated }) {
  const [f, setF] = useState({
    first_name: '', last_name: '',
    phone: '', email: '',
    notes_public: '', gdpr_ok: true
  });
  const [err, setErr] = useState(null);
  const S = (k, v) => setF(p => ({ ...p, [k]: v }));

  const submit = async (e) => {
    e.preventDefault();
    setErr(null);
    const fn = f.first_name.trim(), ln = f.last_name.trim();
    if (!fn || !ln) { setErr('Prénom et nom sont requis.'); return; }
    try {
      await onCreated({
        first_name: fn, last_name: ln,
        phone: f.phone.trim() || null,
        email: f.email.trim() || null,
        notes_public: f.notes_public.trim() || null,
        gdpr_ok: !!f.gdpr_ok
      });
      setF({ first_name:'', last_name:'', phone:'', email:'', notes_public:'', gdpr_ok:true });
    } catch (e2) {
      setErr(e2.message || 'Erreur');
    }
  };

  return (
    <form onSubmit={submit}>
      {err && <div className="alert alert-danger py-1 small">{err}</div>}
      <div className="row g-2">
        <div className="col-6">
          <label className="form-label small">Prénom *</label>
          <input className="form-control" value={f.first_name} onChange={e=>S('first_name', e.target.value)} />
        </div>
        <div className="col-6">
          <label className="form-label small">Nom *</label>
          <input className="form-control" value={f.last_name} onChange={e=>S('last_name', e.target.value)} />
        </div>
        <div className="col-6">
          <label className="form-label small">Téléphone</label>
          <input className="form-control" value={f.phone} onChange={e=>S('phone', e.target.value)} />
        </div>
        <div className="col-6">
          <label className="form-label small">Email</label>
          <input type="email" className="form-control" value={f.email} onChange={e=>S('email', e.target.value)} />
        </div>
        <div className="col-12">
          <label className="form-label small">Notes (visibles)</label>
          <textarea className="form-control" rows={2} value={f.notes_public} onChange={e=>S('notes_public', e.target.value)} />
        </div>
        <div className="col-12">
          <div className="form-check">
            <input className="form-check-input" type="checkbox" id="gdpr_create" checked={!!f.gdpr_ok} onChange={e=>S('gdpr_ok', e.target.checked)} />
            <label className="form-check-label small" htmlFor="gdpr_create">Consentement RGPD</label>
          </div>
        </div>
        <div className="col-12 d-flex justify-content-end mt-2">
          <button type="submit" className="btn btn-sm btn-primary">Créer le client</button>
        </div>
      </div>
    </form>
  );
}
