// assets/react/components/PivotReport.jsx
import React, { useEffect, useMemo, useState } from 'react';
import PivotTableUI from 'react-pivottable/PivotTableUI';
import 'react-pivottable/pivottable.css';
import ErrorBoundary from './ErrorBoundary';

// Normalize renderer module shapes (ESM/CJS)
import TableRenderersImport from 'react-pivottable/TableRenderers';
import PlotlyRenderersFactoryImport from 'react-pivottable/PlotlyRenderers';

import Plotly from 'plotly.js-dist-min';

// Ensure global Plotly for any consumers expecting window.Plotly
if (typeof window !== 'undefined' && !window.Plotly) window.Plotly = Plotly;

// Resolve default/namespace exports
const TableRenderers =
  (TableRenderersImport && TableRenderersImport.default) || TableRenderersImport;

const PlotlyRenderersFactory =
  (PlotlyRenderersFactoryImport && PlotlyRenderersFactoryImport.default) ||
  PlotlyRenderersFactoryImport;

// Keys safe to persist (no functions!)
const SAFE_UI_KEYS = [
  'rows',
  'cols',
  'vals',
  'valueFilter',
  'rowOrder',
  'colOrder',
  'rendererName',
  'aggregatorName',
  'unusedOrientationCutoff',
  'sorters',
  'derivedAttributes',
];

function pickSafe(next) {
  const out = {};
  for (const k of SAFE_UI_KEYS) {
    if (k in next) out[k] = next[k];
  }
  // Normalize shapes that must be plain objects
  if (out.valueFilter && typeof out.valueFilter !== 'object') out.valueFilter = {};
  if (out.sorters && typeof out.sorters !== 'object') out.sorters = {};
  if (out.derivedAttributes && typeof out.derivedAttributes !== 'object') out.derivedAttributes = {};
  return out;
}

