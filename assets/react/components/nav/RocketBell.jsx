// assets/react/components/nav/RocketBell.jsx
import React, { useEffect, useRef, useState } from 'react';
import { RC_DDP } from '../../services/RocketChatDDP';

const styles = `
@keyframes repweb-bell-shake {
  0%, 100% { transform: rotate(0); }
  10% { transform: rotate(-15deg); }
  20% { transform: rotate(12deg); }
  30% { transform: rotate(-10deg); }
  40% { transform: rotate(8deg); }
  50% { transform: rotate(-6deg); }
  60% { transform: rotate(4deg); }
  70% { transform: rotate(-2deg); }
  80% { transform: rotate(1deg); }
  90% { transform: rotate(-1deg); }
}
.repweb-bell {
  position: relative;
  cursor: pointer;
  line-height: 1;
}
.repweb-bell.shake {
  animation: repweb-bell-shake 0.8s ease-in-out;
}
.repweb-bell .badge {
  position: absolute;
  top: -6px; right: -6px;
  min-width: 18px; height: 18px;
  padding: 0 4px;
  font-size: 11px;
  border-radius: 9px;
  background: #dc3545; /* bootstrap danger */
  color: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
`;

export default function RocketBell({
  // DDP config
  url,
  token,
  // Which rooms to watch (array of Rocket.Chat room IDs)
  roomIds = [],
  // Optional click handler (e.g., open chat page or navigate)
  onClick,
}) {
  const [unseen, setUnseen] = useState(0);
  const [recentShake, setRecentShake] = useState(false);
  const visibleRef = useRef(false); // whether a chat widget is currently visible

  // Allow the chat widget to tell us when it is visible/hidden
  useEffect(() => {
    const onVisible = (e) => { visibleRef.current = !!e.detail?.visible; };
    document.addEventListener('rc:chat-visible', onVisible);
    return () => document.removeEventListener('rc:chat-visible', onVisible);
  }, []);

	// Establish DDP + subscribe to rooms (from props OR user prefs)
	useEffect(() => {
	  let mounted = true;

	  (async () => {
		try {
		  await RC_DDP.connect({ url, token });

		  let ids = roomIds;
		  if (!ids || ids.length === 0) {
			const res = await fetch('/api/rocketchat/subscriptions', { credentials: 'same-origin' });
			const data = await res.json();
			ids = (data.subscriptions || []).map(s => s.room_id);
			// Fallback to default name if still empty
			if ((!ids || ids.length === 0) && data?.defaults?.defaultRoomName) {
			  const rooms = await fetch('/api/rocketchat/rooms').then(r=>r.json());
			  const def = (rooms.rooms || []).find(r => r.name === data.defaults.defaultRoomName);
			  if (def) ids = [def.id];
			}
		  }

		  await RC_DDP.watchRooms(ids || []);
		} catch (e) {
		  console.error('RocketBell connect error:', e);
		}
	  })();

	  const off = RC_DDP.onMessage(({ roomId, msg }) => {
		if (!mounted) return;
		// … existing unseen/shake logic …
	  });

	  return () => { mounted = false; off(); };
	}, [url, token, (roomIds||[]).join(',')]);


  const handleClick = () => {
    setUnseen(0);
    onClick?.();
  };

  return (
    <>
      <style>{styles}</style>
      <div className={`repweb-bell ${recentShake ? 'shake' : ''}`} onClick={handleClick} title="Rocket.Chat notifications">
        {/* Simple bell SVG (no external deps) */}
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
          <path d="M18 8a6 6 0 10-12 0c0 7-3 7-3 7h18s-3 0-3-7" />
          <path d="M13.73 21a2 2 0 01-3.46 0" />
        </svg>
        {unseen > 0 && <span className="badge">{unseen > 99 ? '99+' : unseen}</span>}
      </div>
    </>
  );
}
