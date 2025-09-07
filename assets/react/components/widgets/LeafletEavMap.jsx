// assets/react/components/widgets/LeafletEavMap.jsx
import React, { useEffect, useMemo, useRef, useState } from 'react';
import { MapContainer, TileLayer, Marker, Popup, useMap, useMapEvent, LayersControl } from 'react-leaflet';
import L from 'leaflet';
import Supercluster from 'supercluster';
import GeoCiFormModal from './GeoCiFormModal';
import 'leaflet/dist/leaflet.css';

/* ---------- helpers ---------- */
function AutoFocus({ features, singleZoom = 13, pad = 0.2 }) {
  const map = useMap();
  useEffect(() => {
    if (!features?.length) return;
    if (features.length === 1) {
      const [lng, lat] = features[0].geometry.coordinates;
      map.setView([lat, lng], singleZoom, { animate: true });
    } else {
      const bounds = L.latLngBounds(features.map(f => [f.geometry.coordinates[1], f.geometry.coordinates[0]]));
      if (bounds.isValid()) map.fitBounds(bounds.pad(pad));
    }
  }, [features, map, singleZoom, pad]);
  return null;
}
function toBBox(b) { const sw=b.getSouthWest(), ne=b.getNorthEast(); return [sw.lng, sw.lat, ne.lng, ne.lat]; }
function clusterIcon(count) {
  const size = count<10?'sc-small':count<50?'sc-medium':'sc-large';
  return L.divIcon({ html:`<div><span>${count}</span></div>`, className:`sc-cluster ${size}`, iconSize:L.point(40,40,true) });
}
const styleOnce = (() => {
  let done = false;
  return () => {
    if (done) return;
    const css = `
.sc-cluster { background: rgba(0, 123, 255, .15); border-radius:50%; border:2px solid rgba(0,123,255,.6); color:#0d6efd; display:flex; align-items:center; justify-content:center; font-weight:600;}
.sc-cluster div{width:100%;height:100%;border-radius:50%;display:flex;align-items:center;justify-content:center;}
.sc-cluster.sc-small{width:32px;height:32px;font-size:12px;}
.sc-cluster.sc-medium{width:40px;height:40px;font-size:13px;}
.sc-cluster.sc-large{width:52px;height:52px;font-size:14px;}
.searchbox{display:flex;gap:.5rem;align-items:center}
.searchbox input{max-width:280px}

/* Fullscreen container */
.map-wrap { position: relative; }
.map-wrap.map-fullscreen { position: fixed !important; inset: 0 !important; z-index: 1050 !important; background: #fff; }
.map-wrap .card, .map-wrap .card-body { height: 100%; }

/* Leaflet-like control button */
.leaflet-control.custom-fullscreen a {
  background:#fff; border-radius:4px; width:30px; height:30px; display:inline-flex; align-items:center; justify-content:center;
  box-shadow: 0 1px 4px rgba(0,0,0,.2); text-decoration:none; color:#333; font-weight:700;
}
.leaflet-control.custom-fullscreen a:hover { background:#f4f4f4; }
`;
    const el = document.createElement('style'); el.textContent = css; document.head.appendChild(el);
    done = true;
  };
})();

