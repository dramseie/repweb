// assets/react/components/widgets/RocketChatLoaderWidget.jsx
import React, { useEffect, useState } from 'react';
import RocketChatRealtimeWidget from './RocketChatRealtimeWidget';

export default function RocketChatLoaderWidget({
  configUrl = '/api/rocketchat/config',
  roomName = 'repweb',
}) {
  const [cfg, setCfg] = useState(null);
  const [err, setErr] = useState(null);

  useEffect(() => {
    const url = `${configUrl}?roomName=${encodeURIComponent(roomName)}`;
    fetch(url, { cache: 'no-store' })
      .then(async r => {
        const t = await r.text();
        if (!r.ok) throw new Error(`HTTP ${r.status}: ${t}`);
        return JSON.parse(t);
      })
      .then(setCfg)
      .catch(e => setErr(e.message));
  }, [configUrl, roomName]);

  if (err) return <div className="text-danger small">{err}</div>;
  if (!cfg) return <div className="text-muted small">Connecting to Rocket.Chat…</div>;

  // ✅ pass a single config object
  return <RocketChatRealtimeWidget config={cfg} />;
}
