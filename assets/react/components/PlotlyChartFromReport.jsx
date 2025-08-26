import React, { useEffect, useMemo, useState } from 'react';
import Plot from 'react-plotly.js';

/**
 * PlotlyChartFromReport
 * - GET  /api/plotly/{id}/columns  -> [{data,title,type?}]
 * - POST /api/plotly/{id}/data     -> { data: [...] } (accepts {from,to})
 */
export default function PlotlyChartFromReport({
  reportId,
  apiBase = '/api/plotly',
  height = 540,
  initialChart = 'line',
  initialAgg = 'none',
}) {
  usePlotlyFullscreenStyles();

  const [columns, setColumns] = useState([]);
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState(null);

  // UI state
  const [chartType, setChartType] = useState(initialChart);
  const [agg, setAgg] = useState(initialAgg);
  const [xCol, setXCol] = useState('');
  const [yCol, setYCol] = useState('');
  const [seriesCol, setSeriesCol] = useState(''); // split series
  const [timeBin, setTimeBin] = useState('auto'); // auto, day, week, month, quarter, year
  const [stack, setStack] = useState(false);

  // Fullscreen toggle
  const [isFullscreen, setIsFullscreen] = useState(false);
  useEffect(() => {
    if (!isFullscreen) return;
    const prev = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => { document.body.style.overflow = prev; };
  }, [isFullscreen]);

  // Date range (HTML datetime-local expects local “YYYY-MM-DDTHH:mm”)
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');

  // load columns + initial data
  useEffect(() => {
    let alive = true;
    (async () => {
      setLoading(true);
      try {
        const colUrl = new URL(`${apiBase}/${reportId}/columns`, window.location.origin);
        const cResp = await fetch(colUrl, { credentials: 'same-origin' });
        if (!cResp.ok) throw new Error(`Columns HTTP ${cResp.status}`);
        const cJson = await cResp.json();
        if (!alive) return;

        const cols = Array.isArray(cJson) ? cJson : (cJson.columns || []);
        const normalized = cols
          .map(c => {
            if (typeof c === 'string') return { data: c, title: c };
            const data = c.data ?? c.title ?? c.name ?? c.key;
            const title = c.title ?? data ?? '';
            return data ? { data, title, type: c.type || inferType(title) } : null;
          })
          .filter(Boolean);

        if (!normalized.length) throw new Error('No columns returned');
        setColumns(normalized);

        // Fetch all data initially
        const all = await fetchAll({ apiBase, reportId, from: '', to: '' });
        if (!alive) return;
        setRows(all);

        // choose sensible defaults
        const defaultX =
          normalized.find(c => c.type === 'date')?.data ||
          normalized.find(c => c.type !== 'number')?.data ||
          normalized[0]?.data || '';
        const defaultY =
          normalized.find(c => isNumericCol(c, all))?.data ||
          normalized.find(c => c.type === 'number')?.data ||
          normalized[1]?.data || '';
        const defaultSeries =
          normalized.find(c => /^(captor|source(_)?file)$/i.test(c.data))?.data ||
          normalized.find(c => c.type !== 'number' && c.data !== defaultX)?.data ||
          ''; // optional

        setXCol(defaultX);
        setYCol(defaultY);
        setSeriesCol(defaultSeries);

        // prefill date inputs if there is a date column
        if (defaultX) {
          const dates = all
            .map(r => new Date(r[defaultX]))
            .filter(d => !isNaN(d.getTime()))
            .sort((a, b) => a - b);
          if (dates.length) {
            setFrom(toLocalInput(dates[0]));
            setTo(toLocalInput(dates[dates.length - 1]));
          }
        }

        setErr(null);
      } catch (e) {
        setErr(e.message || String(e));
      } finally {
        if (alive) setLoading(false);
      }
    })();
    return () => { alive = false; };
  }, [apiBase, reportId]);

  async function applyRange() {
    setLoading(true);
    try {
      const ranged = await fetchAll({ apiBase, reportId, from, to });
      setRows(ranged);
      setErr(null);
    } catch (e) {
      setErr(e.message || String(e));
    } finally {
      setLoading(false);
    }
  }

  const { traces, title } = useMemo(
    () => buildSeries({ rows, xCol, yCol, seriesCol, agg, chartType, timeBin }),
    [rows, xCol, yCol, seriesCol, agg, chartType, timeBin]
  );

  // In fullscreen, we let the container dictate height (100%); outside, we use prop "height"
  const layout = useMemo(() => ({
    height: isFullscreen ? undefined : height,
    autosize: true,
    margin: { t: 52, r: 24, b: 56, l: 64 },
    xaxis: { title: xCol || 'X' },
    yaxis: { title: yCol || 'Y' },
    hovermode: 'x unified',
    barmode: stack ? 'stack' : 'group',
    legend: { orientation: 'h', y: -0.2 },
    title,
  }), [isFullscreen, height, xCol, yCol, stack, title]);

  return (
    <div className={`plotly-shell ${isFullscreen ? 'plotly-fullscreen' : ''}`}
         style={!isFullscreen ? { minHeight: height, background: '#fff' } : undefined}>
      {/* Toolbar */}
      <div className="plotly-toolbar">
        {err && <div className="alert alert-danger py-1 px-2 mb-0 me-2">{err}</div>}
        {loading && <div className="text-muted me-2">Loading…</div>}
        <div className="spacer" />
        <button
          className="btn btn-sm btn-outline-dark"
          onClick={() => setIsFullscreen(v => !v)}
          title={isFullscreen ? 'Exit fullscreen' : 'Enter fullscreen'}
        >
          {isFullscreen ? 'Exit Fullscreen' : 'Fullscreen'}
        </button>
      </div>

      {/* Controls */}
      <div className="plotly-controls">
        <div className="row g-2">
          <div className="col-sm-6 col-md-3">
            <LabeledSelect label="Chart"
              value={chartType} onChange={setChartType}
              options={[
                { value: 'line', label: 'Line' },
                { value: 'bar', label: 'Bar' },
                { value: 'scatter', label: 'Scatter' },
                { value: 'area', label: 'Area' },
                { value: 'pie', label: 'Pie' },
              ]}
            />
          </div>
          <div className="col-sm-6 col-md-3">
            <LabeledSelect label="Aggregation"
              value={agg} onChange={setAgg}
              options={[
                { value: 'none', label: 'None' },
                { value: 'sum', label: 'Sum' },
                { value: 'avg', label: 'Average' },
                { value: 'min', label: 'Min' },
                { value: 'max', label: 'Max' },
                { value: 'count', label: 'Count' },
              ]}
            />
          </div>
          <div className="col-sm-6 col-md-3">
            <LabeledSelect label="X"
              value={xCol} onChange={setXCol}
              options={columns.map(c => ({ value: c.data, label: c.title }))}
            />
          </div>
          <div className="col-sm-6 col-md-3">
            <LabeledSelect label="Y"
              value={yCol} onChange={setYCol}
              options={columns
                .filter(c => c.data !== xCol && isNumericCol(c, rows))
                .map(c => ({ value: c.data, label: c.title }))}
            />
          </div>
        </div>

        <div className="row g-2">
          <div className="col-sm-6 col-md-3">
            <LabeledSelect label="Series (split lines)"
              value={seriesCol} onChange={setSeriesCol}
              options={[
                { value: '', label: '(none)' },
                ...columns
                  .filter(c => c.data !== xCol && c.data !== yCol && c.type !== 'number')
                  .map(c => ({ value: c.data, label: c.title }))
              ]}
            />
          </div>

          {/* Date range */}
          <div className="col-sm-6 col-md-3">
            <LabeledInputDate label="From" value={from} onChange={setFrom} />
          </div>
          <div className="col-sm-6 col-md-3">
            <LabeledInputDate label="To" value={to} onChange={setTo} />
          </div>
          {isDateCol({ data: xCol }, rows) && (
            <div className="col-sm-6 col-md-3">
              <LabeledSelect label="Time bin"
                value={timeBin} onChange={setTimeBin}
                options={[
                  { value: 'auto', label: 'Auto' },
                  { value: 'day', label: 'Day' },
                  { value: 'week', label: 'Week' },
                  { value: 'month', label: 'Month' },
                  { value: 'quarter', label: 'Quarter' },
                  { value: 'year', label: 'Year' },
                ]}
              />
            </div>
          )}
          <div className="col-sm-6 col-md-3 d-flex align-items-end gap-2">
            <button className="btn btn-primary me-2" onClick={applyRange}>Apply</button>
            <button className="btn btn-outline-secondary" onClick={() => { setFrom(''); setTo(''); applyRange(); }}>Clear</button>
          </div>
        </div>

        {chartType !== 'pie' && (
          <div className="form-check mt-1">
            <input className="form-check-input" type="checkbox" id="stackBars" checked={stack} onChange={e => setStack(e.target.checked)} />
            <label className="form-check-label" htmlFor="stackBars">Stack bars</label>
          </div>
        )}
      </div>

      {/* Chart body fills the remaining height */}
      <div className="plotly-body">
        <div className="plotly-canvas-wrap">
          <Plot
            data={traces}
            layout={layout}
            useResizeHandler
            className="w-100 h-100"
            style={{ width: '100%', height: '100%' }}
            config={{ displaylogo: false, responsive: true, modeBarButtonsToRemove: ['select2d', 'lasso2d'] }}
          />
        </div>
      </div>
    </div>
  );
}

