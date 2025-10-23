// assets/react/components/PivotReport.jsx
import React, { useEffect, useMemo, useRef, useState } from 'react';
import PivotTableUI from 'react-pivottable/PivotTableUI';
import PivotTable from 'react-pivottable/PivotTable';
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
  // Normalize shapes (avoid prop-type warnings)
  if (out.valueFilter && (typeof out.valueFilter !== 'object' || Array.isArray(out.valueFilter))) out.valueFilter = {};
  if (out.sorters && (typeof out.sorters !== 'object' || Array.isArray(out.sorters))) out.sorters = {};
  if (out.derivedAttributes && (typeof out.derivedAttributes !== 'object' || Array.isArray(out.derivedAttributes))) out.derivedAttributes = {};
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

// Inject minimal CSS once
function usePivotStyles() {
  useEffect(() => {
    const id = 'pivot-styles';
    if (document.getElementById(id)) return;
    const style = document.createElement('style');
    style.id = id;
    style.textContent = `
      .pivot-shell{display:flex;flex-direction:column;gap:.5rem;}
      /* Full-bleed with ~2mm side margins (~8px) */
      .pivot-bleed{--edge:8px;width:calc(100vw - var(--edge)*2);margin-left:calc(50% - 50vw + var(--edge));margin-right:calc(50% - 50vw + var(--edge));}

      .pivot-toolbar{display:flex;align-items:center;gap:.75rem;flex-wrap:wrap}
      .pivot-toolbar .spacer{flex:1;}

      /* We set this height dynamically via CSS var so the page itself doesn't scroll */
      .pivot-body{height:var(--pv-body-h,70vh);min-height:240px;overflow:hidden;}
      /* The inner wrapper scrolls instead of the page */
      .pivot-ui-wrap{height:100%;width:100%;overflow:auto;padding:0;border-radius:.5rem;border:1px solid #e5e7eb;background:#fff;}

      .pvtUi{max-width:none;}
      .pvtUi > div{min-width:0;}
      .pvtAxisContainer,.pvtRendererArea{overflow:auto;}
      .pvtRendererArea{padding:0;}
      .pvtTable{width:100%;}

      .preset-bar{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap}
      .preset-bar input[type="text"]{max-width:220px}
      .text-quiet{opacity:.7}

      /* Ensure selectors are hidden in view-only mode even if UI renders */
      .pivot-view-only .pvtUi .pvtVals,
      .pivot-view-only .pvtUi .pvtRenderer,
      .pivot-view-only .pvtUi .pvtUnused,
      .pivot-view-only .pvtUi .pvtAxisContainer { display: none !important; }
      .pivot-view-only .pvtUi .pvtRendererArea { display: block !important; }
    `;
    document.head.appendChild(style);
  }, []);
}

