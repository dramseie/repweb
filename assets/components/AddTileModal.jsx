import React, { useMemo, useState } from 'react';

export default function AddTileModal({ allTiles, myTiles, onAdd, onClose }) {
  const [q, setQ] = useState('');
  const myTileIds = useMemo(() => new Set((myTiles || []).map(ut => ut.tile.id)), [myTiles]);
  const filtered = useMemo(() =>
    (allTiles || [])
      .filter(t => !myTileIds.has(t.id))
      .filter(t => t.title.toLowerCase().includes(q.toLowerCase()))
  , [allTiles, myTileIds, q]);

  return (
    <div className="modal d-block" tabIndex="-1" role="dialog" style={{ background: 'rgba(0,0,0,0.5)' }}>
      <div className="modal-dialog modal-lg" role="document">
        <div className="modal-content">
          <div className="modal-header">
            <h5 className="modal-title">Add tiles</h5>
            <button type="button" className="btn-close" onClick={onClose} />
          </div>
          <div className="modal-body">
            <input className="form-control mb-3" placeholder="Search..." value={q} onChange={e => setQ(e.target.value)} />
            <div className="tiles-grid">
              {filtered.map(t => (
                <div key={t.id} className="card h-100">
                  {t.thumbnailUrl && <img src={t.thumbnailUrl} className="card-img-top" alt="thumb" />}
                  <div className="card-body">
                    <h6 className="card-title">{t.title}</h6>
                    <p className="card-text"><span className="badge bg-secondary">{t.type}</span></p>
                    <button className="btn btn-primary btn-sm" onClick={() => onAdd(t.id)}>Add</button>
                  </div>
                </div>
              ))}
              {filtered.length === 0 && <div className="text-muted">No tiles available.</div>}
            </div>
          </div>
          <div className="modal-footer">
            <button className="btn btn-secondary" onClick={onClose}>Close</button>
          </div>
        </div>
      </div>
    </div>
  );
}
