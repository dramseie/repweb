// assets/react/components/WidgetsHomeTabs.jsx
import React, { useEffect, useState, useCallback } from 'react';
import WidgetsDashboard from './WidgetsDashboard';

/**
 * Props (all optional; sensible defaults provided):
 * - apiTabs:    list/create/reset tabs endpoints base (GET/POST) -> '/api/widgets/tabs'
 * - apiWidgets: widgets endpoints base per tab (we'll call `${apiWidgets}/tabs/:id/...`)
 * - resetUrl:   POST to reset user tabs to system defaults -> '/api/widgets/tabs/reset-to-defaults'
 */
export default function WidgetsHomeTabs({
  apiTabs = '/api/widgets/tabs',
  apiWidgets = '/api/widgets',
  resetUrl = '/api/widgets/tabs/reset-to-defaults',
}) {
  const [tabs, setTabs] = useState([]);
  const [active, setActive] = useState(null);
  const [err, setErr] = useState(null);
  const [loading, setLoading] = useState(true);

  // Normalize + sort helper (handles snake_case or camelCase keys)
  const sortTabs = useCallback((arr) => {
    const toNum = (v) => (v == null ? 0 : Number(v));
    return [...(arr || [])]
      .map(t => ({ ...t, sortOrder: t.sortOrder ?? t.sort_order ?? t.position ?? 0 }))
      .sort((a, b) => (toNum(a.sortOrder) - toNum(b.sortOrder)) || (toNum(a.id) - toNum(b.id)));
  }, []);

  const load = useCallback(async () => {
    try {
      setLoading(true);
      setErr(null);
      const r = await fetch(apiTabs, { cache: 'no-store' });
      if (!r.ok) throw new Error(`Failed to load tabs (${r.status})`);
      const data = await r.json();
      const sorted = sortTabs(data);
      setTabs(sorted);
      // keep current active tab if still present, otherwise pick first
      if (sorted.length) {
        const keep = sorted.find(t => t.id === active);
        setActive(keep ? keep.id : sorted[0].id);
      } else {
        setActive(null);
      }
    } catch (e) {
      console.error(e);
      setErr(e.message || 'Failed to load tabs');
    } finally {
      setLoading(false);
    }
  }, [apiTabs, active, sortTabs]);

  useEffect(() => { load(); }, [load]);

  // Refresh tabs after external changes (e.g., ManageTabsModal closes with changes)
  useEffect(() => {
    const refresh = () => load();
    window.addEventListener('ui.tabs.changed', refresh);
    return () => window.removeEventListener('ui.tabs.changed', refresh);
  }, [load]);

  const addTab = async () => {
    await fetch(apiTabs, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ title: 'New tab' }),
    });
    await load();
  };

  const resetDefaults = async () => {
    await fetch(resetUrl, { method: 'POST' });
    await load();
  };

  const current = tabs.find(t => t.id === active);

  return (
    <div className="widgets-home">
      <ul className="nav nav-tabs mb-3">
        {tabs.map(t => (
          <li key={t.id} className="nav-item">
            <button
              className={`nav-link ${t.id === active ? 'active' : ''}`}
              onClick={() => setActive(t.id)}
            >
              {t.title}
            </button>
          </li>
        ))}

      </ul>

      {loading && !err && <div className="text-muted">Loading…</div>}
      {err && <div className="alert alert-danger">{err}</div>}

      {!loading && !err && current ? (
        <WidgetsDashboard apiBase={`${apiWidgets}/tabs/${current.id}`} />
      ) : (
        !loading && !err && <div className="text-muted">No tab selected.</div>
      )}
    </div>
  );
}
