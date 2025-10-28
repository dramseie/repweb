/* Full modified PosApp.jsx
   - Timer ticks every second (setInterval) so seconds are visible.
   - saveOrder() captures the timer elapsed (seconds persisted in localStorage) and passes rounded minutes to PaymentDialog via elapsedMinutesInitial.
   - PaymentDialog uses elapsedMinutesInitial to pre-fill the "Temps écoulé" field.
*/
import React, { useEffect, useMemo, useRef, useState, useCallback } from 'react';
import PaymentDialog from './PaymentDialog';
import OrderDialog from './OrderDialog';
import TimerButton from './TimerButton';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

/* =========================
   Helpers (money, dates, addr)
   ========================= */
const EUR = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR', minimumFractionDigits: 2 });
const toInt = (n) => Number.isFinite(+n) ? Math.trunc(+n) : 0;               // normalize to int (for cents/qty)
const cents = (n) => toInt(n);                                               // keep integer cents

const fmtMoney = (c) => EUR.format((toInt(c) || 0) / 100);

const nowIso = () => new Date().toISOString();

/** robust € string → cents (int) */
const euroToCents = (val) => {
  if (val == null) return 0;
  const s = String(val).trim()
    .replace(/\s/g, '')               // remove spaces
    .replace(/[€]/g, '')              // drop € sign
    .replace(',', '.');               // fr decimals → dot
  const n = Number(s);
  if (!Number.isFinite(n) || n < 0) return 0;
  return Math.round(n * 100);
};

const uid = () => 'divers-' + Math.random().toString(36).slice(2, 9);

const emptyAddr = {
  street: '', house_number: '', postcode: '', city: '',
  region: '', country: '', formatted: '', geo: null,
  place_id: '', source: '', updated_at: null
};
const fmtAddrBlock = (a) => {
  if (!a) return '';
  const line1 = [a.house_number, a.street].filter(Boolean).join(' ').trim();
  const line2 = [a.postcode, a.city].filter(Boolean).join(' ').trim();
  const line3 = a.country || '';
  return [line1, line2, line3].filter(Boolean).join('\n');
};

