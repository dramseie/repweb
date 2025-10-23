// assets/react/components/PosAgenda.jsx
import React, { useMemo, useRef, useState } from 'react';
import FullCalendar from '@fullcalendar/react';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';

// ---- helpers
const pad = (n)=> String(n).padStart(2,'0');

// Write LOCAL datetime to SQL (no UTC conversion)
const toSql = (d) => {
  return (
    d.getFullYear() + '-' +
    pad(d.getMonth() + 1) + '-' +
    pad(d.getDate()) + ' ' +
    pad(d.getHours()) + ':' +
    pad(d.getMinutes()) + ':' +
    pad(d.getSeconds())
  );
};

// Read as LOCAL time (plain SQL "YYYY-MM-DD HH:MM:SS")
const fromSql = (s) => new Date(s.replace(' ', 'T'));

const toDateInput = (d)=> `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
const toTimeInput = (d)=> `${pad(d.getHours())}:${pad(d.getMinutes())}`;
const addMin = (d, m)=> new Date(d.getTime() + m*60000);

// ---- API helpers
const api = {
  listAppointments: async (startStr, endStr) => {
    const q = new URLSearchParams({
      from: startStr.slice(0,19).replace('T',' '),
      to:   endStr.slice(0,19).replace('T',' ')
    });
    const r = await fetch('/api/pos/appointments?' + q.toString());
    return r.json();
  },
  createAppointment: async (payload) => {
    const r = await fetch('/api/pos/appointments', {
      method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload)
    });
    return r.json();
  },
  updateAppointment: async (id, fields) => {
    const r = await fetch(`/api/pos/appointments/${id}`, {
      method:'PATCH', headers:{'Content-Type':'application/json'}, body: JSON.stringify(fields)
    });
    return r.json();
  },
  deleteAppointment: async (id) => {
    const r = await fetch(`/api/pos/appointments/${id}`, { method:'DELETE' });
    return r.json();
  },
  searchCustomers: async (q) => {
    const r = await fetch('/api/pos/customers?q=' + encodeURIComponent(q));
    return r.json();
  },
  createCustomer: async (payload) => {
    const r = await fetch('/api/pos/customers', {
      method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload)
    });
    return r.json();
  }
};

export default function PosAgenda() {
  const calRef = useRef(null);

  // ---- modal state
  const [open, setOpen] = useState(false);
  const [mode, setMode] = useState('create'); // 'create' | 'edit'
  const [formErr, setFormErr] = useState(null);

  // ---- appointment fields
  const [apptId, setApptId] = useState(null);
  const [cust, setCust] = useState(null);
  const [date, setDate] = useState('');
  const [time, setTime] = useState('');
  const [dur, setDur] = useState(60);
  const [status, setStatus] = useState('booked');
  const [notes, setNotes] = useState('');

  // ---- customer search/create
  const [tab, setTab] = useState('search');
  const [q, setQ] = useState('');
  const [results, setResults] = useState([]);

  const resetForm = () => {
    setApptId(null); setCust(null);
    setDate(''); setTime(''); setDur(60);
    setStatus('booked'); setNotes(''); setQ(''); setResults([]);
    setFormErr(null); setTab('search');
  };

  const refresh = () => calRef.current?.getApi()?.refetchEvents();

  const eventsFetcher = async (info, success, failure) => {
    try {
      const data = await api.listAppointments(info.startStr, info.endStr);
      const ev = (data.items || []).map(a => ({
        id: String(a.id),
        title: `${a.first_name ?? ''} ${a.last_name ?? ''}`.trim() || `Client #${a.customer_id}`,
        start: a.start_at, // plain "YYYY-MM-DD HH:MM:SS" → treated as local
        end: a.end_at,
        extendedProps: a
      }));
      success(ev);
    } catch (e) { failure?.(e); }
  };

  // ---- open create dialog from selection
  const handleSelect = (sel) => {
    resetForm();
    setMode('create');
    const s = new Date(sel.start);
    const e = new Date(sel.end);
    setDate(toDateInput(s));
    setTime(toTimeInput(s));
    setDur(Math.max(15, Math.round((e - s)/60000)));
    setOpen(true);
  };

  // ---- open edit dialog from click
  const handleEventClick = (info) => {
    resetForm();
    setMode('edit');

    const ext = info.event.extendedProps || {};
    setApptId(Number(info.event.id));
    if (ext.customer_id) {
      setCust({ id: ext.customer_id, first_name: ext.first_name || '', last_name: ext.last_name || '' });
    }

    const s = info.event.start ? new Date(info.event.start) : new Date();
    const e = info.event.end   ? new Date(info.event.end)   : new Date(s.getTime() + 60*60000);

    setDate(toDateInput(s));
    setTime(toTimeInput(s));
    setDur(Math.max(15, Math.round((e - s) / 60000)));

    setStatus(ext.status || 'booked');
    setNotes(ext.notes_public || '');

    setOpen(true);
  };

  // ---- build a full PATCH payload from a calendar event
  const buildPayloadFromEvent = (event) => {
    const ext = event.extendedProps || {};
    const start = event.start;
    const end = event.end || addMin(start, 60);

    return {
      customer_id: ext.customer_id ?? null,
      status: ext.status ?? 'booked',
      notes_public: ext.notes_public ?? null,
      start_at: toSql(start), // LOCAL
      end_at: toSql(end)      // LOCAL
    };
  };

  // ---- drag (move) handler
  const handleEventDrop = async (info) => {
    try {
      const id = Number(info.event.id);
      const payload = buildPayloadFromEvent(info.event);
      const res = await api.updateAppointment(id, payload);
      if (!res?.ok) {
        console.warn('eventDrop PATCH failed:', res);
        alert(res?.error || "Impossible de déplacer le rendez-vous.");
        info.revert();
        return;
      }
      refresh();
    } catch (e) {
      console.error('eventDrop error:', e);
      info.revert();
    }
  };

  // ---- resize (change duration) handler
  const handleEventResize = async (info) => {
    try {
      const id = Number(info.event.id);
      const payload = buildPayloadFromEvent(info.event);
      const res = await api.updateAppointment(id, payload);
      if (!res?.ok) {
        console.warn('eventResize PATCH failed:', res);
        alert(res?.error || "Impossible de redimensionner le rendez-vous.");
        info.revert();
        return;
      }
      refresh();
    } catch (e) {
      console.error('eventResize error:', e);
      info.revert();
    }
  };

  // ---- customer helpers
  const doSearch = async (qq) => {
    if (!qq || qq.trim().length < 2) { setResults([]); return; }
    const data = await api.searchCustomers(qq.trim());
    setResults(data.items || []);
  };
  const fastCreateCustomer = async () => {
    const d = await api.createCustomer({ first_name:'Client', last_name:'SansNom', phone:null, email:null, gdpr_ok:1 });
    if (d.ok) setCust({ id:d.id, first_name:'Client', last_name:'SansNom' });
    else setFormErr(d.error || 'Erreur client');
  };

  // ---- save create/edit from modal
  const save = async () => {
    setFormErr(null);
    if (!cust?.id) return setFormErr('Sélectionnez un client.');
    if (!date || !time) return setFormErr('Date & heure requises.');

    const start = fromSql(`${date} ${time}:00`);       // LOCAL
    const end = addMin(start, Number(dur) || 60);      // LOCAL

    const payload = {
      customer_id: cust.id,
      start_at: toSql(start), // LOCAL
      end_at: toSql(end),     // LOCAL
      status,
      notes_public: notes || null
    };

    const res = mode === 'create'
      ? await api.createAppointment(payload)
      : await api.updateAppointment(apptId, payload);

    if (!res.ok && mode === 'create') { setFormErr(res.error || 'Erreur création'); return; }
    if (!res.ok && mode === 'edit')   { setFormErr(res.error || 'Erreur mise à jour'); return; }

    setOpen(false);
    refresh();
  };

  const remove = async () => {
    if (!apptId) return;
    const yes = confirm('Supprimer ce rendez-vous ?');
    if (!yes) return;
    const res = await api.deleteAppointment(apptId);
    if (!res.ok && res.error) { alert('Erreur: ' + res.error); return; }
    setOpen(false);
    refresh();
  };

  // ---- modal markup
  const Modal = useMemo(() => open && (
    <div className="modal d-block" tabIndex="-1" role="dialog" style={{background:'rgba(0,0,0,0.35)'}}>
      <div className="modal-dialog modal-dialog-scrollable">
        <div className="modal-content">
          <div className="modal-header">
            <h5 className="modal-title">{mode === 'create' ? 'Nouveau rendez-vous' : `Modifier RDV #${apptId}`}</h5>
            <button type="button" className="btn-close" onClick={()=>setOpen(false)} />
          </div>
          <div className="modal-body">
            {formErr && <div className="alert alert-danger py-1 small">{formErr}</div>}

            {/* Client picker */}
            <div className="mb-3">
              <div className="d-flex justify-content-between align-items-center">
                <label className="form-label mb-1">Client</label>
                {!!cust && <span className="badge text-bg-light">#{cust.id}</span>}
              </div>

              {!cust ? (
                <>
                  <ul className="nav nav-tabs mb-2">
                    <li className="nav-item">
                      <button className={`nav-link ${tab==='search'?'active':''}`} onClick={()=>setTab('search')}>Rechercher</button>
                    </li>
                    <li className="nav-item">
                      <button className={`nav-link ${tab==='create'?'active':''}`} onClick={()=>setTab('create')}>Créer</button>
                    </li>
                  </ul>

                  {tab === 'search' && (
                    <>
                      <div className="input-group mb-2">
                        <input className="form-control" placeholder="Nom, téléphone, email…" value={q}
                               onChange={e=>{ setQ(e.target.value); doSearch(e.target.value); }} />
                        <button className="btn btn-outline-secondary" onClick={()=>doSearch(q)}>OK</button>
                      </div>
                      <div className="list-group small" style={{maxHeight:200, overflowY:'auto'}}>
                        {results.map(r => (
                          <button key={r.id} className="list-group-item list-group-item-action"
                                  onClick={()=>setCust(r)}>
                            {r.last_name} {r.first_name} — {r.phone || r.email || '—'}
                          </button>
                        ))}
                        {!results.length && q.trim().length>=2 && (
                          <div className="text-muted small p-2">Aucun résultat…</div>
                        )}
                      </div>
                    </>
                  )}

                  {tab === 'create' && (
                    <div className="d-flex gap-2">
                      <button className="btn btn-sm btn-outline-primary" onClick={fastCreateCustomer}>
                        + Création rapide
                      </button>
                      <span className="small text-muted align-self-center">Client SansNom (modifiable ensuite)</span>
                    </div>
                  )}
                </>
              ) : (
                <div className="d-flex justify-content-between align-items-center">
                  <div>
                    <div className="fw-semibold">{cust.last_name} {cust.first_name}</div>
                    <div className="small text-muted">ID {cust.id}</div>
                  </div>
                    <button className="btn btn-sm btn-outline-danger" onClick={()=>setCust(null)}>Changer</button>
                </div>
              )}
            </div>

            {/* Date / time / duration */}
            <div className="row g-2 mb-2">
              <div className="col-5">
                <label className="form-label small">Date</label>
                <input type="date" className="form-control" value={date} onChange={e=>setDate(e.target.value)} />
              </div>
              <div className="col-4">
                <label className="form-label small">Heure</label>
                <input type="time" step="1800" className="form-control" value={time} onChange={e=>setTime(e.target.value)} />
              </div>
              <div className="col-3">
                <label className="form-label small">Durée (minutes)</label>
                <input
                  type="number"
                  min="15"
                  step="15"
                  className="form-control"
                  value={dur}
                  onChange={e => setDur(e.target.value)}
                />
              </div>
            </div>

            <div className="mb-2">
              <label className="form-label small">Statut</label>
              <select className="form-select" value={status} onChange={e=>setStatus(e.target.value)}>
                <option value="booked">booked</option>
                <option value="done">done</option>
                <option value="cancelled">cancelled</option>
                <option value="no-show">no-show</option>
              </select>
            </div>

            <div>
              <label className="form-label small">Notes (visibles)</label>
              <textarea className="form-control" rows={2} value={notes} onChange={e=>setNotes(e.target.value)} />
            </div>
          </div>
          <div className="modal-footer">
            {mode === 'edit' && (
              <button className="btn btn-outline-danger me-auto" onClick={remove}>Supprimer</button>
            )}
            <button className="btn btn-secondary" onClick={()=>setOpen(false)}>Fermer</button>
            <button className="btn btn-primary" onClick={save}>{mode === 'create' ? 'Créer' : 'Enregistrer'}</button>
          </div>
        </div>
      </div>
    </div>
  ), [open, mode, apptId, cust, date, time, dur, status, notes, q, results, tab, formErr]);

  return (
    <>
      <FullCalendar
        ref={calRef}
        plugins={[dayGridPlugin, timeGridPlugin, interactionPlugin]}
        initialView="timeGridWeek"
        headerToolbar={{ left:'prev,next today', center:'title', right:'dayGridMonth,timeGridWeek,timeGridDay' }}
        slotMinTime="08:00:00"
        slotMaxTime="20:00:00"
        locale="fr"
        timeZone="local"      // ← make intent explicit
        nowIndicator

        // create by selecting
        selectable
        selectMirror
        selectAllow={() => true}
        select={handleSelect}

        // edit dialog on click
        eventClick={handleEventClick}

        // drag & resize, saved to DB
        editable
        eventStartEditable
        eventDurationEditable
        eventDrop={handleEventDrop}
        eventResize={handleEventResize}

        // events
        events={eventsFetcher}
        height="auto"
      />
      {Modal}
    </>
  );
}