export default function PivotReport({
  reportId,
  apiBase = '/api/pivot',
  height = 650, // still used as a minimum, but container will auto-fit viewport
}) {
  usePivotStyles();

  const rootRef = useRef(null);
  const [rows, setRows] = useState([]);
  const [columns, setColumns] = useState([]);
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState(null);
  const [isEditing, setIsEditing] = useState(false); // view vs edit
  const [bodyH, setBodyH] = useState(height);

  // Compute available height so navbar stays put and only the pivot scrolls
  useEffect(() => {
    const calc = () => {
      if (!rootRef.current) return;
      const top = rootRef.current.getBoundingClientRect().top; // distance from top of viewport
      const vh = window.innerHeight || document.documentElement.clientHeight;
      const bottomPadding = 12; // small breathing room at page bottom
      const avail = Math.max(240, vh - top - bottomPadding);
      setBodyH(avail);
      // write to CSS var on the element so CSS can use it
      rootRef.current.style.setProperty('--pv-body-h', `${avail}px`);
    };
    calc();
    window.addEventListener('resize', calc);
    window.addEventListener('scroll', calc, { passive: true });
    return () => {
      window.removeEventListener('resize', calc);
      window.removeEventListener('scroll', calc);
    };
  }, []);

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

  // Presets state (report_presets endpoints)
  const [presets, setPresets] = useState([]);
  const [presetId, setPresetId] = useState(null);
  const [presetName, setPresetName] = useState('');
  const [presetMsg, setPresetMsg] = useState('');
  const [presetErr, setPresetErr] = useState('');
  const [busyPreset, setBusyPreset] = useState(false);
  const [isDirty, setIsDirty] = useState(false);
  const dirtyGuardRef = useRef(false); // prevent "modified" right after applyPreset

  // Build renderer maps once
  const PlotlyRenderers = useMemo(() => PlotlyRenderersFactory(Plotly), []);
  const mergedRenderers = useMemo(
    () => ({ ...TableRenderers, ...PlotlyRenderers }),
    [PlotlyRenderers]
  );

  // Load columns, data, presets
  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        setLoading(true);

        const cRes = await fetch(`${apiBase}/${reportId}/columns`);
        const cJson = await cRes.json();
        if (!cRes.ok) throw new Error(cJson.error || 'Failed to load columns');
        if (cancelled) return;
        setColumns(Array.isArray(cJson) ? cJson : []);

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

        try {
          const pRes = await fetch(`${apiBase}/${reportId}/presets`);
          if (pRes.ok) {
            const list = await pRes.json();
            setPresets(Array.isArray(list) ? list : []);
          } else {
            setPresetMsg('Presets API unavailable (using local layout only).');
          }
        } catch {
          setPresetMsg('Presets API unavailable (using local layout only).');
        }
      } catch (e) {
        if (!cancelled) setErr(e.message);
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();
    return () => { cancelled = true; };
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

  // Persist UI locally
  useEffect(() => {
    try { localStorage.setItem(storageKey, JSON.stringify(pickSafe(ui))); } catch {}
  }, [ui, storageKey]);

  // Dirty tracking (skip once when we just applied a preset)
  useEffect(() => {
    if (dirtyGuardRef.current) {
      dirtyGuardRef.current = false;
      setIsDirty(false);
    } else {
      setIsDirty(true);
    }
  }, [ui]);

  // Helpers
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

  async function refreshPresets() {
    try {
      setPresetErr('');
      const res = await fetch(`${apiBase}/${reportId}/presets`);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const list = await res.json();
      setPresets(Array.isArray(list) ? list : []);
    } catch {
      setPresetErr('Could not load presets.');
    }
  }

  function applyPreset(p) {
    if (!p) return;
    const next = pickSafe(p.data || {});
    dirtyGuardRef.current = true; // prevent "modified" flash
    setUi((prev) => ({ ...next, __initialized: true }));
    setPresetId(p.id ?? null);
    setPresetName(p.name ?? '');
    setPresetMsg(`Loaded preset “${p.name}”.`);
  }

  async function saveNewPreset() {
    setPresetErr(''); setPresetMsg('');
    if (!presetName.trim()) { setPresetErr('Please enter a preset name.'); return; }
    setBusyPreset(true);
    try {
      const body = JSON.stringify({ name: presetName.trim(), data: pickSafe(ui) });
      const res = await fetch(`${apiBase}/${reportId}/presets`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body,
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const created = await res.json();
      await refreshPresets();
      setPresetId(created.id || null);
      setPresetName(created.name || presetName.trim());
      setIsDirty(false);
      setPresetMsg('Preset saved.');
    } catch {
      setPresetErr('Save failed.');
    } finally { setBusyPreset(false); }
  }

  async function updatePreset() {
    if (!presetId) return saveNewPreset();
    setBusyPreset(true); setPresetErr(''); setPresetMsg('');
    try {
      const body = JSON.stringify({ name: presetName.trim() || undefined, data: pickSafe(ui) });
      const res = await fetch(`${apiBase}/${reportId}/presets/${encodeURIComponent(presetId)}`, {
        method: 'PUT', headers: { 'Content-Type': 'application/json' }, body,
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      await refreshPresets();
      setIsDirty(false);
      setPresetMsg('Preset updated.');
    } catch {
      setPresetErr('Update failed.');
    } finally { setBusyPreset(false); }
  }

  async function deletePreset() {
    if (!presetId) return;
    if (!confirm('Delete this preset permanently?')) return;
    setBusyPreset(true); setPresetErr(''); setPresetMsg('');
    try {
      const res = await fetch(`${apiBase}/${reportId}/presets/${encodeURIComponent(presetId)}`, {
        method: 'DELETE',
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      await refreshPresets();
      setPresetId(null);
      setPresetName('');
      setPresetMsg('Preset deleted.');
    } catch {
      setPresetErr('Delete failed.');
    } finally { setBusyPreset(false); }
  }

  // Render guards
  if (loading) return <div className="text-muted">Loading pivot…</div>;
  if (err) return <div className="text-danger">Error: {err}</div>;
  if (!rows.length) return <div>No data.</div>;

  // Safe rendererName
  const safeUi = pickSafe(ui);
  const safeRendererName =
    safeUi.rendererName && mergedRenderers[safeUi.rendererName]
      ? safeUi.rendererName
      : 'Table';

  return (
    <div
      ref={rootRef}
      className={`pivot-shell pivot-bleed ${!isEditing ? 'pivot-view-only' : ''}`}
      style={{ background: '#fff' }}
    >
      <div className="pivot-toolbar">
        <small className="text-muted">
          Rows: {rows.length.toLocaleString()} • Fields: {columns.length}
        </small>

        <div className="spacer" />

        {/* Preset controls */}
        <div className="preset-bar">
          <span className="text-quiet">Preset:</span>
          <select
            className="form-select form-select-sm"
            style={{ minWidth: 220 }}
            value={presetId ?? ''}
            onChange={(e) => {
              const id = e.target.value || null;
              setPresetId(id);
              const p = presets.find(pr => String(pr.id) === String(id));
              if (p) applyPreset(p);
            }}
          >
            <option value="">(unsaved layout)</option>
            {presets.map((p) => (
              <option key={p.id} value={p.id}>
                {p.name}{p.updated_at ? ` — ${new Date(p.updated_at).toLocaleString()}` : ''}
              </option>
            ))}
          </select>

          {/* Show preset editing widgets ONLY in Edit mode */}
          {isEditing && (
            <>
              <input
                type="text"
                className="form-control form-control-sm"
                placeholder="Preset name"
                value={presetName}
                onChange={(e) => setPresetName(e.target.value)}
              />
              <button className="btn btn-sm btn-outline-success" disabled={busyPreset} onClick={saveNewPreset}>
                Save as
              </button>
              <button
                className="btn btn-sm btn-outline-primary"
                disabled={busyPreset || (!presetId && !presetName.trim())}
                onClick={updatePreset}
              >
                {presetId ? 'Update' : 'Save'}
              </button>
              <button className="btn btn-sm btn-outline-danger" disabled={busyPreset || !presetId} onClick={deletePreset}>
                Delete
              </button>
              {isDirty && <span className="badge text-bg-warning">modified</span>}
              {presetErr && <span className="text-danger ms-2 small">{presetErr}</span>}
              {!presetErr && presetMsg && <span className="text-success ms-2 small">{presetMsg}</span>}
            </>
          )}
        </div>

        <div className="spacer" />

        {/* Right-side controls */}
        <button
          className="btn btn-sm btn-outline-secondary"
          onClick={() => setIsEditing((v) => !v)}
          title={isEditing ? 'Done' : 'Edit layout'}
        >
          {isEditing ? 'Done' : 'Edit'}
        </button>
        <button className="btn btn-sm btn-outline-primary" onClick={exportRawCsv}>
          Export raw CSV
        </button>
      </div>

      <div className="pivot-body">
        <div className="pivot-ui-wrap">
          <ErrorBoundary fallback={PivotFallback}>
            {isEditing ? (
              <PivotTableUI
                renderers={mergedRenderers}
                data={rows}
                onChange={(next) => setUi(pickSafe(next))}
                {...safeUi}
                rendererName={safeRendererName}
              />
            ) : (
              <PivotTable
                data={rows}
                rows={safeUi.rows || []}
                cols={safeUi.cols || []}
                vals={safeUi.vals || []}
                aggregatorName={safeUi.aggregatorName || (numericKeys[0] ? 'Sum' : 'Count')}
                rendererName={safeRendererName}
                renderers={mergedRenderers}
                valueFilter={safeUi.valueFilter || {}}
                sorters={safeUi.sorters || {}}
                derivedAttributes={safeUi.derivedAttributes || {}}
              />
            )}
          </ErrorBoundary>
        </div>
      </div>
    </div>
  );
}
