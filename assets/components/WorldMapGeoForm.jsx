// assets/react/components/WorldMapGeoForm.jsx
import React, { useMemo, useState } from "react";
import { MapContainer, TileLayer, Marker, Popup, useMapEvents } from "react-leaflet";
import L from "leaflet";
import "leaflet/dist/leaflet.css";

const pin = new L.Icon({
  iconUrl:
    "https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon.png",
  iconRetinaUrl:
    "https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon-2x.png",
  shadowUrl:
    "https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png",
  iconSize: [25, 41],
  iconAnchor: [12, 41],
});

function ClickToSet({ setLat, setLng }) {
  useMapEvents({
    click(e) {
      setLat(Number(e.latlng.lat.toFixed(6)));
      setLng(Number(e.latlng.lng.toFixed(6)));
    },
  });
  return null;
}

export default function WorldMapGeoForm({
  tenant = "loc",
  type = "geoloc",
  initial = {
    ci: "Home",
    name: "David Ramseier",
    status: "active",
    address: "7, rue de Bettlach",
    city: "Hagenthal-le-Bas",
    country: "France",
    lat: 47.5685,
    long: 7.5031,
    icon: "fa-solid fa-building",
    desc: "David Ramseier private home",
  },
  updatedBy = "repweb",
  onSaved = () => {},
}) {
  const [ci, setCi] = useState(initial.ci);
  const [name, setName] = useState(initial.name);
  const [status, setStatus] = useState(initial.status ?? "active");
  const [address, setAddress] = useState(initial.address ?? "");
  const [city, setCity] = useState(initial.city ?? "");
  const [country, setCountry] = useState(initial.country ?? "");
  const [lat, setLat] = useState(Number(initial.lat) || 0);
  const [lng, setLng] = useState(Number(initial.long) || 0);
  const [icon, setIcon] = useState(initial.icon ?? "");
  const [desc, setDesc] = useState(initial.desc ?? "");
  const [saving, setSaving] = useState(false);
  const center = useMemo(() => [lat || 47.5685, lng || 7.5031], [lat, lng]);

  async function handleSave() {
    setSaving(true);
    try {
      const payload = {
        tenant,
        type,
        ci,
        name,
        status,
        attributes: {
          address,
          city,
          country,
          lat: String(lat),
          long: String(lng),
          icon,
          desc,
        },
        updated_by: updatedBy,
      };

      const res = await fetch("/api/eav/upsert", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });

      const data = await res.json();
      if (!res.ok || data?.ok === false) {
        const msg = data?.message || data?.error || "Upsert failed";
        throw new Error(msg);
      }
      onSaved(data);
      alert("Saved ✔");
    } catch (e) {
      console.error(e);
      alert(`Save failed: ${e.message}`);
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="grid grid-cols-12 gap-4">
      <div className="col-span-3">
        <div className="text-gray-700 font-semibold mb-2">WORLD MAP</div>
        <MapContainer center={center} zoom={13} style={{ height: 520 }}>
          <TileLayer
            attribution='&copy; OpenStreetMap'
            url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
          />
          <Marker position={[lat, lng]} icon={pin}>
            <Popup>
              <strong>{ci}</strong>
              <br />
              {name}
              <br />
              {lat},{lng}
            </Popup>
          </Marker>
          <ClickToSet setLat={setLat} setLng={setLng} />
        </MapContainer>
        <div className="text-xs mt-2 text-gray-500">
          Tip: click the map to set coordinates.
        </div>
      </div>

      <div className="col-span-9">
        <div className="grid grid-cols-12 gap-4">
          <div className="col-span-4">
            <label className="block text-sm">CI</label>
            <input className="form-control" value={ci} onChange={e=>setCi(e.target.value)} />
          </div>
          <div className="col-span-4">
            <label className="block text-sm">Name</label>
            <input className="form-control" value={name} onChange={e=>setName(e.target.value)} />
          </div>
          <div className="col-span-4">
            <label className="block text-sm">Status</label>
            <input className="form-control" value={status} onChange={e=>setStatus(e.target.value)} />
          </div>

          <div className="col-span-6">
            <label className="block text-sm">Address</label>
            <input className="form-control" value={address} onChange={e=>setAddress(e.target.value)} />
          </div>
          <div className="col-span-3">
            <label className="block text-sm">City</label>
            <input className="form-control" value={city} onChange={e=>setCity(e.target.value)} />
          </div>
          <div className="col-span-3">
            <label className="block text-sm">Country</label>
            <input className="form-control" value={country} onChange={e=>setCountry(e.target.value)} />
          </div>

          <div className="col-span-3">
            <label className="block text-sm">Latitude</label>
            <input className="form-control" value={lat} onChange={e=>setLat(Number(e.target.value))} />
          </div>
          <div className="col-span-3">
            <label className="block text-sm">Longitude</label>
            <input className="form-control" value={lng} onChange={e=>setLng(Number(e.target.value))} />
          </div>
          <div className="col-span-6">
            <label className="block text-sm">Icon (Fa class)</label>
            <input className="form-control" value={icon} onChange={e=>setIcon(e.target.value)} />
          </div>

          <div className="col-span-12">
            <label className="block text-sm">Description</label>
            <textarea className="form-control" rows={4} value={desc} onChange={e=>setDesc(e.target.value)} />
          </div>
        </div>

        <div className="flex justify-end gap-3 mt-6">
          <button className="btn btn-secondary" onClick={()=>window.history.back()} disabled={saving}>
            Cancel
          </button>
          <button className="btn btn-primary" onClick={handleSave} disabled={saving}>
            {saving ? "Saving…" : "Save changes"}
          </button>
        </div>
      </div>
    </div>
  );
}