/* ---------------- helpers ---------------- */

function LabeledSelect({ label, value, onChange, options }) {
  return (
    <label className="form-label w-100">
      <div className="small text-muted mb-1">{label}</div>
      <select className="form-select" value={value} onChange={e => onChange(e.target.value)}>
        {options.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
      </select>
    </label>
  );
}

function LabeledInputDate({ label, value, onChange }) {
  return (
    <label className="form-label w-100">
      <div className="small text-muted mb-1">{label}</div>
      <input type="datetime-local" className="form-control" value={value} onChange={e => onChange(e.target.value)} />
    </label>
  );
}

async function fetchAll({ apiBase, reportId, from, to }) {
  const url = new URL(`${apiBase}/${reportId}/data`, window.location.origin);
  const payload = {};
  if (from) payload.from = from;
  if (to) payload.to = to;
  const resp = await fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });
  if (!resp.ok) throw new Error(`Data HTTP ${resp.status}`);
  const j = await resp.json();
  return Array.isArray(j) ? j : (j.data || []);
}

function inferType(name) {
  const s = String(name || '').toLowerCase();
  if (/(date|time|timestamp|_at)$/.test(s)) return 'date';
  return undefined;
}

function isNumericCol(col, rows) {
  if (!col?.data) return false;
  const N = Math.min(rows?.length || 0, 50);
  let seen = 0;
  for (let i = 0; i < N; i++) {
    const v = rows[i]?.[col.data];
    if (v == null || v === '') continue;
    seen++;
    if (!Number.isFinite(Number(v))) return false;
  }
  return seen > 0;
}

