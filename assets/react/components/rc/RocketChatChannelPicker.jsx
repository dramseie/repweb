import React, { useEffect, useState } from 'react';

export default function RocketChatChannelPicker({ onClose, onSaved }) {
  const [rooms, setRooms] = useState([]);           // from /api/rocketchat/rooms
  const [subs, setSubs] = useState(new Map());      // roomId -> { notify }
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let cancel = false;
    (async () => {
      try {
        const [rRooms, rSubs] = await Promise.all([
          fetch('/api/rocketchat/rooms').then(r=>r.json()),
          fetch('/api/rocketchat/subscriptions').then(r=>r.json()),
        ]);
        if (cancel) return;
        const list = (rRooms.rooms || []).map(x => ({ id:x.id, name:x.name, type:x.type }));
        setRooms(list);
        const m = new Map();
        for (const s of (rSubs.subscriptions || [])) {
          m.set(s.room_id, { notify: !!s.notify });
        }
        // Seed default if none yet
        if (m.size === 0 && rSubs?.defaults?.defaultRoomName) {
          const def = list.find(x => x.name === rSubs.defaults.defaultRoomName);
          if (def) m.set(def.id, { notify: true });
        }
        setSubs(m);
      } finally { setLoading(false); }
    })();
    return () => { cancel = true; };
  }, []);

  const toggle = (room) => {
    setSubs(prev => {
      const m = new Map(prev);
      if (m.has(room.id)) m.delete(room.id); else m.set(room.id, { notify: true });
      return m;
    });
  };

  const save = async () => {
    const payload = [];
    for (const room of rooms) {
      if (subs.has(room.id)) {
        payload.push({ room_id: room.id, room_name: room.name, room_type: room.type, notify: true });
      }
    }
    await fetch('/api/rocketchat/subscriptions', {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
      credentials: 'same-origin',
    });
    onSaved?.();
    onClose?.();
  };

  return (
    <div className="modal d-block" tabIndex="-1" role="dialog" style={{ background: 'rgba(0,0,0,0.4)' }}>
      <div className="modal-dialog modal-lg">
        <div className="modal-content">
          <div className="modal-header">
            <h5 className="modal-title">Rocket.Chat Channels</h5>
            <button type="button" className="btn-close" onClick={onClose} />
          </div>
          <div className="modal-body">
            {loading ? <div>Loading…</div> : (
              <div className="row g-2">
                {rooms.map(r => {
                  const checked = subs.has(r.id);
                  return (
                    <div className="col-12 col-md-6" key={r.id}>
                      <label className="form-check">
                        <input type="checkbox" className="form-check-input" checked={checked} onChange={() => toggle(r)} />
                        <span className="form-check-label">
                          {r.type === 'p' ? '🔒' : '#'} {r.name}
                        </span>
                      </label>
                    </div>
                  );
                })}
              </div>
            )}
          </div>
          <div className="modal-footer">
            <button className="btn btn-outline-secondary" onClick={onClose}>Cancel</button>
            <button className="btn btn-primary" onClick={save} disabled={loading}>Save</button>
          </div>
        </div>
      </div>
    </div>
  );
}
