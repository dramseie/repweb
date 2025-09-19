import React, { useEffect, useMemo, useState } from "react";

/** Small badge */
const Badge = ({ label, value, cls }) => (
  <span className={`badge bg-${cls} me-1`}>{label}: {value ?? 0}</span>
);

/** One row in the list */
function Row({ node, level }) {
  const s = node.status || {};
  const hasIssues = (s.invalidCount || 0) > 0 || (s.disabledCount || 0) > 0;
  const allStopped = (s.runningCount || 0) === 0 && (s.stoppedCount || 0) > 0;
  const dot = hasIssues ? "bg-danger" : allStopped ? "bg-secondary" : "bg-success";

  return (
    <div className="d-flex align-items-center py-2 border-bottom">
      <div style={{ width: 16 * level }} />
      <span className={`rounded-circle ${dot}`} style={{ width: 10, height: 10, display: "inline-block" }} />
      <span className="ms-2">{node.name}</span>
      <div className="ms-auto">
        <Badge label="run"    value={s.runningCount}      cls="success" />
        <Badge label="stop"   value={s.stoppedCount}      cls="secondary" />
        <Badge label="inv"    value={s.invalidCount}      cls="danger" />
        <Badge label="dis"    value={s.disabledCount}     cls="warning" />
        <Badge label="thr"    value={s.activeThreadCount} cls="info" />
        <Badge label="queued" value={s.flowFilesQueued}   cls="dark" />
      </div>
    </div>
  );
}

/**
 * Widget body
 * Expected to be used like other widgets: it renders a Bootstrap "card" itself.
 * If your dashboard wraps widgets in a card, you can strip the outer <div className="card">.
 */
export default function NiFiWidget({ title = "NiFi Process Groups", refreshSec = 300 }) {
  const [items, setItems] = useState([]);
  const [ts, setTs] = useState(0);
  const [loading, setLoading] = useState(true);
  const [onlyProblem, setOnlyProblem] = useState(false);

  const fetchData = async () => {
    try {
      setLoading(true);
      const r = await fetch("/api/nifi/process-groups/status");
      const j = await r.json();
      setItems(Array.isArray(j.items) ? j.items : []);
      setTs(j.ts || Math.floor(Date.now() / 1000));
    } catch (e) {
      console.error("NiFiWidget fetch error:", e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
    const t = setInterval(fetchData, (refreshSec || 300) * 1000);
    return () => clearInterval(t);
  }, [refreshSec]);

  // Build a tree order with level for indentation
  const rows = useMemo(() => {
    const children = new Map();
    items.forEach(i => {
      const pid = i.parentId ?? null;
      if (!children.has(pid)) children.set(pid, []);
      children.get(pid).push(i);
    });
    const out = [];
    const walk = (pid, level) => {
      (children.get(pid) || []).forEach(n => {
        out.push({ ...n, _level: level });
        walk(n.id, level + 1);
      });
    };
    walk(null, 0);
    return out;
  }, [items]);

  const filtered = useMemo(() => {
    if (!onlyProblem) return rows;
    return rows.filter(n => {
      const s = n.status || {};
      return (s.invalidCount || 0) > 0 || (s.disabledCount || 0) > 0 || (s.flowFilesQueued || 0) > 0;
    });
  }, [rows, onlyProblem]);

  return (
    <div className="card shadow-sm h-100">
      <div className="card-header d-flex align-items-center">
        <span className="fw-bold">{title}</span>
        <div className="ms-auto d-flex align-items-center">
          <div className="form-check form-switch me-3">
            <input id="onlyProblem" className="form-check-input" type="checkbox"
                   checked={onlyProblem} onChange={e => setOnlyProblem(e.target.checked)} />
            <label className="form-check-label" htmlFor="onlyProblem">Only issues/queued</label>
          </div>
          <button className="btn btn-sm btn-outline-primary" onClick={fetchData} disabled={loading}>
            {loading ? "Refreshing…" : "Refresh"}
          </button>
        </div>
      </div>
      <div className="card-body p-0">
        {filtered.length === 0 && !loading && (
          <div className="p-3 text-muted">No process groups found.</div>
        )}
        {filtered.map(n => <Row key={n.id} node={n} level={n._level} />)}
      </div>
      <div className="card-footer small text-muted">
        Updated: {new Date((ts || 0) * 1000).toLocaleString()}
      </div>
    </div>
  );
}
