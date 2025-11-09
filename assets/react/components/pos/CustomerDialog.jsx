import React, { useEffect, useMemo, useRef, useState } from 'react';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

// Utilities
const emptyAddr = {
  street: '', house_number: '', postcode: '', city: '',
  region: '', country: '', formatted: '', geo: null,
  place_id: '', source: '', updated_at: null
};

const HAGENTHAL_CENTER = [47.5385, 7.5140]; // FR-68
const HAGENTHAL_ZOOM = 14;

const STATUS_OPTIONS = [
  { value: 'active', label: 'Actif' },
  { value: 'inactive', label: 'Inactif' },
  { value: 'banned', label: 'Banni' },
  { value: 'test', label: 'Test' },
];


const fmtBlock = (a) => {
  if (!a) return '';
  const line1 = [a.house_number, a.street].filter(Boolean).join(' ').trim();
  const line2 = [a.postcode, a.city].filter(Boolean).join(' ').trim();
  const line3 = a.country || '';
  return [line1, line2, line3].filter(Boolean).join('\n');
};
const nowIso = () => new Date().toISOString();

// --- Map picker modal (inline component for simplicity)
function AddressPickerModal({ show, onClose, onPick, initial }) {
  const mapRef = useRef(null);
  const nodeRef = useRef(null);
  const markerRef = useRef(null);
  const [query, setQuery] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    if (!show) return;
    if (!nodeRef.current) return;

    // init map once
    if (!mapRef.current) {
      mapRef.current.setView(HAGENTHAL_CENTER, HAGENTHAL_ZOOM);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
      }).addTo(mapRef.current);

      mapRef.current.on('click', async (e) => {
        setBusy(true); setError('');
        try {
          const lat = e.latlng.lat, lon = e.latlng.lng;
          if (!markerRef.current) {
            markerRef.current = L.marker(e.latlng).addTo(mapRef.current);
          } else {
            markerRef.current.setLatLng(e.latlng);
          }
          const res = await fetch(`/api/geocode/reverse?lat=${lat}&lon=${lon}`);
          const j = await res.json();
          if (!j || !j.address) throw new Error('No address found');

          const a = j.address;
          const addr = {
            street: a.road || a.pedestrian || a.footway || a.path || '',
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
          setError(e2.message || 'Reverse geocoding failed');
        } finally {
          setBusy(false);
        }
      });
    }

    // center to initial if available
    if (initial?.geo) {
      mapRef.current.setView([initial.geo.lat, initial.geo.lng], 17);
      if (!markerRef.current) {
        markerRef.current = L.marker([initial.geo.lat, initial.geo.lng]).addTo(mapRef.current);
      } else {
        markerRef.current.setLatLng([initial.geo.lat, initial.geo.lng]);
      }
    } else {
      mapRef.current.setView([48.8566, 2.3522], 12); // Paris default
    }
  }, [show]);

  const onSubmitSearch = async (e) => {
    e.preventDefault();
    if (!query.trim()) return;
    setBusy(true); setError('');
    try {
      const res = await fetch(`/api/geocode/search?q=${encodeURIComponent(query)}&limit=5`);
      const j = await res.json();
      if (!Array.isArray(j) || j.length === 0) { setError('No results'); return; }
      const r = j[0]; // take first hit
      const lat = parseFloat(r.lat), lon = parseFloat(r.lon);
      mapRef.current.setView([lat, lon], 18);
      if (!markerRef.current) {
        markerRef.current = L.marker([lat, lon]).addTo(mapRef.current);
      } else {
        markerRef.current.setLatLng([lat, lon]);
      }
      // synthesize address object from search hit
      const a = r.address || {};
      const addr = {
        street: a.road || a.pedestrian || a.footway || '',
        house_number: a.house_number || '',
        postcode: a.postcode || '',
        city: a.city || a.town || a.village || '',
        region: a.state || '',
        country: a.country_code ? a.country_code.toUpperCase() : (a.country || ''),
        formatted: r.display_name || '',
        geo: { lat, lng: lon },
        place_id: r.place_id ? `nominatim:${r.place_id}` : '',
        source: 'nominatim',
        updated_at: nowIso()
      };
      onPick(addr);
    } catch (e2) {
      setError(e2.message || 'Search failed');
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className={`modal ${show ? 'd-block' : ''}`} tabIndex="-1" style={{ background: 'rgba(0,0,0,.3)' }}>
      <div className="modal-dialog modal-xl modal-dialog-centered">
        <div className="modal-content">
          <div className="modal-header">
            <h5 className="modal-title">Choisir une adresse</h5>
            <button type="button" className="btn-close" onClick={onClose} />
          </div>
          <div className="modal-body">
            <form className="d-flex gap-2 mb-2" onSubmit={onSubmitSearch}>
              <input
                className="form-control"
                placeholder="Rechercher une adresse, ex: 10 Rue de la Paix, Paris"
                value={query}
                onChange={(e) => setQuery(e.target.value)}
              />
              <button type="submit" className="btn btn-primary" disabled={busy}>
                Rechercher
              </button>
            </form>
            {error && <div className="alert alert-warning py-2">{error}</div>}
            <div ref={nodeRef} style={{ height: 500, width: '100%' }} />
            <div className="form-text mt-2">
              Astuce: cliquez sur la carte pour choisir l’emplacement exact. Un reverse-geocode remplira l’adresse.
            </div>
          </div>
          <div className="modal-footer">
            <button type="button" className="btn btn-outline-secondary" onClick={onClose}>Fermer</button>
          </div>
        </div>
      </div>
    </div>
  );
}

