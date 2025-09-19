import React, { useEffect, useMemo, useState } from "react";

const StatusBadge = ({ label, value, variant }) => (
  <span className={`badge bg-${variant} me-1`}>
    {label}: {value}
  </span>
);

function Row({ node, level }) {
  // derive a simple health signal
  const s = node.status || {};
  const hasIssues = (s.invalidCount || 0) > 0 || (s.disabledCount || 0) > 0;
  const allStopped = (s.runningCount || 0) === 0 && (s.stoppedCount || 0) > 0;

  const dotClass = hasIssues ? "bg-danger" : allStopped ? "bg-secondary" : "bg-success";

  return (
    <div className="d-flex align-items-center py-2 border-bottom">
      <div style={{ width: 16 * level }} />
      <span className={`rounded-circle ${dotClass}`} style={{ width: 10, height: 10, display: "inline-block" }} />
      <span className="ms-2 fw-semibold">{node.name}</span>
      <div className="ms-auto">
        <StatusBadge label="run" value={s.runningCount ?? 0} variant="success" />
        <StatusBadge label="stop" value={s.stoppedCount ?? 0} variant="secondary" />
        <StatusBadge label="inv" value={s.invalidCount ?? 0} variant="danger" />
        <StatusBadge label="dis" value={s.disabledCount ?? 0} variant="warning" />
        <StatusBadge label="thr" value={s.activeThreadCount ?? 0} variant="info" />
        <StatusBadge label="queued" value={s.flowFilesQueued ?? 0} variant="dark" />
      </div>
    </div>
  );
}

export default function NiFiPgStatusWidget({ refreshSec = 300 }) {
  const [items, setItems] = useState([]);
  const [ts, setTs] = useState(0);
  const [loading, setLoading] = useState(true);
  const [onlyProblem, setOnlyProblem] = useState(false);

  const fetchData = async () => {
    try {
      setLoading(true);
      const r = await fetch("/api/nifi/process-groups/status");
      const j = await r.json();
      setItems(j.items || []);
      setTs(j.ts || Date.now() / 1000);
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
    const t = setInterval(fetchData, refreshSec * 1000);
    return () => clearInterval(t);
  }, [refreshSec]);

  // Build a parent->children map and compute levels for indentation
  const treeWithLevel = useMemo(() => {
    const byId = new Map(items.map(i => [i.id, i]));
    const children = new Map();
    items.forEach(i => {
      const pid = i.parentId || null;
      if (!children.has(pid)) children.set(pid, []);
      children.get(pid).push(i);
    });

    const res = [];
    const walk = (id, level) => {
      const ch = children.get(id) || [];
      ch.forEach(n => {
        res.push({ ...n, _level: level });
        walk(n.id, level + 1);
      });
    };

    // Find root(s): parentId === null
    walk(null, 0);
    return res;
  }, [items]);

  const filtered = useMemo(() => {
    if (!onlyProblem) return treeWithLevel;
    return treeWithLevel.filter(n => {
      const s = n.status || {};
      return (s.invalidCount || 0) > 0 || (s.disabledCount || 0) > 0 || (s.flowFilesQueued || 0) > 0;
    });
  }, [treeWithLevel, onlyProblem]);

  return (
    <div className="card shadow-sm">
      <div className="card-header d-flex align-items-center">
        <span className="fw-bold">NiFi Process Groups</span>
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
        {filtered.map(n => (
          <Row key={n.id} node={n} level={n._level} />
        ))}
      </div>
      <div className="card-footer small text-muted">
        Updated: {new Date((ts || 0) * 1000).toLocaleString()}
      </div>
    </div>
  );
}
