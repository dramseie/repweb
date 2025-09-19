// assets/react/components/widgets/RocketChatRealtimeWidget.jsx
import React, { useEffect, useMemo, useRef, useState } from 'react';
import { DDPSDK } from '@rocket.chat/ddp-client';

// --- daterangepicker deps ---
import $ from 'jquery';
import moment from 'moment';
import 'daterangepicker';
import 'daterangepicker/daterangepicker.css';
if (typeof window !== 'undefined') {
  window.$ = window.jQuery = $;
  window.moment = moment;
}

const DISPLAY_FORMAT = 'YYYY-MM-DD HH:mm';

function normalizeBase(url) {
  if (!url) return '';
  return url.replace(/\/websocket$/, '').replace(/^wss:\/\//, 'https://');
}
function iso(dt) {
  return new Date(dt).toISOString();
}
function nowMinusHours(h) {
  const d = new Date();
  d.setHours(d.getHours() - h);
  return d;
}

/* ----------------------------- Range Picker ----------------------------- */
function RangePicker({ start, end, disabled, onChange, onApply }) {
  const inputRef = useRef(null);

  useEffect(() => {
    if (!inputRef.current) return;
    const $el = $(inputRef.current);

    const opts = {
      startDate: moment(start),
      endDate: moment(end),
      timePicker: true,
      timePicker24Hour: true,
      timePickerSeconds: false,
      autoUpdateInput: true,
      alwaysShowCalendars: true,
      opens: 'left',
      locale: {
        format: DISPLAY_FORMAT,
        applyLabel: 'Apply',
        cancelLabel: 'Cancel',
      },
      ranges: {
        Today: [moment().startOf('day'), moment().endOf('day')],
        Yesterday: [moment().subtract(1, 'day').startOf('day'), moment().subtract(1, 'day').endOf('day')],
        'Last 12 Hours': [moment().subtract(12, 'hours'), moment()],
        'Last 24 Hours': [moment().subtract(24, 'hours'), moment()],
        'Last 48 Hours': [moment().subtract(48, 'hours'), moment()],
        'Last 7 Days': [moment().subtract(6, 'days').startOf('day'), moment().endOf('day')],
        'This Month': [moment().startOf('month'), moment().endOf('month')],
        'Last Month': [
          moment().subtract(1, 'month').startOf('month'),
          moment().subtract(1, 'month').endOf('month'),
        ],
      },
    };

    $el.daterangepicker(opts, (s, e) => {
      onChange?.({ start: s.toDate(), end: e.toDate() });
    });

    $el.on('apply.daterangepicker', (_ev, picker) => {
      onChange?.({ start: picker.startDate.toDate(), end: picker.endDate.toDate() });
      onApply?.();
    });

    $el.val(`${moment(start).format(DISPLAY_FORMAT)} - ${moment(end).format(DISPLAY_FORMAT)}`);

    return () => {
      try { $el.data('daterangepicker')?.remove?.(); } catch {}
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    const $el = $(inputRef.current);
    const picker = $el.data('daterangepicker');
    if (picker) {
      picker.setStartDate(moment(start));
      picker.setEndDate(moment(end));
      $el.val(`${moment(start).format(DISPLAY_FORMAT)} - ${moment(end).format(DISPLAY_FORMAT)}`);
    }
  }, [start, end]);

  return (
    <div className="d-flex flex-column">
      <label className="small fw-medium">Range</label>
      <input
        ref={inputRef}
        type="text"
        className="form-control"
        disabled={disabled}
        /* Wider, so the last digits never clip */
        style={{ width: '36ch' }}
        readOnly
      />
    </div>
  );
}

/* ----------------------------------------------------------------------- */

export default function RocketChatRealtimeWidget({ config = {} }) {
  const defaultHours = Number(config.defaultHours ?? 48);
  const [rooms, setRooms] = useState([]);
  const [roomId, setRoomId] = useState(config.roomId || null);
  const [roomType, setRoomType] = useState('c'); // 'c' public, 'p' private

  const [oldest, setOldest] = useState(nowMinusHours(defaultHours));
  const [latest, setLatest] = useState(new Date());

  const [historyLoading, setHistoryLoading] = useState(false);
  const [err, setErr] = useState('');

  // messagesMap: id -> message (dedupe live vs history)
  const [messagesMap, setMessagesMap] = useState(new Map());
  const messagesArray = useMemo(() => {
    const arr = Array.from(messagesMap.values());
    arr.sort((a, b) => {
      const ta = new Date(a.ts).getTime() || 0;
      const tb = new Date(b.ts).getTime() || 0;
      if (ta !== tb) return ta - tb;
      return (a._id || '').localeCompare(b._id || '');
    });
    return arr;
  }, [messagesMap]);

  const ddpRef = useRef(null);
  const stopStreamRef = useRef(null);

  if (!config?.url || !config?.token) {
    return <div className="text-danger small">Rocket.Chat config missing (url/token)</div>;
  }

  // (re)load rooms and pick default based on user subscriptions
  async function resolveRoomsAndDefault(selectIfNone = !roomId) {
    const r = await fetch('/api/rocketchat/rooms');
    const data = await r.json();
    if (!data.rooms) throw new Error('No rooms returned');
    setRooms(data.rooms);

    if (!selectIfNone) return;

    // Load user subs to pick initial room
    let subs = [];
    try {
      const s = await fetch('/api/rocketchat/subscriptions', { credentials: 'same-origin' });
      const sj = await s.json();
      subs = Array.isArray(sj.subscriptions) ? sj.subscriptions : [];
      if (subs.length === 0 && sj?.defaults?.defaultRoomName) {
        const defByName = data.rooms.find(x => x.name === sj.defaults.defaultRoomName);
        if (defByName) {
          setRoomId(defByName.id);
          setRoomType(defByName.type || 'c');
          return;
        }
      }
    } catch { /* ignore */ }

    if (subs.length > 0) {
      const firstId = subs[0].room_id;
      const found = data.rooms.find(rm => rm.id === firstId) || data.rooms[0];
      if (found) {
        setRoomId(found.id);
        setRoomType(found.type || 'c');
        return;
      }
    }

    const byName =
      data.rooms.find(x => x.name === (config.defaultRoomName || 'repweb')) ||
      data.rooms[0];
    if (byName) {
      setRoomId(byName.id);
      setRoomType(byName.type || 'c');
    }
  }

  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        await resolveRoomsAndDefault(true);
      } catch (e) {
        if (!cancelled) setErr(String(e?.message || e));
      }
    })();
    return () => { cancelled = true; };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    const handler = async () => {
      try { await resolveRoomsAndDefault(!config.roomId); }
      catch (e) { setErr(String(e?.message || e)); }
    };
    window.addEventListener('rc:subs-updated', handler);
    return () => window.removeEventListener('rc:subs-updated', handler);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [config.roomId]);

  async function loadHistory(forRoomId = roomId, forType = roomType, from = oldest, to = latest) {
    if (!forRoomId) return;
    setHistoryLoading(true);
    setErr('');
    try {
      const url = new URL(window.location.origin + '/api/rocketchat/history');
      url.searchParams.set('roomId', forRoomId);
      url.searchParams.set('type', forType);
      url.searchParams.set('oldest', iso(from));
      url.searchParams.set('latest', iso(to));
      url.searchParams.set('limit', '2000');

      const r = await fetch(url.toString());
      const data = await r.json();
      if (data.error) throw new Error(data.error);
      const list = data.messages || [];

      setMessagesMap(() => {
        const m = new Map();
        for (const msg of list) {
          if (!msg._id && msg.id) msg._id = msg.id;
          m.set(msg._id, msg);
        }
        return m;
      });
    } catch (e) {
      setErr(String(e?.message || e));
    } finally {
      setHistoryLoading(false);
    }
  }

  async function connectDDP(forRoomId) {
    try { stopStreamRef.current?.stop?.(); } catch {}
    try { ddpRef.current?.connection?.disconnect?.(); } catch {}
    stopStreamRef.current = null;
    ddpRef.current = null;

    if (!forRoomId) return;

    try {
      const base = normalizeBase(config.url);
      const client = await DDPSDK.createAndConnect(base);
      await client.account.loginWithToken(config.token);

      const stopper = client.stream('room-messages', forRoomId, (msg) => {
        setMessagesMap(prev => {
          const m = new Map(prev);
          m.set(msg._id, msg);
          if (m.size > 300) {
            const toTrim = m.size - 300;
            const it = m.keys();
            for (let i = 0; i < toTrim; i++) m.delete(it.next().value);
          }
          return m;
        });
      });

      ddpRef.current = client;
      stopStreamRef.current = stopper;
    } catch (e) {
      console.error(e);
      setErr(e?.reason || e?.message || 'DDP connection failed');
    }
  }

  useEffect(() => {
    document.dispatchEvent(new CustomEvent('rc:chat-visible', { detail: { visible: true } }));
    return () => document.dispatchEvent(new CustomEvent('rc:chat-visible', { detail: { visible: false } }));
  }, []);

  useEffect(() => {
    if (!roomId) return;
    (async () => {
      await loadHistory(roomId, roomType, oldest, latest);
      await connectDDP(roomId);
    })();
    return () => {
      try { stopStreamRef.current?.stop?.(); } catch {}
      try { ddpRef.current?.connection?.disconnect?.(); } catch {}
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [roomId]);

  async function reloadRange() {
    if (!roomId) return;
    await loadHistory(roomId, roomType, oldest, latest);
  }

  // send message (DDP)
  const [text, setText] = useState('');
  async function sendMessage() {
    const trimmed = text.trim();
    if (!ddpRef.current || !trimmed) return;
    try {
      await ddpRef.current.call('sendMessage', { rid: roomId, msg: trimmed });
      setText('');
    } catch (e) {
      console.error(e);
      setErr(e?.reason || e?.message || 'Failed to send message');
    }
  }

  const onSelectRoom = (id) => {
    const r = rooms.find(x => x.id === id);
    setRoomId(id);
    setRoomType(r?.type || 'c');
  };

  // ---------- Rich content renderer (attachments & link previews) ----------
  function renderRichContent(m) {
    const blocks = [];

    if (Array.isArray(m.attachments)) blocks.push(...m.attachments);

    if (Array.isArray(m.urls)) {
      m.urls.forEach(u => {
        const img =
          u.meta?.ogImage?.secure_url || u.meta?.ogImage?.url || u.meta?.ogImage ||
          u.meta?.image || u.image;
        const desc = u.meta?.ogDescription || u.description;
        if (img || desc) {
          blocks.push({ title: u.url, title_link: u.url, text: desc, image_url: img });
        }
      });
    }

    if (blocks.length === 0) return null;

    return (
      <div className="mt-2">
        {blocks.map((a, i) => (
          <div key={i} className="border rounded p-2 mb-2" style={{ background: '#f8f9fa' }}>
            {a.title_link ? (
              <a
                href={a.title_link}
                target="_blank"
                rel="noopener noreferrer"
                className="fw-semibold d-inline-block mb-1 text-decoration-none"
              >
                {a.title || a.title_link}
              </a>
            ) : a.title ? (
              <div className="fw-semibold mb-1">{a.title}</div>
            ) : null}

            {(a.text || a.description) && (
              <div className="mb-2" style={{ whiteSpace: 'pre-wrap' }}>
                {a.text || a.description}
              </div>
            )}

            {a.image_url && (
              <img
                src={a.image_url}
                alt=""
                style={{ maxWidth: '100%', height: 'auto', borderRadius: 6 }}
              />
            )}
          </div>
        ))}
      </div>
    );
  }

  if (err) return <div className="text-danger small">Rocket.Chat error: {err}</div>;

  return (
    <div className="p-3 h-100 d-flex flex-column gap-2">
      <div className="fw-semibold">Rocket.Chat — Live + History</div>

      {/* Controls */}
      <div className="d-flex flex-wrap align-items-end gap-3">
        <div className="d-flex flex-column">
          <label className="small fw-medium">Channel</label>
          <select
            className="form-select"
            value={roomId || ''}
            onChange={(e) => onSelectRoom(e.target.value)}
          >
            {rooms.map(r => (
              <option key={`${r.type}-${r.id}`} value={r.id}>
                {r.type === 'p' ? '🔒 ' : '# '}{r.name}
              </option>
            ))}
          </select>
        </div>

        <RangePicker
          start={oldest}
          end={latest}
          disabled={!roomId || historyLoading}
          onChange={({ start, end }) => { setOldest(start); setLatest(end); }}
          onApply={reloadRange}
        />

        <button
          className="btn btn-outline-secondary"
          onClick={reloadRange}
          disabled={!roomId || historyLoading}
        >
          {historyLoading ? 'Loading…' : 'Load'}
        </button>
      </div>

      {/* Messages */}
      <div className="flex-grow-1 overflow-auto border rounded p-2 bg-white">
        {messagesArray.length === 0 && !historyLoading && (
          <div className="text-muted">No messages in selected range.</div>
        )}

        {messagesArray.map((m) => (
          <div key={m._id} className="mb-3">
            <div className="small text-muted">
              <span className="fw-medium">{m.u?.name || m.u?.username || 'unknown'}</span>
              {' · '}
              {m.ts ? moment(m.ts).format(DISPLAY_FORMAT) : ''}
            </div>

            {(m.msg || m.text) && (
              <div className="lh-base" style={{ whiteSpace: 'pre-wrap' }}>
                {m.msg || m.text}
              </div>
            )}

            {renderRichContent(m)}
          </div>
        ))}

        {historyLoading && <div className="text-muted">Loading…</div>}
      </div>

      {/* Composer */}
      <div className="d-flex gap-2">
        <input
          className="form-control"
          value={text}
          onChange={(e) => setText(e.target.value)}
          onKeyDown={(e) => e.key === 'Enter' && sendMessage()}
          placeholder="Type a message…"
        />
        <button className="btn btn-primary" onClick={sendMessage}>Send</button>
      </div>
    </div>
  );
}
