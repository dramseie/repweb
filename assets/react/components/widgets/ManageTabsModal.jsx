// assets/react/components/widgets/ManageTabsModal.jsx
import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';

export default function ManageTabsModal({ open, onClose }) {
  const [tabs, setTabs] = useState([]);
  const [busy, setBusy] = useState(false);

  // --- modal plumbing --------------------------------------------------------
  // Render into body so it’s not constrained by the dashboard layout
  if (typeof document === 'undefined') return null;

  // Prevent body scroll while open + ESC to close
  useEffect(() => {
    if (!open) return;
    const prevOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    const onKey = (e) => (e.key === 'Escape' ? onClose?.() : undefined);
    window.addEventListener('keydown', onKey);
    return () => {
      document.body.style.overflow = prevOverflow;
      window.removeEventListener('keydown', onKey);
    };
  }, [open, onClose]);

  const overlayStyle = {
    position: 'fixed',
    inset: 0,
    background: 'rgba(0,0,0,0.45)',
    zIndex: 2000,
    display: open ? 'flex' : 'none',
    alignItems: 'flex-start',
    justifyContent: 'center',
    overflowY: 'auto',
    padding: '48px 16px',
  };

  const dialogStyle = {
    background: '#fff',
    borderRadius: '12px',
    boxShadow: '0 10px 30px rgba(0,0,0,0.25)',
    width: 'min(960px, 96vw)',
    maxWidth: '96vw',
  };

  // --- data ops --------------------------------------------------------------
  const load = async () => {
    const r = await fetch('/api/ui/tabs', { credentials: 'same-origin' });
    setTabs(await r.json());
  };

  useEffect(() => { if (open) load(); }, [open]);

  const addTab = async () => {
    setBusy(true);
    const r = await fetch('/api/ui/tabs', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      credentials: 'same-origin',
      body: JSON.stringify({ title: 'New tab' })
    });
    setBusy(false);
    if (r.ok) await load();
  };

  const saveTitle = async (id, title) => {
    await fetch(`/api/ui/tabs/${id}`, {
      method: 'PATCH',
      headers: {'Content-Type':'application/json'},
      credentials: 'same-origin',
      body: JSON.stringify({ title })
    });
  };

  const del = async (id) => {
    if (!confirm('Delete this tab?')) return;
    await fetch(`/api/ui/tabs/${id}`, { method: 'DELETE', credentials: 'same-origin' });
    await load();
  };

  const move = async (idx, dir) => {
    const arr = [...tabs];
    const j = idx + dir;
    if (j < 0 || j >= arr.length) return;
    [arr[idx], arr[j]] = [arr[j], arr[idx]];
    // recompute sort_order 10,20,30…
    const payload = arr.map((t, i) => ({ id: t.id, sort_order: (i + 1) * 10 }));
    setTabs(arr);
    await fetch('/api/ui/tabs/reorder', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      credentials: 'same-origin',
      body: JSON.stringify(payload)
    });
  };

  // --- UI --------------------------------------------------------------------
  const content = (
    <div style={overlayStyle} role="dialog" aria-modal="true" aria-label="Manage tabs" onMouseDown={(e)=>{ if(e.target===e.currentTarget) onClose?.(); }}>
      <div style={dialogStyle} className="p-3 p-md-4" onMouseDown={(e)=>e.stopPropagation()}>
        <div className="d-flex align-items-center justify-content-between mb-3">
          <h2 className="h4 m-0">Manage tabs</h2>
          <button
            type="button"
            className="btn btn-sm btn-outline-secondary"
            onClick={onClose}
            aria-label="Close"
          >
            ✕
          </button>
        </div>

        <div className="mb-3" style={{maxHeight:'60vh', overflow:'auto'}}>
          {tabs.map((t, i) => (
            <div key={t.id} className="border rounded p-2 p-md-3 mb-2">
              <div className="d-flex align-items-center gap-2">
                <span className="text-muted small me-2" style={{width:24, textAlign:'right'}}>{i+1}</span>
                <input
                  defaultValue={t.title}
                  className="form-control"
                  onBlur={(e) => saveTitle(t.id, e.target.value)}
                  style={{maxWidth:'420px'}}
                />
                <div className="ms-auto d-flex gap-1">
                  <button className="btn btn-sm btn-outline-secondary" onClick={() => move(i,-1)} title="Move up">↑</button>
                  <button className="btn btn-sm btn-outline-secondary" onClick={() => move(i, 1)} title="Move down">↓</button>
                  <button className="btn btn-sm btn-outline-danger" onClick={() => del(t.id)}>Delete</button>
                </div>
              </div>
            </div>
          ))}
          {tabs.length === 0 && <div className="text-muted small">No tabs yet.</div>}
        </div>

        <div className="d-flex justify-content-between">
          <button className="btn btn-outline-primary" onClick={addTab} disabled={busy}>+ New tab</button>
          <button className="btn btn-dark" onClick={onClose}>Close</button>
        </div>
      </div>
    </div>
  );

  return open ? createPortal(content, document.body) : null;
}