// Fallback UI if pivot crashes
function PivotFallback({ error, onReset }) {
  return (
    <div className="alert alert-warning">
      <div className="d-flex align-items-start gap-2">
        <div>
          <strong>Pivot rendering failed.</strong>
          <div className="small text-muted">{String(error?.message || error)}</div>
          <div className="mt-2 d-flex gap-2">
            <button className="btn btn-sm btn-outline-secondary" onClick={onReset}>
              Try again
            </button>
            <button
              className="btn btn-sm btn-outline-primary"
              onClick={() => {
                onReset();
                // Clear saved layout (common cause: invalid renderer/aggregator)
                try {
                  const m = location?.pathname?.match(/\/pivot\/(\d+)/);
                  if (m) localStorage.removeItem(`pivot-ui-state:${m[1]}`);
                } catch {}
              }}
            >
              Reset layout & retry
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

// Inject minimal CSS for fullscreen shell once per page
function usePivotFullscreenStyles() {
  useEffect(() => {
    const id = 'pivot-fullscreen-styles';
    if (document.getElementById(id)) return;
    const style = document.createElement('style');
    style.id = id;
    style.textContent = `
      .pivot-shell{display:flex;flex-direction:column;gap:.5rem;}
      .pivot-toolbar{display:flex;align-items:center;gap:.75rem;}
      .pivot-toolbar .spacer{flex:1;}
      .pivot-body{flex:1;min-height:0;overflow:hidden;}
      .pivot-ui-wrap{height:100%;width:100%;overflow:auto;padding:.25rem;border-radius:.5rem;border:1px solid #e5e7eb;background:#fff;}
      .pivot-fullscreen{position:fixed;inset:0;z-index:1050;background:#fff;padding:1rem;}
      .pivot-fullscreen .pivot-ui-wrap{border:1px solid #e5e7eb;}
      /* let react-pivottable stretch nicely */
      .pvtUi{max-width:none;}
      .pvtUi > div{min-width:0;}
      .pvtAxisContainer,.pvtRendererArea{overflow:auto;}
      /* optional: slightly smaller table font in fullscreen */
      .pivot-fullscreen .pvtTable{font-size:12px;}
    `;
    document.head.appendChild(style);
  }, []);
}

export default function PivotReport({
  reportId,
  apiBase = '/api/pivot',
  height = 650,
}) {
  usePivotFullscreenStyles();

  const [rows, setRows] = useState([]);
  const [columns, setColumns] = useState([]);
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState(null);
  const [isFullscreen, setIsFullscreen] = useState(false);

  // lock body scroll when fullscreen
  useEffect(() => {
    if (!isFullscreen) return;
    const prev = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => { document.body.style.overflow = prev; };
  }, [isFullscreen]);

  const storageKey = `pivot-ui-state:${reportId}`;

  // Load only safe UI state from storage
  const [ui, setUi] = useState(() => {
    try {
      const saved = JSON.parse(localStorage.getItem(storageKey) || '{}');
      return pickSafe(saved);
    } catch {
      return {};
    }
  });

  // Build renderer maps once
  const PlotlyRenderers = useMemo(() => PlotlyRenderersFactory(Plotly), []);
  const mergedRenderers = useMemo(
    () => ({ ...TableRenderers, ...PlotlyRenderers }),
    [PlotlyRenderers]
  );

  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        setLoading(true);

        // 1) Fetch columns (to build sensible defaults)
        const cRes = await fetch(`${apiBase}/${reportId}/columns`);
        const cJson = await cRes.json();
        if (!cRes.ok) throw new Error(cJson.error || 'Failed to load columns');
        if (cancelled) return;
        setColumns(Array.isArray(cJson) ? cJson : []);

        // 2) Fetch data
        const dRes = await fetch(`${apiBase}/${reportId}/data`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ limit: 20000 }),
        });
        const dJson = await dRes.json();
        if (!dRes.ok) throw new Error(dJson.error || 'Failed to load data');
        if (cancelled) return;
        setRows(Array.isArray(dJson?.data) ? dJson.data : []);
        setErr(null);
      } catch (e) {
        if (!cancelled) setErr(e.message);
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [reportId, apiBase]);

  // Detect numeric fields to choose a default aggregator/val
  const numericKeys = useMemo(() => {
    if (!rows.length) return [];
    const keys = Object.keys(rows[0] || {});
    const isNum = (v) => v !== null && v !== '' && !isNaN(parseFloat(v)) && isFinite(v);
    return keys.filter((k) => rows.some((r) => isNum(r[k])));
  }, [rows]);

  // Initialize defaults once when data/columns are ready
  useEffect(() => {
    if (!rows.length || !columns.length) return;
    if (ui.__initialized) return;

    const keys = columns.map((c) => c.key);
    const initial = pickSafe({
      rows: keys[0] ? [keys[0]] : [],
      cols: keys[1] ? [keys[1]] : [],
      vals: numericKeys[0] ? [numericKeys[0]] : [],
      aggregatorName: numericKeys[0] ? 'Sum' : 'Count',
      rendererName: 'Table',
      unusedOrientationCutoff: 85,
    });

    setUi((prev) => ({ ...initial, ...prev, __initialized: true }));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [rows, columns, numericKeys, ui.__initialized]);

  // Persist only safe keys
  useEffect(() => {
    try {
      const toSave = pickSafe(ui);
      localStorage.setItem(storageKey, JSON.stringify(toSave));
    } catch {}
  }, [ui, storageKey]);

  // Helpers
  const resetLayout = () => {
    setUi({});
    try {
      localStorage.removeItem(storageKey);
    } catch {}
  };

  const exportRawCsv = () => {
    if (!rows.length) return;
    const keys = Object.keys(rows[0]);
    const esc = (s) => `"${String(s ?? '').replace(/"/g, '""')}"`;
    const csv = [
      keys.map(esc).join(','),
      ...rows.map((r) => keys.map((k) => esc(r[k])).join(',')),
    ].join('\n');

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `report-${reportId}-raw.csv`;
    a.click();
    URL.revokeObjectURL(url);
  };

  if (loading) return <div className="text-muted">Loading pivot…</div>;
  if (err) return <div className="text-danger">Error: {err}</div>;
  if (!rows.length) return <div>No data.</div>;

  // Guard against saved rendererName that's not present (after upgrades)
  const safeUi = pickSafe(ui);
  const safeRendererName =
    safeUi.rendererName && mergedRenderers[safeUi.rendererName]
      ? safeUi.rendererName
      : 'Table';

  return (
    <div
      className={`pivot-shell ${isFullscreen ? 'pivot-fullscreen' : ''}`}
      style={!isFullscreen ? { minHeight: height, background: '#fff' } : undefined}
    >
      <div className="pivot-toolbar">
        <small className="text-muted">
          Rows: {rows.length.toLocaleString()} • Fields: {columns.length}
        </small>
        <div className="spacer" />
        <button
          className="btn btn-sm btn-outline-secondary"
          onClick={resetLayout}
          title="Reset layout"
        >
          Reset layout
        </button>
        <button
          className="btn btn-sm btn-outline-primary"
          onClick={exportRawCsv}
          title="Export raw data as CSV"
        >
          Export raw CSV
        </button>
        <button
          className="btn btn-sm btn-outline-dark"
          onClick={() => setIsFullscreen((v) => !v)}
          title={isFullscreen ? 'Exit fullscreen' : 'Enter fullscreen'}
        >
          {isFullscreen ? 'Exit Fullscreen' : 'Fullscreen'}
        </button>
      </div>

      <div className="pivot-body">
        <div className="pivot-ui-wrap">
          <ErrorBoundary fallback={PivotFallback}>
            <PivotTableUI
              renderers={mergedRenderers}
              data={rows}
              onChange={(next) => setUi(pickSafe(next))}
              {...safeUi}
              rendererName={safeRendererName} // ensure it's a valid component key
            />
          </ErrorBoundary>
        </div>
      </div>
    </div>
  );
}
