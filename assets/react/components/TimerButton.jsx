// ./assets/react/components/TimerButton.jsx
import React, { useEffect, useRef, useState } from 'react';

const pad = n => String(n).padStart(2, '0');
const fmt = (ms) => {
  const s = Math.max(0, Math.floor(ms / 1000));
  const h = Math.floor(s / 3600);
  const m = Math.floor((s % 3600) / 60);
  const ss = s % 60;
  return `${pad(h)}:${pad(m)}:${pad(ss)}`;
};

export default function TimerButton({
  appointmentId,
  className = '',
  onStart,
  onPause,
  onResume,
  onStop,
}) {
  // running: true when counting; `pausedAt` non-null means paused
  const [running, setRunning] = useState(false);
  const startedAtRef   = useRef(null);   // epoch ms when timer was started
  const pausedTotalRef = useRef(0);      // total paused ms accumulated
  const pausedAtRef    = useRef(null);   // epoch ms when last pause started
  const [, force]      = useState(0);    // re-render ticker

  const storageKey = appointmentId ? `pos:timer:${appointmentId}` : null;

  // ---- restore from storage when appointment changes
  useEffect(() => {
    if (!storageKey) { resetAll(); return; }
    try {
      const raw = localStorage.getItem(storageKey);
      if (raw) {
        const s = JSON.parse(raw);
        startedAtRef.current   = s.startedAt ?? null;
        pausedTotalRef.current = s.pausedTotal ?? 0;
        pausedAtRef.current    = s.pausedAt ?? null;
        setRunning(!!s.running);
      } else {
        resetAll();
      }
    } catch { resetAll(); }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [storageKey]);

  // ---- persist
  useEffect(() => {
    if (!storageKey) return;
    try {
      localStorage.setItem(storageKey, JSON.stringify({
        running,
        startedAt: startedAtRef.current,
        pausedTotal: pausedTotalRef.current,
        pausedAt: pausedAtRef.current,
      }));
    } catch {}
  }, [running, storageKey]);

  // ---- ticker while running (no need to tick while paused)
  useEffect(() => {
    if (!running || !startedAtRef.current) return;
    const t = setInterval(() => force(x => x + 1), 1000);
    return () => clearInterval(t);
  }, [running]);

  const elapsedMs = () => {
    const st = startedAtRef.current;
    if (!st) return 0;
    const now = Date.now();
    const pausedLive = pausedAtRef.current ? (now - pausedAtRef.current) : 0;
    // total elapsed = (now - start) - (total paused + live pause)
    return (now - st) - (pausedTotalRef.current + pausedLive);
  };

  const start = async () => {
    if (!appointmentId || running || startedAtRef.current) return;
    // backend "real_start_at"
    try {
      const nowSql = new Date(Date.now() - new Date().getTimezoneOffset() * 60000)
        .toISOString().slice(0, 19).replace('T', ' ');
      await fetch(`/api/pos/appointments/${appointmentId}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ real_start_at: nowSql, status: 'booked' }),
      }).catch(()=>{});
    } finally {
      startedAtRef.current = Date.now();
      pausedTotalRef.current = 0;
      pausedAtRef.current = null;
      setRunning(true);
      onStart?.();
    }
  };

  const pause = () => {
    if (!running || pausedAtRef.current != null) return;
    pausedAtRef.current = Date.now();
    setRunning(false);
    onPause?.();
  };

  const resume = () => {
    if (running || pausedAtRef.current == null) return;
    const pausedFor = Date.now() - pausedAtRef.current;
    pausedTotalRef.current += pausedFor;
    pausedAtRef.current = null;
    setRunning(true);
    onResume?.();
  };

  const resetAll = () => {
    setRunning(false);
    startedAtRef.current = null;
    pausedTotalRef.current = 0;
    pausedAtRef.current = null;
  };

  const stop = () => {
    resetAll();
    if (storageKey) {
      try { localStorage.removeItem(storageKey); } catch {}
    }
    onStop?.();
  };

  const disabled = !appointmentId;

  return (
    <div className={`d-flex align-items-center gap-2 ${className}`}>
      <span className="badge text-bg-dark" title="Temps écoulé">
        {fmt(elapsedMs())}
      </span>

      {!startedAtRef.current && (
        <button
          type="button"
          className="btn btn-sm btn-outline-primary"
          disabled={disabled}
          onClick={start}
          title={disabled ? 'Sélectionnez un rendez-vous' : 'Démarrer le timer'}
        >
          ⏱️ Start
        </button>
      )}

      {startedAtRef.current && running && (
        <button type="button" className="btn btn-sm btn-outline-warning" onClick={pause} title="Mettre en pause">
          ⏸️ Pause
        </button>
      )}

      {startedAtRef.current && !running && (
        <button type="button" className="btn btn-sm btn-outline-success" onClick={resume} title="Reprendre">
          ▶️ Reprendre
        </button>
      )}

      {startedAtRef.current && (
        <button type="button" className="btn btn-sm btn-outline-danger" onClick={stop} title="Arrêter / Réinitialiser">
          ■ Stop
        </button>
      )}
    </div>
  );
}
