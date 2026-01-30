import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
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
const fmtSingleLine = (a) => {
  const block = fmtBlock(a);
  return block ? block.replace(/\s*\n\s*/g, ', ').replace(/\s{2,}/g, ' ').trim() : '';
};
const nowIso = () => new Date().toISOString();

// --- Map picker modal (inline component for simplicity)
function AddressPickerModal({ show, onClose, onPick, initial }) {
  const mapRef = useRef(null);
  const nodeRef = useRef(null);
  const markerRef = useRef(null);
  const reverseGeocodeRef = useRef(null);
  const [query, setQuery] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [selected, setSelected] = useState(null);

  const clearMarker = useCallback(() => {
    if (markerRef.current && mapRef.current) {
      mapRef.current.removeLayer(markerRef.current);
    }
    markerRef.current = null;
  }, []);

  const ensureMarker = useCallback((lat, lon) => {
    if (!mapRef.current) return;
    if (!markerRef.current) {
      markerRef.current = L.marker([lat, lon], { draggable: true }).addTo(mapRef.current);
      markerRef.current.on('dragend', (ev) => {
        const { lat: nLat, lng: nLon } = ev.target.getLatLng();
        reverseGeocodeRef.current?.(nLat, nLon);
      });
    } else {
      markerRef.current.setLatLng([lat, lon]);
    }
  }, []);

  const nominatimToAddress = useCallback((payload, lat, lon) => {
    const a = payload?.address || {};
    const street = a.road
      || a.residential
      || a.pedestrian
      || a.cycleway
      || a.footway
      || a.path
      || a.street
      || a.suburb
      || '';
    const addr = {
      ...emptyAddr,
      street,
      house_number: a.house_number ? String(a.house_number).trim() : '',
      postcode: a.postcode ? String(a.postcode).trim() : '',
      city: (a.city || a.town || a.village || a.municipality || a.hamlet || a.locality || '').trim(),
      region: (a.state || a.region || a.county || '').trim(),
      country: (a.country_code ? a.country_code.toUpperCase() : (a.country || '')).trim(),
      geo: { lat, lng: lon },
      place_id: payload?.place_id ? `nominatim:${payload.place_id}` : '',
      source: 'nominatim'
    };
    addr.formatted = fmtBlock(addr);
    addr.updated_at = nowIso();
    return addr;
  }, []);

  const applySelection = useCallback((addr) => {
    setSelected(addr);
    setQuery(addr ? fmtSingleLine(addr) : '');
  }, []);

  const reverseGeocode = useCallback(async (lat, lon) => {
    setBusy(true);
    setError('');
    try {
      const res = await fetch(`/api/geocode/reverse?lat=${lat}&lon=${lon}`);
      if (!res.ok) throw new Error('Recherche inverse indisponible');
      const data = await res.json();
      if (!data?.address) throw new Error('Aucune adresse trouvée');
      const addr = nominatimToAddress(data, lat, lon);
      ensureMarker(lat, lon);
      applySelection(addr);
      if (mapRef.current) {
        const nextZoom = Math.max(mapRef.current.getZoom() || 0, 17);
        mapRef.current.setView([lat, lon], nextZoom);
      }
    } catch (err) {
      setError(err.message || 'Reverse geocoding échoué');
    } finally {
      setBusy(false);
    }
  }, [applySelection, ensureMarker, nominatimToAddress]);

  useEffect(() => {
    reverseGeocodeRef.current = reverseGeocode;
  }, [reverseGeocode]);

  useEffect(() => {
    if (!show || !nodeRef.current) return;

    if (!mapRef.current) {
      mapRef.current = L.map(nodeRef.current, { zoomControl: true }).setView(HAGENTHAL_CENTER, HAGENTHAL_ZOOM);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
      }).addTo(mapRef.current);

      mapRef.current.on('click', (e) => {
        const { lat, lng } = e.latlng;
        ensureMarker(lat, lng);
        reverseGeocodeRef.current?.(lat, lng);
      });
    }

    // ensure tiles resize once modal animation is done
    setTimeout(() => {
      try { mapRef.current?.invalidateSize(); } catch (_) {}
    }, 100);
  }, [show, ensureMarker]);

  useEffect(() => {
    if (!show) return;
    setError('');
    setBusy(false);

    if (initial && typeof initial === 'object') {
      const rawGeo = initial.geo || null;
      const rawLat = rawGeo?.lat ?? rawGeo?.latitude ?? null;
      const rawLon = rawGeo?.lng ?? rawGeo?.lon ?? rawGeo?.longitude ?? null;
      const lat = rawLat == null ? NaN : parseFloat(rawLat);
      const lon = rawLon == null ? NaN : parseFloat(rawLon);
      const geo = Number.isFinite(lat) && Number.isFinite(lon) ? { lat, lng: lon } : null;
      const seed = {
        ...emptyAddr,
        ...initial,
        geo
      };
      seed.formatted = fmtBlock(seed);
      applySelection(seed);
      if (geo && !Number.isNaN(geo.lat) && !Number.isNaN(geo.lng) && mapRef.current) {
        ensureMarker(geo.lat, geo.lng);
        const targetZoom = mapRef.current.getZoom() >= 17 ? mapRef.current.getZoom() : 17;
        mapRef.current.setView([geo.lat, geo.lng], targetZoom);
      } else if (mapRef.current) {
        clearMarker();
        mapRef.current.setView(HAGENTHAL_CENTER, HAGENTHAL_ZOOM);
      }
    } else {
      setSelected(null);
      setQuery('');
      if (mapRef.current) {
        clearMarker();
        mapRef.current.setView(HAGENTHAL_CENTER, HAGENTHAL_ZOOM);
      }
    }
  }, [show, initial, applySelection, ensureMarker, clearMarker]);

  const onSubmitSearch = async (e) => {
    e.preventDefault();
    const raw = (query || '').trim();
    if (!raw) return;
    setBusy(true);
    setError('');
    try {
      const res = await fetch(`/api/geocode/search?q=${encodeURIComponent(raw)}&limit=5`);
      if (!res.ok) throw new Error('Recherche indisponible');
      const results = await res.json();
      if (!Array.isArray(results) || results.length === 0) throw new Error('Aucun résultat');
      const hit = results[0];
      const lat = parseFloat(hit.lat);
      const lon = parseFloat(hit.lon);
      if (!Number.isFinite(lat) || !Number.isFinite(lon)) throw new Error('Coordonnées invalides');
      const addr = nominatimToAddress(hit, lat, lon);
      ensureMarker(lat, lon);
      if (mapRef.current) {
        const targetZoom = mapRef.current.getZoom() >= 18 ? mapRef.current.getZoom() : 18;
        mapRef.current.setView([lat, lon], targetZoom);
      }
      applySelection(addr);
    } catch (err) {
      setError(err.message || 'Recherche échouée');
    } finally {
      setBusy(false);
    }
  };

  const handleSave = () => {
    if (!selected) return;
    const payload = {
      ...selected,
      geo: selected.geo ? { ...selected.geo } : null,
      formatted: fmtBlock(selected),
      updated_at: nowIso()
    };
    onPick(payload);
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
            <button type="button" className="btn btn-primary" onClick={handleSave} disabled={!selected || busy}>
              Enregistrer
            </button>
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