function isDateCol(col, rows) {
  if (!col?.data) return false;
  let hits = 0, seen = 0;
  for (let i = 0; i < Math.min(rows.length, 50); i++) {
    const v = rows[i]?.[col.data];
    if (v == null || v === '') continue;
    seen++;
    const d = new Date(v);
    if (!isNaN(d.getTime())) hits++;
  }
  return seen > 0 && hits / seen > 0.6;
}

/**
 * Build Plotly traces.
 * If `seriesCol` is set, create one trace per series value.
 */
function buildSeries({ rows, xCol, yCol, seriesCol, agg, chartType, timeBin }) {
  const titleParts = [];
  if (yCol) titleParts.push(yCol);
  if (agg && agg !== 'none') titleParts.push(`(${agg})`);
  if (xCol) titleParts.push('by', xCol);
  if (seriesCol) titleParts.push('split by', seriesCol);

  if (!xCol || !rows?.length) return { traces: [], title: titleParts.join(' ') };

  const xIsDate = rows.some(r => !isNaN(new Date(r[xCol]).getTime()));

  // PIE: aggregate by series only
  if (chartType === 'pie') {
    const seriesMap = new Map(); // key=series, arr of y
    for (const r of rows) {
      const sKey = seriesCol ? String(r[seriesCol]) : 'All';
      const y = Number(r[yCol]);
      if (!Number.isFinite(y)) continue;
      if (!seriesMap.has(sKey)) seriesMap.set(sKey, []);
      seriesMap.get(sKey).push(y);
    }
    const labels = [];
    const values = [];
    for (const [sKey, arr] of seriesMap.entries()) {
      labels.push(sKey);
      values.push(aggregate(arr, agg));
    }
    return {
      traces: [{ type: 'pie', labels, values, hovertemplate: '%{label}: %{value}<extra></extra>' }],
      title: titleParts.join(' ')
    };
  }

  // NON-PIE: build one trace per series
  const seriesValues = seriesCol
    ? Array.from(new Set(rows.map(r => String(r[seriesCol]))))
    : ['All'];

  const traces = [];

  for (const sVal of seriesValues) {
    const filtered = seriesCol ? rows.filter(r => String(r[seriesCol]) === sVal) : rows;

    // group by binned x
    const grouped = new Map(); // key -> arr of y
    for (const r of filtered) {
      const rawX = r[xCol];
      const key = xIsDate ? binDateKey(rawX, timeBin) : String(rawX);
      const y = Number(r[yCol]);
      if (!Number.isFinite(y)) continue;

      if (!grouped.has(key)) grouped.set(key, []);
      grouped.get(key).push(y);
    }

    const xs = [];
    const ys = [];
    [...grouped.entries()]
      .sort((a, b) => (xIsDate ? (new Date(a[0]) - new Date(b[0])) : String(a[0]).localeCompare(String(b[0]))))
      .forEach(([k, arr]) => {
        xs.push(xIsDate ? new Date(k) : k);
        ys.push(aggregate(arr, agg));
      });

    const base = { x: xs, y: ys, name: sVal, hovertemplate: '%{x}<br>%{y}<extra></extra>' };

    if (chartType === 'bar')         traces.push({ type: 'bar', ...base });
    else if (chartType === 'scatter') traces.push({ type: 'scatter', mode: 'markers', ...base });
    else if (chartType === 'area')    traces.push({ type: 'scatter', mode: 'lines', fill: 'tozeroy', ...base });
    else                               traces.push({ type: 'scatter', mode: 'lines', ...base }); // line
  }

  return { traces, title: titleParts.join(' ') };
}