/* ---------- main component ---------- */
export default function LeafletEavMap({
  apiUrl = '/api/eav/geo/view',
  height = '560px',
  query,
  clusterRadius = 60,
  maxZoom = 18,
}) {
  const [fc, setFc] = useState({ type: 'FeatureCollection', features: [] });
  const [err, setErr] = useState(null);
  const [loading, setLoading] = useState(true);

  // UI
  const [search, setSearch] = useState('');
  const [searchHits, setSearchHits] = useState([]);
  const [newMarker, setNewMarker] = useState(null);
  const [showCreate, setShowCreate] = useState(false);
  const [editCi, setEditCi] = useState(null);
  const [placing, setPlacing] = useState(false);
  const [showLocations, setShowLocations] = useState(true);
  const [showNewPin, setShowNewPin] = useState(true);
  const [fullscreen, setFullscreen] = useState(false);

  const wrapperRef = useRef(null);
  const mapRef = useRef(null);

  // Build URL
  const url = useMemo(() => {
    const p = new URLSearchParams();
    if (query && typeof query === 'object') for (const [k,v] of Object.entries(query)) if (v!=null && v!=='') p.set(k,v);
    return p.toString() ? `${apiUrl}?${p}` : apiUrl;
  }, [apiUrl, query]);

  // Load data
  useEffect(() => {
    styleOnce();
    let alive = true;
    setLoading(true);
    fetch(url, { credentials: 'same-origin' })
      .then(r => { if (!r.ok) throw new Error(`HTTP ${r.status}`); return r.json(); })
      .then(j => { if (alive) { setFc(j); setErr(null); } })
      .catch(e => alive && setErr(e.message || String(e)))
      .finally(() => alive && setLoading(false));
    return () => { alive = false; };
  }, [url]);

  // Escape to exit fullscreen
  useEffect(() => {
    const onKey = (e) => { if (e.key === 'Escape') setFullscreen(false); };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, []);

  // When toggling fullscreen, force Leaflet to recompute sizes
  useEffect(() => {
    const map = mapRef.current;
    if (map) setTimeout(() => map.invalidateSize?.(), 150);
  }, [fullscreen]);

  const features = fc?.features ?? [];

  // Supercluster index
  const index = useMemo(() => {
    const idx = new Supercluster({ radius: clusterRadius, maxZoom, map: (p)=>p });
    idx.load(features);
    return idx;
  }, [features, clusterRadius, maxZoom]);

  // Search proxy
  async function doSearch() {
    if (!search.trim()) return setSearchHits([]);
    const r = await fetch(`/api/geo/search?q=${encodeURIComponent(search.trim())}`);
    if (!r.ok) return;
    setSearchHits(await r.json());
  }

  // Reverse geocode
  async function reverseAt(lat, lng) {
    const r = await fetch(`/api/geo/reverse?lat=${encodeURIComponent(lat)}&lon=${encodeURIComponent(lng)}`);
    if (!r.ok) return;
    const j = await r.json();
    const adr = j.address || {};
    setNewMarker(m => ({
      ...(m||{}),
      lat, lng,
      address: j.display_name || m?.address || '',
      city: adr.city || adr.town || adr.village || m?.city || '',
      country: adr.country || m?.country || '',
    }));
  }

  /* ---------- clustered layer ---------- */
  function ClusterLayer() {
    const map = useMap();
    const [clusters, setClusters] = useState([]);

    const update = () => {
      const b = map.getBounds(); const z = map.getZoom();
      if (!b) return;
      setClusters(index.getClusters(toBBox(b), Math.round(z)));
    };

    useEffect(() => {
      update();
      map.on('moveend zoomend', update);
      return () => map.off('moveend zoomend', update);
    }, [map, index]);

    if (!showLocations) return null;

    return (
      <>
        {clusters.map((f, i) => {
          const [lng, lat] = f.geometry.coordinates;
          const props = f.properties || {};

          if (props.cluster) {
            const count = props.point_count || 0;
            const id = props.cluster_id;
            return (
              <Marker
                key={`c-${id}`}
                position={[lat, lng]}
                icon={clusterIcon(count)}
                eventHandlers={{
                  click: () => {
                    const nextZoom = Math.min(index.getClusterExpansionZoom(id), maxZoom);
                    map.setView([lat, lng], nextZoom, { animate: true });
                  },
                }}
              />
            );
          }

          return (
            <Marker
              key={`p-${i}`}
              position={[lat, lng]}
              eventHandlers={{
                dblclick: () =>
                  setEditCi({
                    ci: props.ci,
                    name: props.name,
                    status: props.status,
                    address: props.address,
                    city: props.city,
                    country: props.country,
                    desc: props.desc,
                    icon: props.icon,
                    lat: lat.toString(),
                    long: lng.toString(),
                  }),
              }}
            >
              <Popup>
                <div style={{ minWidth: 220 }}>
                  <div className="fw-bold mb-1">{props.name || props.ci || 'Unknown'}</div>
                  {props.address && <div>{props.address}</div>}
                  <div className="text-muted small">{[props.city, props.country].filter(Boolean).join(', ')}</div>
                  {props.status && <div className="badge bg-secondary mt-2">{props.status}</div>}
                  {props.desc && <div className="mt-2">{props.desc}</div>}
                  {props.ci && <div className="mt-2 small text-muted">CI: {props.ci}</div>}
                  <div className="mt-2">
                    <button
                      className="btn btn-sm btn-outline-primary"
                      onClick={() =>
                        setEditCi({
                          ci: props.ci,
                          name: props.name,
                          status: props.status,
                          address: props.address,
                          city: props.city,
                          country: props.country,
                          desc: props.desc,
                          icon: props.icon,
                          lat: lat.toString(),
                          long: lng.toString(),
                        })
                      }
                    >
                      Edit…
                    </button>
                  </div>
                </div>
              </Popup>
            </Marker>
          );
        })}
      </>
    );
  }

  /* ---------- click-to-place in "new" mode ---------- */
  function ClickToPlace() {
    useMapEvent('click', (e) => {
      if (!placing) return;
      const { lat, lng } = e.latlng;
      setNewMarker({ lat, lng, address:'', city:'', country:'' });
      reverseAt(lat, lng);
    });
    return null;
  }

  /* ---------- Custom Fullscreen control ---------- */
  function FullscreenControl() {
    const map = useMap();
    useEffect(() => { mapRef.current = map; }, [map]);
    return (
      <div className="leaflet-control custom-fullscreen leaflet-bar">
        <a
          href="#"
          onClick={(e) => { e.preventDefault(); setFullscreen(f => !f); }}
          title={fullscreen ? 'Exit full screen (Esc)' : 'Full screen'}
        >
          ⤢
        </a>
      </div>
    );
  }

  return (
    <div ref={wrapperRef} className={`map-wrap ${fullscreen ? 'map-fullscreen' : ''}`} style={{ height }}>
      <div className="card shadow-sm" style={{ height: '100%' }}>
        <div className="card-header py-2 d-flex justify-content-between align-items-center">
          <strong>World Map</strong>
          <div className="d-flex align-items-center gap-2">
            <div className="searchbox">
              <input
                className="form-control form-control-sm"
                placeholder="Search address/place…"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                onKeyDown={(e) => { if (e.key === 'Enter') doSearch(); }}
              />
              <button className="btn btn-sm btn-outline-secondary" onClick={doSearch}>Search</button>
            </div>
            <button
              className={`btn btn-sm ${placing ? 'btn-warning' : 'btn-primary'}`}
              onClick={() => setPlacing((p) => !p)}
              title="Click map to place draggable pin"
            >
              {placing ? 'Cancel placing' : 'New location'}
            </button>
          </div>
        </div>

        {/* search suggestions */}
        {searchHits.length>0 && (
          <div className="px-2 pt-2">
            <div className="list-group">
              {searchHits.map((h, i) => (
                <button key={i} className="list-group-item list-group-item-action"
                  onClick={()=>{
                    const lat = parseFloat(h.lat), lng = parseFloat(h.lon);
                    setNewMarker({ lat, lng,
                      address: h.display_name,
                      city: h.address?.city || h.address?.town || h.address?.village || '',
                      country: h.address?.country || '',
                    });
                    setSearchHits([]);
                  }}>
                  {h.display_name}
                </button>
              ))}
            </div>
          </div>
        )}

        <div className="card-body p-0" style={{ height: `calc(${height} - 56px)` }}>
          <MapContainer
            style={{ height: '100%', width: '100%' }}
            center={[20, 0]} zoom={2} scrollWheelZoom
            whenCreated={(map) => (mapRef.current = map)}
          >
            {/* Fullscreen button as a Leaflet control in topleft */}
            <div className="leaflet-top leaflet-left"><FullscreenControl /></div>

            {/* Base layers & overlays */}
            <LayersControl position="topright">
              {/* Base layers */}
              <LayersControl.BaseLayer checked name="OpenStreetMap">
                <TileLayer
                  attribution="&copy; OpenStreetMap contributors"
                  url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                />
              </LayersControl.BaseLayer>

              <LayersControl.BaseLayer name="CartoDB Positron">
                <TileLayer
                  attribution="&copy; OpenStreetMap contributors &copy; CARTO"
                  url="https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png"
                />
              </LayersControl.BaseLayer>

              <LayersControl.BaseLayer name="OpenTopoMap (terrain)">
                <TileLayer
                  attribution="Map data: &copy; OpenStreetMap contributors, SRTM | Map style: &copy; OpenTopoMap (CC-BY-SA)"
                  url="https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png"
                />
              </LayersControl.BaseLayer>

              <LayersControl.BaseLayer name="Esri WorldImagery (satellite)">
                <TileLayer
                  attribution="Source: Esri, Maxar, Earthstar Geographics, and the GIS User Community"
                  url="https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}"
                />
              </LayersControl.BaseLayer>

              {/* Overlays */}
              <LayersControl.Overlay checked name="Locations" >
                <div onAdd={() => setShowLocations(true)} onRemove={() => setShowLocations(false)}>
                  {/* Marker layers rendered regardless; visibility controlled via state */}
                </div>
              </LayersControl.Overlay>

              <LayersControl.Overlay checked name="New location pin">
                <div onAdd={() => setShowNewPin(true)} onRemove={() => setShowNewPin(false)} />
              </LayersControl.Overlay>
            </LayersControl>

            <AutoFocus features={features} />
            <ClickToPlace />
            <ClusterLayer />

            {/* Draggable new marker (overlay toggle respected) */}
            {newMarker && showNewPin && (
              <Marker
                position={[newMarker.lat, newMarker.lng]}
                draggable
                eventHandlers={{
                  dragend: (e) => {
                    const ll = e.target.getLatLng();
                    reverseAt(ll.lat, ll.lng);
                  },
                }}
              >
                <Popup>
                  <div style={{ minWidth: 240 }}>
                    <div className="mb-2"><strong>New location</strong></div>
                    <div className="small text-muted mb-2">{newMarker.address}</div>
                    <button className="btn btn-sm btn-primary" onClick={() => setShowCreate(true)}>
                      Create CI…
                    </button>
                  </div>
                </Popup>
              </Marker>
            )}
          </MapContainer>
        </div>

        {/* Create CI modal */}
        <GeoCiFormModal
          show={showCreate}
          initial={{
            ci: '', name: '', status: 'active',
            address: newMarker?.address || '',
            city: newMarker?.city || '',
            country: newMarker?.country || '',
            lat: newMarker ? String(newMarker.lat) : '',
            long: newMarker ? String(newMarker.lng) : '',
            icon: 'fa-solid fa-location-dot',
            desc: '',
          }}
          submitLabel="Create CI"
          onClose={() => setShowCreate(false)}
          onSubmit={async (form) => {
            const r = await fetch('/api/eav/geo/entity', {
              method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(form),
            });
            if (!r.ok) { alert('Create failed'); return; }
            setShowCreate(false); setPlacing(false); setNewMarker(null);
            const j = await fetch(url).then(x=>x.json()); setFc(j);
          }}
        />

        {/* Edit CI modal */}
        <GeoCiFormModal
          show={!!editCi}
          initial={editCi || {}}
          submitLabel="Save changes"
          onClose={() => setEditCi(null)}
          onSubmit={async (form) => {
            const ci = form.ci || editCi.ci;
            const r = await fetch(`/api/eav/geo/entity/${encodeURIComponent(ci)}`, {
              method:'PUT', headers:{'Content-Type':'application/json'}, body: JSON.stringify(form),
            });
            if (!r.ok) { alert('Update failed'); return; }
            setEditCi(null);
            const j = await fetch(url).then(x=>x.json()); setFc(j);
          }}
        />
      </div>
    </div>
  );
}
