import React, { useEffect, useMemo, useState } from 'react';

const STATE_BADGE = {
  host: (h) => {
    if (h.down) return { label: 'DOWN', cls: 'badge bg-danger' };
    if (h.unreach) return { label: 'UNREACH', cls: 'badge bg-warning' };
    return { label: 'UP', cls: 'badge bg-success' };
  },
  svc: (s) => {
    if (s === 2) return { label: 'CRIT', cls: 'badge bg-danger' };
    if (s === 1) return { label: 'WARN', cls: 'badge bg-warning' };
    if (s === 3) return { label: 'UNKN', cls: 'badge bg-secondary' };
    return { label: 'OK', cls: 'badge bg-success' };
  }
};

const Kpi = ({ title, value, sub, className = '' }) => (
  <div className={`card shadow-sm ${className}`}>
    <div className="card-body py-3">
      <div className="small text-muted">{title}</div>
      <div className="h3 m-0">{value}</div>
      {sub && <div className="small mt-1">{sub}</div>}
    </div>
  </div>
);

export default function CheckmkWidget({ apiBase = '/api/checkmk', refreshMs = 30000 }) {
  const [data, setData] = useState(null);
  const [err, setErr] = useState(null);
  const [busy, setBusy] = useState(false);

  const load = async () => {
    try {
      setBusy(true);
      const rsp = await fetch(`${apiBase}/summary`);
      const js = await rsp.json();
      if (!rsp.ok) throw new Error(js?.error || `HTTP ${rsp.status}`);
      setData(js);
      setErr(null);
    } catch (e) {
      setErr(String(e.message || e));
    } finally {
      setBusy(false);
    }
  };

  useEffect(() => {
    load();
    const t = setInterval(load, Math.max(5000, refreshMs));
    return () => clearInterval(t);
  }, [apiBase, refreshMs]);

  const ts = data?.ts ? new Date(data.ts) : null;
  const hosts = data?.hosts || {};
  const svcs = data?.services || {};
  const problems = data?.problems || [];

  const hostSub = useMemo(() => {
    const parts = [];
    if (hosts.up) parts.push(<span key="up" className="me-2"><span className="badge bg-success">UP</span> {hosts.up}</span>);
    if (hosts.down) parts.push(<span key="down" className="me-2"><span className="badge bg-danger">DOWN</span> {hosts.down}</span>);
    if (hosts.unreach) parts.push(<span key="unreach"><span className="badge bg-warning">UNREACH</span> {hosts.unreach}</span>);
    return parts;
  }, [hosts]);

  const svcSub = useMemo(() => {
    const parts = [];
    if (svcs.ok) parts.push(<span key="ok" className="me-2"><span className="badge bg-success">OK</span> {svcs.ok}</span>);
    if (svcs.warn) parts.push(<span key="warn" className="me-2"><span className="badge bg-warning">WARN</span> {svcs.warn}</span>);
    if (svcs.crit) parts.push(<span key="crit" className="me-2"><span className="badge bg-danger">CRIT</span> {svcs.crit}</span>);
    if (svcs.unknown) parts.push(<span key="unk"><span className="badge bg-secondary">UNKN</span> {svcs.unknown}</span>);
    return parts;
  }, [svcs]);

  return (
    <div className="h-100 d-flex flex-column">
      <div className="d-flex align-items-center justify-content-between mb-2">
        <div className="fw-semibold">CheckMK Health</div>
        <div className="small text-muted">
          {busy ? 'Refreshing…' : ts ? `Updated ${ts.toLocaleTimeString()}` : '—'}
        </div>
      </div>

      {err && (
        <div className="alert alert-danger py-2">
          <strong>Error:</strong> {err}
        </div>
      )}

      <div className="row g-2">
        <div className="col-6">
          <Kpi title="Hosts" value={hosts.total ?? '—'} sub={<>{hostSub}</>} />
        </div>
        <div className="col-6">
          <Kpi title="Services" value={svcs.total ?? '—'} sub={<>{svcSub}</>} />
        </div>
      </div>

      <div className="mt-3 card flex-grow-1 overflow-auto">
        <div className="card-header py-2">Top Problems</div>
        <div className="list-group list-group-flush">
          {problems.length === 0 && (
            <div className="list-group-item small text-muted">No problems 🎉</div>
          )}
          {problems.map((p, idx) => {
            const badge = STATE_BADGE.svc(p.state);
            return (
              <div key={idx} className="list-group-item py-2">
                <div className="d-flex justify-content-between">
                  <div className="me-2 text-truncate">
                    <div className="text-truncate"><strong>{p.host}</strong></div>
                    <div className="text-truncate small">{p.service}</div>
                  </div>
                  <div className="ms-2"><span className={badge.cls}>{badge.label}</span></div>
                </div>
                {p.output && <div className="small text-muted mt-1 text-truncate">{p.output}</div>}
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}