function binDateKey(x, bin) {
  const d = new Date(x);
  if (isNaN(d.getTime())) return String(x);
  const y = d.getFullYear(), m = d.getMonth(), day = d.getDate();
  switch (bin) {
    case 'year':    return `${y}-01-01T00:00:00Z`;
    case 'quarter': return `${y}-${String(Math.floor(m/3)*3 + 1).padStart(2,'0')}-01T00:00:00Z`;
    case 'month':   return `${y}-${String(m+1).padStart(2,'0')}-01T00:00:00Z`;
    case 'week': {
      const dow = (d.getDay() + 6) % 7;  // Monday
      const mon = new Date(d); mon.setDate(d.getDate() - dow);
      return `${mon.getFullYear()}-${String(mon.getMonth()+1).padStart(2,'0')}-${String(mon.getDate()).padStart(2,'0')}T00:00:00Z`;
    }
    case 'day':     return `${y}-${String(m+1).padStart(2,'0')}-${String(day).padStart(2,'0')}T00:00:00Z`;
    case 'auto':
    default:        return `${y}-${String(m+1).padStart(2,'0')}-${String(day).padStart(2,'0')}T00:00:00Z`;
  }
}

function aggregate(arr, agg) {
  const clean = arr.filter(Number.isFinite);
  if (!clean.length) return null;
  switch (agg) {
    case 'sum':   return clean.reduce((a, b) => a + b, 0);
    case 'avg':   return clean.reduce((a, b) => a + b, 0) / clean.length;
    case 'min':   return Math.min(...clean);
    case 'max':   return Math.max(...clean);
    case 'count': return clean.length;
    case 'none':
    default:      return clean[0]; // first value per group
  }
}

function toLocalInput(d) {
  const pad = n => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

/* ---------- tiny style injector for fullscreen shell ---------- */
function usePlotlyFullscreenStyles() {
  useEffect(() => {
    const id = 'plotly-fullscreen-styles';
    if (document.getElementById(id)) return;
    const style = document.createElement('style');
    style.id = id;
    style.textContent = `
      .plotly-shell{display:flex;flex-direction:column;gap:.5rem;}
      .plotly-toolbar{display:flex;align-items:center;gap:.75rem;}
      .plotly-toolbar .spacer{flex:1;}
      .plotly-controls{display:block;}
      .plotly-body{flex:1;min-height:0;overflow:hidden;}
      .plotly-canvas-wrap{height:100%;width:100%;overflow:hidden;border:1px solid #e5e7eb;border-radius:.5rem;background:#fff;}
      .plotly-fullscreen{position:fixed;inset:0;z-index:1050;background:#fff;padding:1rem;}
      .plotly-fullscreen .plotly-canvas-wrap{border:1px solid #e5e7eb;}
    `;
    document.head.appendChild(style);
  }, []);
}