// ---- Read realizations from the mount div (set by Twig) ----
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
      colour_code: String(x.colour_code ?? '').toLowerCase(),
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
  if (isNaN(d)) return '';
  const pad = n => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:00`;
};
const nowSql = () =>
  new Date(Date.now() - new Date().getTimezoneOffset() * 60000)
    .toISOString().slice(0, 19).replace('T', ' ');

// ---------- Pastel color mapping + hook to sort/filter ----------
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

/* =========================
   Address Picker Modal (Leaflet + Nominatim proxy)
   ========================= */

// Default map center: Hagenthal-le-Bas 🇫🇷
const HAGENTHAL_CENTER = [47.5385, 7.5140];
const HAGENTHAL_ZOOM   = 14;

function AddressPickerModal({ show, onClose, onPick, initial }) {
  const mapRef = useRef(null);
  const nodeRef = useRef(null);
  const markerRef = useRef(null);
  const clickHandlerRef = useRef(null);
  const [query, setQuery] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const searchAbortRef = useRef(null);
  const reverseAbortRef = useRef(null);

  // init / cleanup map
  useEffect(() => {
    if (!show || !nodeRef.current) return;

    if (!mapRef.current) {
      mapRef.current = L.map(nodeRef.current, { zoomControl: true }).setView(HAGENTHAL_CENTER, HAGENTHAL_ZOOM);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19, attribution: '&copy; OpenStreetMap'
      }).addTo(mapRef.current);

      // click handler (kept in ref to remove cleanly)
      clickHandlerRef.current = async (e) => {
        setBusy(true); setError('');
        try {
          // abort any previous reverse
          reverseAbortRef.current?.abort();
          reverseAbortRef.current = new AbortController();

          const lat = e.latlng.lat, lon = e.latlng.lng;
          if (!markerRef.current) markerRef.current = L.marker(e.latlng).addTo(mapRef.current);
          else markerRef.current.setLatLng(e.latlng);

          const r = await fetch(`/api/geocode/reverse?lat=${lat}&lon=${lon}`, { signal: reverseAbortRef.current.signal });
          if (!r.ok) throw new Error(`HTTP ${r.status}`);
          const j = await r.json();
          if (!j?.address) throw new Error('Aucune adresse trouvée');

          const a = j.address || {};
          const addr = {
            street: a.road || a.pedestrian || a.footway || '',
            house_number: a.house_number || '',
            postcode: a.postcode || '',
            city: a.city || a.town || a.village || a.hamlet || '',
            region: a.state || '',
            country: a.country_code ? a.country_code.toUpperCase() : (a.country || ''),
            formatted: j.display_name || '',
            geo: { lat, lng: lon },
            place_id: j.place_id ? `nominatim:${j.place_id}` : '',
            source: 'nominatim',
            updated_at: nowIso()
          };
          onPick(addr);
        } catch (e2) {
          if (e2.name !== 'AbortError') setError(e2.message || 'Reverse-geocoding indisponible');
        } finally {
          setBusy(false);
        }
      };

      mapRef.current.on('click', clickHandlerRef.current);
    }

    // ensure size after modal becomes visible
    setTimeout(() => { try { mapRef.current?.invalidateSize(); } catch {} }, 100);

    // center on existing address if any
    if (initial?.geo) {
      const { lat, lng } = initial.geo;
      mapRef.current.setView([lat, lng], 17);
      if (!markerRef.current) markerRef.current = L.marker([lat, lng]).addTo(mapRef.current);
      else markerRef.current.setLatLng([lat, lng]);
    } else {
      mapRef.current.setView(HAGENTHAL_CENTER, HAGENTHAL_ZOOM);
    }

    // cleanup when modal closes/unmounts
    return () => {
      searchAbortRef.current?.abort();
      reverseAbortRef.current?.abort();
      if (mapRef.current && clickHandlerRef.current) mapRef.current.off('click', clickHandlerRef.current);
      // keep map instance for next open to avoid re-creating tiles; don’t destroy node
    };
  }, [show, initial, onPick]);

  const onSubmitSearch = async (e) => {
    e.preventDefault();
    const raw = (query || '').trim();
    if (!raw) return;
    setBusy(true); setError('');
    try {
      // abort previous search
      searchAbortRef.current?.abort();
      searchAbortRef.current = new AbortController();

      // Try as-is
      let r = await fetch(`/api/geocode/search?q=${encodeURIComponent(raw)}&limit=8`, { signal: searchAbortRef.current.signal });
      if (!r.ok) throw new Error(`HTTP ${r.status}`);
      let j = await r.json();

      // Fallback: add ", France"
      if (!Array.isArray(j) || j.length === 0) {
        r = await fetch(`/api/geocode/search?q=${encodeURIComponent(`${raw}, France`)}&limit=8`, { signal: searchAbortRef.current.signal });
        if (!r.ok) throw new Error(`HTTP ${r.status}`);
        j = await r.json();
      }

      if (!Array.isArray(j) || j.length === 0) { setError('Aucun résultat'); return; }

      // Prefer result with city/town/village match
      const hit = j.find(x => {
        const city = (x.address?.village || x.address?.town || x.address?.city || '').toLowerCase();
        return city && raw.toLowerCase().includes(city);
      }) || j[0];

      const lat = parseFloat(hit.lat), lon = parseFloat(hit.lon);
      mapRef.current.setView([lat, lon], 18);
      if (!markerRef.current) markerRef.current = L.marker([lat, lon]).addTo(mapRef.current);
      else markerRef.current.setLatLng([lat, lon]);

      const a = hit.address || {};
      const addr = {
        street: a.road || a.pedestrian || a.footway || '',
        house_number: a.house_number || '',
        postcode: a.postcode || '',
        city: a.city || a.town || a.village || '',
        region: a.state || '',
        country: a.country_code ? a.country_code.toUpperCase() : (a.country || ''),
        formatted: hit.display_name || '',
        geo: { lat, lng: lon },
        place_id: hit.place_id ? `nominatim:${hit.place_id}` : '',
        source: 'nominatim',
        updated_at: nowIso()
      };
      onPick(addr);
    } catch (e2) {
      if (e2.name !== 'AbortError') setError(e2.message || 'Recherche échouée');
    } finally {
           setBusy(false);
    }
  };

  return (
    <div className={`modal ${show ? 'd-block' : ''}`} tabIndex="-1" style={{ background:'rgba(0,0,0,.5)' }} aria-hidden={!show}>
      <div className="modal-dialog modal-xl modal-dialog-centered">
        <div className="modal-content">
          <div className="modal-header">
            <h5 className="modal-title">Choisir une adresse</h5>
            <button type="button" className="btn-close" onClick={onClose} aria-label="Fermer"/>
          </div>
          <div className="modal-body">
            <form className="d-flex gap-2 mb-2" onSubmit={onSubmitSearch}>
              <input
                className="form-control"
                placeholder="Ex: hagenthal-le-bas, 10 rue ..."
                value={query}
                onChange={e=>setQuery(e.target.value)}
                aria-label="Rechercher une adresse"
              />
              <button className="btn btn-primary" disabled={busy}>Rechercher</button>
            </form>
            {error && <div className="alert alert-warning py-2">{error}</div>}
            <div ref={nodeRef} style={{ height: 500, width: '100%' }}/>
            <div className="form-text mt-2">
              Astuce: cliquez sur la carte pour sélectionner précisément l’adresse.
            </div>
          </div>
          <div className="modal-footer">
            <button className="btn btn-outline-secondary" onClick={onClose}>Fermer</button>
          </div>
        </div>
      </div>
    </div>
  );
}

/* =========================
   Divers (free line) modal
   ========================= */
function DiversModal({ show, onClose, onAdd, defaultTaxRate = 0 }) {
  const [label, setLabel] = useState('');
  const [amount, setAmount] = useState('0');
  const [tax, setTax] = useState(defaultTaxRate);
  const [qty, setQty] = useState(1);
  const [err, setErr] = useState(null);

  useEffect(() => {
    if (show) {
      setErr(null);
      setLabel('');
      setAmount('0');
      setTax(defaultTaxRate);
      setQty(1);
    }
  }, [show, defaultTaxRate]);

  const add = () => {
    const labelT = (label || '').trim();
    const centsVal = euroToCents(amount);
    if (!labelT) { setErr('Veuillez saisir un libellé.'); return; }
    if (centsVal <= 0) { setErr('Le montant doit être > 0.'); return; }
    if ((Number(qty) || 0) <= 0) { setErr('La quantité doit être ≥ 1.'); return; }
    onAdd({
      id: uid(),
      isCustom: true,
      name: `Divers — ${labelT}`,
      unit: centsVal,
      rate: Number(tax) || 0,
      qty: Math.max(1, Number(qty) || 1)
    });
    onClose();
  };

  return (
    <div className={`modal ${show ? 'd-block' : ''}`} tabIndex="-1" style={{ background:'rgba(0,0,0,.5)' }}>
      <div className="modal-dialog">
        <div className="modal-content">
          <div className="modal-header">
            <h5 className="modal-title">Ajouter un poste “Divers”</h5>
            <button type="button" className="btn-close" onClick={onClose} aria-label="Fermer"/>
          </div>
          <div className="modal-body">
            {err && <div className="alert alert-danger py-1 small">{err}</div>}
            <div className="mb-2">
              <label className="form-label small">Libellé</label>
              <input className="form-control" value={label} onChange={e=>setLabel(e.target.value)} placeholder="Ex: Réparation, supplément, remise négative…" />
            </div>
            <div className="row g-2">
              <div className="col-5">
                <label className="form-label small">Montant (€)</label>
                <input className="form-control" value={amount} onChange={e=>setAmount(e.target.value)} />
              </div>
              <div className="col-4">
                <label className="form-label small">TVA</label>
                <select className="form-select" value={tax} onChange={e=>setTax(e.target.value)}>
                  {[0, 2.1, 5.5, 10, 20].map(t => (
                    <option key={t} value={t}>{t}%</option>
                  ))}
                </select>
              </div>
              <div className="col-3">
                <label className="form-label small">Qté</label>
                <input type="number" min="1" className="form-control" value={qty} onChange={e=>setQty(e.target.value)} />
              </div>
            </div>
          </div>
          <div className="modal-footer">
            <button className="btn btn-secondary" onClick={onClose}>Annuler</button>
            <button className="btn btn-primary" onClick={add}>Ajouter</button>
          </div>
        </div>
      </div>
    </div>
  );
}

/* =========================
   Main POS App
   ========================= */

/* == Timer helpers & components == */
const fmtHMS = (totalSec) => {
  const s = Math.max(0, Math.floor(totalSec || 0));
  const h = String(Math.floor(s / 3600)).padStart(2, '0');
  const m = String(Math.floor((s % 3600) / 60)).padStart(2, '0');
  const ss = String(s % 60).padStart(2, '0');
  return `${h}:${m}:${ss}`;
};

// Small localStorage-backed engine with setInterval so seconds visibly update
function usePosTimer(storageKey) {
  const key = `posTimer:${storageKey || 'default'}`;
  const [status, setStatus] = useState('idle'); // idle | running | stopped
  const [elapsed, setElapsed] = useState(0);    // seconds (integer)
  const intervalRef = useRef(null);

  // load persisted state
  useEffect(() => {
    try {
      const raw = localStorage.getItem(key);
      if (raw) {
        const j = JSON.parse(raw);
        setStatus(j.status ?? 'idle');
        setElapsed(Math.round(Number(j.elapsed ?? 0)));
      } else {
        setStatus('idle'); setElapsed(0);
      }
    } catch {
      // ignore
    }
    return () => { if (intervalRef.current) { clearInterval(intervalRef.current); intervalRef.current = null; } };
  }, [key]);

  const persist = useCallback((st = status, el = elapsed) => {
    try {
      localStorage.setItem(key, JSON.stringify({ status: st, elapsed: el }));
    } catch {}
  }, [key, status, elapsed]);

  // start timer: if persisted state says running we also resync using the saved elapsed
  const start = useCallback(() => {
    if (intervalRef.current) return;
    // mark running and persist
    setStatus('running');
    // use functional update to ensure we have latest elapsed
    setElapsed((prev) => {
      persist('running', prev);
      return prev;
    });
    // every second increment elapsed by 1 so UI visibly updates seconds
    intervalRef.current = setInterval(() => {
      setElapsed((prev) => {
        const next = prev + 1;
        persist('running', next);
        return next;
      });
    }, 1000);
  }, [persist]);

  const stop = useCallback(() => {
    if (intervalRef.current) {
      clearInterval(intervalRef.current);
      intervalRef.current = null;
    }
    setStatus('stopped');
    setElapsed((prev) => {
      persist('stopped', prev);
      return prev;
    });
  }, [persist]);

  const reset = useCallback(() => {
    if (intervalRef.current) {
      clearInterval(intervalRef.current);
      intervalRef.current = null;
    }
    setStatus('idle');
    setElapsed(0);
    persist('idle', 0);
  }, [persist]);

  // if persisted status is running after mount, resume visual ticking
  useEffect(() => {
    if (status === 'running' && !intervalRef.current) {
      // ensure interval runs to show seconds
      intervalRef.current = setInterval(() => {
        setElapsed((prev) => {
          const next = prev + 1;
          persist('running', next);
          return next;
        });
      }, 1000);
    }
    return () => {
      // cleanup on unmount
      if (intervalRef.current) { clearInterval(intervalRef.current); intervalRef.current = null; }
    };
  }, [status, persist]);

  return { status, elapsed, start, stop, reset };
}

// === Timer UI (exposes stop() via ref) ===
const Timer = React.forwardRef(function Timer({ storageKey, onStopped, disabled }, ref) {
  const { status, elapsed, start, stop, reset } = usePosTimer(storageKey);

  React.useImperativeHandle(ref, () => ({
    stopAndReturn: () => {
      const before = Math.round(elapsed);
      stop();
      const t = fmtHMS(before);
      return t; // display format
    },
    hardStop: () => stop(),
  }), [stop, elapsed]);

  const doStop = () => {
    const before = Math.round(elapsed);
    stop();
    const hms = fmtHMS(before);
    onStopped?.(hms, before);
  };

  return (
    <div className="d-flex align-items-center gap-2">
      <div className="fw-bold" style={{ minWidth: 90, fontVariantNumeric:'tabular-nums' }}>
        {fmtHMS(elapsed)}
      </div>

      <button className="btn btn-sm btn-success"
              onClick={start}
              disabled={disabled || status==='running'} aria-label="Start timer">▶︎</button>

      <button className="btn btn-sm btn-danger"
              onClick={doStop}
              disabled={disabled || status!=='running'} aria-label="Stop timer">■</button>

      <button className="btn btn-sm btn-outline-secondary"
              onClick={reset}
              disabled={disabled || (status==='idle' && elapsed===0)} aria-label="Reset timer">⟲</button>
    </div>
  );
});

/* =========================
   POS App
   ========================= */
export default function PosApp() {
  const [loading, setLoading] = useState(true);
  const [cats, setCats] = useState({});
  const [cart, setCart] = useState([]);

  // 👤 Customer state (persisted)
  const [customer, setCustomer] = useState(() => {
    try {
      const raw = localStorage.getItem('ongleri:selectedCustomer');
      return raw ? JSON.parse(raw) : null;
    } catch {
      return null;
    }
  });

  // keep in sync with localStorage
  useEffect(() => {
    try {
      if (customer) localStorage.setItem('ongleri:selectedCustomer', JSON.stringify(customer));
      else localStorage.removeItem('ongleri:selectedCustomer');
    } catch {}
  }, [customer]);

  // Optional: cross-tab sync
  useEffect(() => {
    const onStorage = (e) => {
      if (e.key === 'ongleri:selectedCustomer') {
        try {
          setCustomer(e.newValue ? JSON.parse(e.newValue) : null);
        } catch {}
      }
    };
    window.addEventListener('storage', onStorage);
    return () => window.removeEventListener('storage', onStorage);
  }, []);

  // 🔧 Auto-load detail/history on startup and when customer changes
  const [custDetail, setCustDetail] = useState(null);
  const [activeAppointmentId, setActiveAppointmentId] = useState(null);
  useEffect(() => {
    if (customer?.id) {
      loadCustomerDetail(customer.id);
    } else {
      setCustDetail(null);
      setActiveAppointmentId(null);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [customer?.id]);

  const [custTab, setCustTab] = useState('search');
  const [custQuery, setCustQuery] = useState('');
  const [custResults, setCustResults] = useState([]);

  // 👥 All customers tab state
  const [allCustomers, setAllCustomers] = useState([]);
  const [allLoading, setAllLoading] = useState(false);
  const [allErr, setAllErr] = useState(null);
  const [allFilter, setAllFilter] = useState('');

  // 🔹 New RDV form
  const [rdv, setRdv] = useState({date: '', time: '', durationMin: 120, notes_public: '', status: 'booked'});
  const setR = (k, v) => setRdv(prev => ({ ...prev, [k]: v }));
  const [rdvErr, setRdvErr] = useState(null);

  // 🧩 Réalisations selection (metadata only, no price)
  const [selectedReals, setSelectedReals] = useState([]); // [{code,label}]
  const allReals = initialRealisations; // from Twig
  const sortedReals = useSortedReals(allReals);

  // 📝 Order notes
  const [techNotes, setTechNotes] = useState('');

  // 💳 Payment dialog state
  const [payOpen, setPayOpen] = useState(false);
  const [currentOrder, setCurrentOrder] = useState(null);

  // ✏️ EDIT MODAL STATE
  const [editOpen, setEditOpen] = useState(false);
  const [editId, setEditId] = useState(null);
  const [editForm, setEditForm] = useState({
    first_name: '', last_name: '', phone: '', email: '', notes_public: '', gdpr_ok: true,
    address: null
  });
  const [editErr, setEditErr] = useState(null);
  const [showAddrPicker, setShowAddrPicker] = useState(false);
  const EF = (k, v) => setEditForm(p => ({ ...p, [k]: v }));

  // Divers modal state
  const [diversOpen, setDiversOpen] = useState(false);
  const defaultDiversTax = useMemo(() => (cart.length ? (cart[cart.length - 1].rate || 0) : 0), [cart]);

  const minsToHHMM = (m) => {
    const mm = Math.max(0, Number(m) || 0);
    const h = Math.floor(mm / 60);
    const m2 = mm % 60;
    return `${String(h).padStart(2, '0')}:${String(m2).padStart(2, '0')}`;
  };
  const hhmmToMins = (hhmm) => {
    if (!hhmm) return 0;
    const [h, m] = hhmm.split(':').map(n => Number(n) || 0);
    return h * 60 + m;
  };
  const sqlToDateTime = (s) => {
    const d = new Date(String(s).replace(' ', 'T'));
    if (isNaN(d)) return { date: '', time: '' };
    const date = d.toISOString().slice(0, 10);
    const time = d.toTimeString().slice(0, 5);
    return { date, time };
  };
  const diffMinutes = (startSql, endSql) => {
    const a = new Date(String(startSql).replace(' ', 'T'));
    const b = new Date(String(endSql).replace(' ', 'T'));
    if (isNaN(a) || isNaN(b)) return 60;
    return Math.max(0, Math.round((b - a) / 60000));
  };

  // 🔎 Order dialog state
  const [showOrderDlg, setShowOrderDlg] = useState(false);
  const [orderDlgData, setOrderDlgData] = useState(null);
  const openOrderDialog = async (orderId) => {
    try {
      const r = await fetch(`/api/pos/orders/${orderId}`);
      if (!r.ok) throw new Error(`HTTP ${r.status}`);
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
      try {
        setLoading(true);
        const r = await fetch('/api/pos/items');
        if (!r.ok) throw new Error(`HTTP ${r.status}`);
        const data = await r.json();
        setCats(data.categories || {});
      } catch (e) {
        console.error(e);
        setCats({});
      } finally {
               setLoading(false);
      }
    })();
  }, []);

  // --- debounced customer search with abort ---
  const searchAbort = useRef(null);
  const searchCustomers = useCallback((q) => {
    const query = (q || '').trim();
    if (query.length < 2) { setCustResults([]); return; }
    searchAbort.current?.abort();
    const ctrl = new AbortController();
    searchAbort.current = ctrl;

    // debounce ~250ms
    const handle = setTimeout(async () => {
      try {
        const r = await fetch('/api/pos/customers?q=' + encodeURIComponent(query), { signal: ctrl.signal });
        if (!r.ok) throw new Error(`HTTP ${r.status}`);
        const data = await r.json();
        setCustResults(data.items || []);
      } catch (e) {
        if (e.name !== 'AbortError') console.error(e);
      }
    }, 250);

    return () => {
      clearTimeout(handle);
      ctrl.abort();
    };
  }, []);

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
    try {
      const r = await fetch(`/api/pos/customers/${id}`);
      if (!r.ok) throw new Error(`HTTP ${r.status}`);
      const d = await r.json();
      if (d.customer) {
        setCustDetail(d);
        if (Object.prototype.hasOwnProperty.call(d, 'active_appointment_id')) {
          setActiveAppointmentId(d.active_appointment_id || null);
        }
      }
    } catch (e) {
      console.error(e);
      setCustDetail(null);
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

  async function updateRdv() {
    setRdvErr(null);
    if (!customer?.id) { setRdvErr('Sélectionnez un client.'); return; }
    if (!activeAppointmentId) { setRdvErr('Sélectionnez un rendez-vous.'); return; }
    if (!rdv.date || !rdv.time) { setRdvErr('Date et heure sont requises.'); return; }

    const start = mkDt(rdv.date, rdv.time);
    const startDate = new Date(`${rdv.date}T${rdv.time}`);
    const end = mkDt(
      rdv.date,
      new Date(startDate.getTime() + (Number(rdv.durationMin) || 60) * 60000)
        .toTimeString().slice(0, 5)
    );

    const payload = {
      start_at: start,
      end_at: end,
      status: rdv.status || 'booked',
      notes_public: rdv.notes_public || null,
    };

    const res = await fetch(`/api/pos/appointments/${activeAppointmentId}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    if (!res.ok) {
      const t = await res.json().catch(() => ({}));
      setRdvErr(t.error || `HTTP ${res.status}`);
      return;
    }
    if (customer?.id) await loadCustomerDetail(customer.id);
  }

  const selectCustomer = async (c) => {
    setCustomer(c);
    clearRdvSelectionAndForm(); // also reset RDV form
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
  const addDivers = (line) => {
    setCart(prev => {
      const wasEmpty = prev.length === 0;
      const next = [...prev, line];
      if (wasEmpty && activeAppointmentId) markRealStart(activeAppointmentId);
      return next;
    });
  };
  const removeLine = (id) => setCart(prev => prev.filter(x => x.id !== id));
  const inc = (id) => setCart(prev => prev.map(x => x.id === id ? { ...x, qty: x.qty + 1 } : x));
  const dec = (id) => setCart(prev => prev.map(x => x.id === id ? { ...x, qty: Math.max(1, x.qty - 1) } : x));

  const totals = useMemo(() => {
    let net = 0, tax = 0;
    for (const l of cart) {
      const line = cents(l.unit) * (l.qty || 0);
      net += line;
      tax += Math.round(line * (Number(l.rate) / 100));
    }
    const total = net + tax;
    return { net, tax, total };
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

  // ⏱ storage key follows selected RDV, or global when none
  const timerStorageKey = activeAppointmentId ? `appt-${activeAppointmentId}` : 'global';

  // Create order, then open modal
  const timerRef = useRef(null);
  const saveOrder = async () => {
    if (!cart.length) return;

    // Capture timer elapsed (in minutes) by reading persisted value.
    // We stop the visible timer (hardStop) so the persisted elapsed is stable.
    let elapsedMinutesInitial = null;
    try {
      try { timerRef.current?.hardStop?.(); } catch {}
      const raw = localStorage.getItem(`posTimer:${timerStorageKey}`);
      if (raw) {
        try {
          const j = JSON.parse(raw);
          const secs = Number(j.elapsed ?? 0);
          if (Number.isFinite(secs)) elapsedMinutesInitial = Math.max(0, Math.round(secs / 60));
        } catch {}
      }
    } catch (e) {
      console.error('Failed to read persisted timer', e);
    }

    if (activeAppointmentId) {
      await fetch(`/api/pos/appointments/${activeAppointmentId}`, {
        method:'PATCH', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ real_end_at: nowSql(), status: 'done' })
      });
    }

    const items = cart.filter(l => !l.isCustom).map(l => ({ item_id: l.id, qty: l.qty }));
    const customItems = cart.filter(l => l.isCustom).map(l => ({
      label: l.name.replace(/^Divers —\s*/, ''),
      unit_cents: l.unit,
      tax_rate: l.rate,
      qty: l.qty
    }));

    const payload = {
      items,
      ...(customItems.length ? { custom_items: customItems } : {}),
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
      let startIso2 = null;
      if (apptId && custDetail?.appointments?.length) {
        const a = custDetail.appointments.find(x => x.id === apptId);
        const start = a?.start_at || null;
        if (start) startIso2 = start.replace(' ', 'T');
      }
      setCurrentOrder({
        id: data.order_id,
        total_cents: data.total_cents,
        appointment_id: apptId,
        rendezVousStartIso: startIso2,
        elapsedMinutesInitial: elapsedMinutesInitial,
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

  // 🔄 Clear selection + reset form (used by toggle and by “Nouveau rendez-vous”)
  const clearRdvSelectionAndForm = () => {
    setActiveAppointmentId(null);
    setRdv({ date: '', time: '', durationMin: 120, notes_public: '', status: 'booked' });
  };

  // 🔁 TOGGLE selection: click same row again → unselect & clear form
  const selectAppt = (a) => {
    if (activeAppointmentId === a.id) {
      // toggle OFF
      clearRdvSelectionAndForm();
      return;
    }
    // select & fill
    setActiveAppointmentId(a.id);
    const { date, time } = sqlToDateTime(a.start_at);
    const duration = diffMinutes(a.start_at, a.end_at);
    setRdv({
      date,
      time,
      durationMin: duration || 60,
      notes_public: a.notes_public || '',
      status: a.status || 'booked',
    });
  };

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
    try {
      const r = await fetch(`/api/pos/customers/${id}`);
      if (!r.ok) throw new Error(`HTTP ${r.status}`);
      const d = await r.json();
      if (!d?.customer) throw new Error('Chargement du client impossible.');
      const c = d.customer;
      let parsedAddr = null;
      try { parsedAddr = c.address ? JSON.parse(c.address) : null; } catch { parsedAddr = null; }
      setEditId(c.id);
      setEditForm({
        first_name: c.first_name || '',
        last_name: c.last_name || '',
        phone: c.phone || '',
        email: c.email || '',
        notes_public: c.notes_public || '',
        gdpr_ok: !!c.gdpr_ok,
        address: parsedAddr
      });
      setEditOpen(true);
    } catch (e) {
      setEditErr(e.message || 'Chargement du client impossible.');
      setEditOpen(true);
    }
  }

  async function saveEditCustomer() {
    setEditErr(null);
    const fn = (editForm.first_name || '').trim();
    const ln = (editForm.last_name || '').trim();
    if (!fn || !ln) {
      setEditErr('Prénom et nom sont requis.');
      return;
    }
    const payload = {
      first_name: fn,
      last_name: ln,
      phone: (editForm.phone || '').trim() || null,
      email: (editForm.email || '').trim() || null,
      notes_public: (editForm.notes_public || '').trim() || null,
      gdpr_ok: !!editForm.gdpr_ok,
      address: editForm.address
        ? JSON.stringify({ ...editForm.address, formatted: fmtAddrBlock(editForm.address), updated_at: nowIso() })
        : null
    };
    const r = await fetch(`/api/pos/customers/${editId}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const d = await r.json().catch(() => ({}));
    if (!r.ok) {
      setEditErr(d?.error || 'Enregistrement impossible.');
      return;
    }

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

  // 👉 Quick emoji add for RDV notes
  const QUICK_EMOJIS = [
    { symbol: '✋', title: 'Main' },
    { symbol: '🦶', title: 'Pieds' },
    { symbol: '🙅', title: 'Dépose' },
    { symbol: '🛠️', title: 'Réparation' },
  ];

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

            {/* Timer in header */}
            <div className="d-flex align-items-center gap-3">
              <Timer
                ref={timerRef}
                storageKey={timerStorageKey}
                disabled={false}
                onStopped={() => {}}
              />
              <div className="d-flex gap-2">
                <button className="btn btn-sm btn-outline-primary" onClick={() => setDiversOpen(true)}>+ Divers</button>
                <button className="btn btn-sm btn-outline-secondary" onClick={() => setCart([])} disabled={!cart.length}>Vider</button>
              </div>
            </div>
          </div>

          <div className="card-body">
            {!cart.length && <div className="text-muted">Ajoutez des articles à gauche.</div>}
            {cart.map(line => (
              <div key={line.id} className="d-flex align-items-center border-bottom py-2">
                <div className="flex-grow-1">
                  <div className="fw-semibold">{line.name}</div>
                  <div className="small text-muted">{fmtMoney(line.unit)} • TVA {line.rate}% {line.isCustom ? '• (Divers)' : ''}</div>
                </div>
                <div className="d-flex align-items-center gap-2" aria-label="Quantité">
                  <button className="btn btn-sm btn-outline-secondary" onClick={() => dec(line.id)} aria-label="Diminuer la quantité">-</button>
                  <span className="px-2">{line.qty}</span>
                  <button className="btn btn-sm btn-outline-secondary" onClick={() => inc(line.id)} aria-label="Augmenter la quantité">+</button>
                </div>
                <div className="ms-3 fw-semibold" style={{ width: 130, textAlign: 'right' }}>
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
              <label className="form-label small">Notes concernant la prestation</label>
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
                        onClick={() => { setCustomer(null); setCustDetail(null); clearRdvSelectionAndForm(); }}>
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
                          searchCustomers(v);
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

                      {/* Empty state only when there is a query with no results */}
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

                {/* Nouveau / Modifier RDV */}
                <div className="border rounded p-2 mb-3">
                  <div className="fw-semibold mb-2">
                    {activeAppointmentId ? `Modifier rendez-vous #${activeAppointmentId}` : 'Nouveau rendez-vous'}
                  </div>
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
                      <label className="form-label small">Durée (hh:mm)</label>
                      <input
                        type="time"
                        step="900"
                        className="form-control"
                        value={minsToHHMM(rdv.durationMin)}
                        onChange={(e) => setR('durationMin', hhmmToMins(e.target.value))}
                      />
                    </div>

                    <div className="col-4">
                      <label className="form-label small">Statut</label>
                      <select
                        className="form-select"
                        value={rdv.status}
                        onChange={(e) => setR('status', e.target.value)}
                      >
                        <option value="booked">bookée</option>
                        <option value="done">done</option>
                        <option value="no-show">no-show</option>
                        <option value="cancelled">cancelled</option>
                      </select>
                    </div>

                    <div className="col-12">
                      {/* Label + emoji toolbar */}
                      <div className="d-flex align-items-center justify-content-between">
                        <label className="form-label small mb-0">Notes pour le rendez-vous</label>
                        <div className="d-flex gap-1">
                          {QUICK_EMOJIS.map(e => (
                            <button
                              key={e.symbol}
                              type="button"
                              className="btn btn-sm btn-outline-secondary"
                              title={e.title}
                              onClick={() => {
                                setR("notes_public", (rdv.notes_public || "").includes(e.symbol)
                                  ? rdv.notes_public
                                  : ((rdv.notes_public || "").trim().length
                                    ? rdv.notes_public + " " + e.symbol
                                    : e.symbol)
                                );
                              }}
                            >
                              {e.symbol}
                            </button>
                          ))}
                        </div>
                      </div>

                      <textarea
                        id="rdv-notes"
                        className="form-control mt-1"
                        rows={2}
                        value={rdv.notes_public || ""}
                        onChange={e => setR("notes_public", e.target.value)}
                      />
                    </div>

                    <div className="col-12 d-flex justify-content-end">
                      {activeAppointmentId ? (
                        <>
                          <button className="btn btn-sm btn-primary" onClick={updateRdv}>
                            Mettre à jour
                          </button>
                          <button className="btn btn-sm btn-outline-secondary ms-2" onClick={clearRdvSelectionAndForm}>
                            Nouveau rendez-vous
                          </button>
                        </>
                      ) : (
                        <button className="btn btn-sm btn-primary" onClick={createRdv}>
                          Créer RDV
                        </button>
                      )}
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
                                  .map(a => {
                                    const selected = a.id === activeAppointmentId;
                                    return (
                                      <tr key={a.id}
                                          onClick={() => selectAppt(a)}
                                          className={selected ? 'table-primary' : ''}
                                          style={{ cursor:'pointer' }}
                                          title={selected ? 'Cliquez pour désélectionner' : 'Cliquez pour sélectionner'}>
                                        <td>{fmtDateFr(a.start_at)}</td>
                                        <td>{fmtTimeFr(a.start_at)}</td>
                                        <td>{a.status}</td>
                                        <td className="text-truncate" style={{maxWidth:220}} title={a.notes_public || ''}>{a.notes_public || '—'}</td>
                                      </tr>
                                    );
                                  })
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
                              : <tr><td colSpan={5} className="text-muted">Aucune commande</td></tr>}
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
        amountDueCents={currentOrder?.total_cents ?? totals.total}
        rendezVousAtIso={currentOrder?.rendezVousStartIso || rendezVousAtIso}
        elapsedMinutesInitial={currentOrder?.elapsedMinutesInitial ?? null}
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

                {/* Adresse */}
                <div className="col-12">
                  <div className="d-flex justify-content-between align-items-center mb-1">
                    <label className="form-label small mb-0">Adresse postale</label>
                    <div className="btn-group">
                      <button type="button" className="btn btn-sm btn-outline-primary" onClick={()=>setShowAddrPicker(true)}>
                        Rechercher sur la carte
                      </button>
                      <button type="button" className="btn btn-sm btn-outline-danger" onClick={()=>EF('address', null)}>
                        Effacer
                      </button>
                    </div>
                  </div>
                  <textarea
                    className="form-control"
                    rows={3}
                    readOnly
                    value={fmtAddrBlock(editForm.address) || ''}
                    placeholder="— aucune adresse —"
                  />
                </div>

                <div className="col-12">
                  <label className="form-label small">Notes sur la cliente</label>
                  <textarea className="form-control" rows={2} value={editForm.notes_public || ''} onChange={e=>EF('notes_public', e.target.value)} />
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

      {/* Address Picker modal mount */}
      {showAddrPicker && (
        <AddressPickerModal
          show={showAddrPicker}
          initial={editForm.address}
          onPick={(addr)=>{ EF('address', addr); setShowAddrPicker(false); }}
          onClose={()=>setShowAddrPicker(false)}
        />
      )}

      {/* Divers modal mount */}
      {diversOpen && (
        <DiversModal
          show={diversOpen}
          onClose={()=>setDiversOpen(false)}
          onAdd={addDivers}
          defaultTaxRate={defaultDiversTax}
        />
      )}
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