// --- Main dialog
export default function CustomerDialog({
  show, onClose, onSave, customer
}) {
  const [firstName, setFirstName] = useState('');
  const [lastName,  setLastName]  = useState('');
  const [phone,     setPhone]     = useState('');
  const [email,     setEmail]     = useState('');
  const [notes,     setNotes]     = useState('');
  const [gdprOk,    setGdprOk]    = useState(false);
  const [status,    setStatus]    = useState('active');
  const [address,   setAddress]   = useState(null);
  const [showPicker, setShowPicker] = useState(false);

  useEffect(() => {
    if (!show) return;
    setFirstName(customer?.first_name || '');
    setLastName(customer?.last_name || '');
    setPhone(customer?.phone || '');
    setEmail(customer?.email || '');
    setNotes(customer?.notes_public || '');
    setGdprOk(!!customer?.gdpr_ok);
  setStatus(customer?.status || 'active');

    let addr = null;
    try { addr = customer?.address ? JSON.parse(customer.address) : null; } catch {}
    if (addr && typeof addr === 'object') setAddress(addr); else setAddress(null);
  }, [show, customer]);

  const addressBlock = useMemo(() => fmtBlock(address), [address]);

  const handleClearAddress = () => setAddress(null);

  const submit = (e) => {
    e.preventDefault();
    // Prepare payload
    const payload = {
      id: customer?.id,
      first_name: firstName?.trim(),
      last_name: lastName?.trim(),
      phone: phone?.trim(),
      email: email?.trim(),
      notes_public: notes ?? '',
      gdpr_ok: gdprOk ? 1 : 0,
      status,
      address: address ? JSON.stringify({
        ...address,
        formatted: fmtBlock(address),
        updated_at: nowIso()
      }) : null
    };
    onSave(payload);
  };

  return (
    <>
      <div className={`modal ${show ? 'd-block' : ''}`} tabIndex="-1" style={{ background: 'rgba(0,0,0,.3)' }}>
        <div className="modal-dialog modal-lg modal-dialog-centered">
          <div className="modal-content">
            <form onSubmit={submit}>
              <div className="modal-header">
                <h5 className="modal-title">Éditer le client #{customer?.id ?? '—'}</h5>
                <button type="button" className="btn-close" onClick={onClose} />
              </div>

              <div className="modal-body">
                <div className="row g-3">
                  <div className="col-md-6">
                    <label className="form-label">Prénom *</label>
                    <input className="form-control"
                           value={firstName || ''} onChange={e=>setFirstName(e.target.value)} />
                  </div>
                  <div className="col-md-6">
                    <label className="form-label">Nom *</label>
                    <input className="form-control"
                           value={lastName || ''} onChange={e=>setLastName(e.target.value)} />
                  </div>

                  <div className="col-md-6">
                    <label className="form-label">Téléphone</label>
                    <input className="form-control"
                           value={phone || ''} onChange={e=>setPhone(e.target.value)} />
                  </div>
                  <div className="col-md-6">
                    <label className="form-label">Email</label>
                    <input type="email" className="form-control"
                           value={email || ''} onChange={e=>setEmail(e.target.value)} />
                  </div>

                  <div className="col-12">
                    <label className="form-label">Notes sur la cliente</label>
                    <textarea className="form-control" rows={2}
                              value={notes || ''} onChange={e=>setNotes(e.target.value)} />
                  </div>

                  <div className="col-md-6">
                    <label className="form-label">Statut</label>
                    <select className="form-select" value={status} onChange={e=>setStatus(e.target.value)}>
                      {STATUS_OPTIONS.map((opt) => (
                        <option key={opt.value} value={opt.value}>{opt.label}</option>
                      ))}
                    </select>
                  </div>

                  {/* Address block */}
                  <div className="col-12">
                    <div className="d-flex justify-content-between align-items-center mb-1">
                      <label className="form-label mb-0">Adresse postale</label>
                      <div className="btn-group">
                        <button type="button" className="btn btn-outline-primary btn-sm"
                                onClick={() => setShowPicker(true)}>
                          Rechercher sur la carte
                        </button>
                        <button type="button" className="btn btn-outline-secondary btn-sm"
                                onClick={() => {
                                  const t = prompt('Saisir une adresse libre (3 lignes max):',
                                    addressBlock || '');
                                  if (t !== null) {
                                    // quick manual capture (kept minimal)
                                    const lines = t.split('\n').map(s=>s.trim()).filter(Boolean);
                                    const next = { ...emptyAddr };
                                    if (lines[0]) next.street = lines[0];
                                    if (lines[1]) next.postcode = lines[1].split(' ')[0], next.city = lines[1].split(' ').slice(1).join(' ');
                                    if (lines[2]) next.country = lines[2];
                                    next.formatted = fmtBlock(next);
                                    next.updated_at = nowIso();
                                    setAddress(next);
                                  }
                                }}>
                          Saisie manuelle
                        </button>
                        <button type="button" className="btn btn-outline-danger btn-sm"
                                onClick={handleClearAddress}>
                          Effacer
                        </button>
                      </div>
                    </div>
                    <textarea className="form-control" rows={3} readOnly
                              value={addressBlock || ''} placeholder="— aucune adresse —" />
                    {/* Keep raw JSON in a hidden input so existing forms still serialize if needed */}
                    <input type="hidden" name="address_json"
                           value={address ? JSON.stringify(address) : ''} />
                  </div>

                  <div className="col-12">
                    <div className="form-check">
                      <input className="form-check-input" type="checkbox" id="gdpr_ok"
                             checked={!!gdprOk} onChange={(e)=>setGdprOk(e.target.checked)} />
                      <label className="form-check-label" htmlFor="gdpr_ok">
                        Consentement RGPD
                      </label>
                    </div>
                  </div>
                </div>
              </div>

              <div className="modal-footer">
                <button type="button" className="btn btn-outline-secondary" onClick={onClose}>Annuler</button>
                <button type="submit" className="btn btn-primary">Enregistrer</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      {/* Map modal */}
      {showPicker && (
        <AddressPickerModal
          show={showPicker}
          initial={address}
          onPick={(addr) => { setAddress(addr); setShowPicker(false); }}
          onClose={() => setShowPicker(false)}
        />
      )}
    </>
  );
}
