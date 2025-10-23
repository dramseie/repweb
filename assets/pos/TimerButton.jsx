// assets/react/components/TimerButton.jsx
import React, { useEffect, useMemo, useRef, useState } from "react";

export default function TimerButton({ orderId }) {
  const [status, setStatus] = useState("idle");      // idle | running | paused | finished
  const [total, setTotal]   = useState(0);           // seconds (server-computed)
  const tickRef = useRef(null);

  const load = async () => {
    if (!orderId) return;
    const r = await fetch(`/api/pos/orders/${orderId}/timer/status`);
    const d = await r.json();
    setStatus(d.status || "idle");
    setTotal(d.totalSeconds || 0);
  };

  useEffect(() => {
    load();
    return () => tickRef.current && clearInterval(tickRef.current);
  }, [orderId]);

  // Smooth repaint + periodic server sync to avoid drift
  useEffect(() => {
    if (tickRef.current) clearInterval(tickRef.current);
    tickRef.current = setInterval(() => {
      // repaint every second; re-poll server every 15s when running
      setTotal(t => t + (status === "running" ? 1 : 0));
    }, 1000);
    let sync;
    if (status === "running") {
      sync = setInterval(load, 15000);
    }
    return () => { if (sync) clearInterval(sync); };
  }, [status]);

  const fmt = (s) => {
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const sec = s % 60;
    return h ? `${h}:${String(m).padStart(2,"0")}:${String(sec).padStart(2,"0")}` 
             : `${m}:${String(sec).padStart(2,"0")}`;
  };
  const display = useMemo(() => fmt(total), [total]);

  const toggle = async () => {
    if (!orderId) return;
    if (status === "running") {
      await fetch(`/api/pos/orders/${orderId}/timer/pause`, { method: "POST" });
    } else {
      await fetch(`/api/pos/orders/${orderId}/timer/start`, { method: "POST" });
    }
    await load();
  };

  const finish = async () => {
    if (!orderId) return;
    await fetch(`/api/pos/orders/${orderId}/timer/finish`, { method: "POST" });
    await load();
  };

  const isRunning = status === "running";
  const isPaused  = status === "paused";

  return (
    <div className="d-flex align-items-center gap-2">
      <button
        type="button"
        className={`btn btn-sm ${isRunning ? "btn-warning" : "btn-success"}`}
        onClick={toggle}
        title={isRunning ? "Mettre en pause" : "Démarrer"}
      >
        {isRunning ? "Pause" : "Timer"}
      </button>

      <span className="badge bg-light text-dark" style={{fontFamily:'monospace'}}>
        {display}
      </span>

      {(isRunning || isPaused) && (
        <button type="button" className="btn btn-sm btn-secondary" onClick={finish} title="Terminer">
          Terminer
        </button>
      )}
    </div>
  );
}
