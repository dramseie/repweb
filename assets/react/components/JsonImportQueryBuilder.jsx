import React, { useEffect, useMemo, useState, useCallback, useRef } from 'react';

/** Build a MySQL/MariaDB JSON path like $.a.b[2]['weird key'] */
function buildJsonPath(segments) {
  if (!segments || segments.length === 0) return '$';
  return (
    '$' +
    segments
      .map((seg) => {
        if (typeof seg === 'number') return `[${seg}]`;
        const s = String(seg);
        return /^[A-Za-z0-9_]+$/.test(s) ? `.${s}` : `['${s.replace(/'/g, "\\'")}']`;
      })
      .join('')
  );
}

/** Convert a JS value to a lightweight tree */
function toTree(value, path = []) {
  if (Array.isArray(value)) {
    return { type: 'array', path, value, children: value.map((v, i) => toTree(v, [...path, i])) };
  }
  if (value !== null && typeof value === 'object') {
    return {
      type: 'object',
      path,
      value,
      children: Object.keys(value).sort().map((k) => toTree(value[k], [...path, k])),
    };
  }
  return { type: 'primitive', path, value, children: [] };
}

/** Nicely format a node label */
function nodeLabel(node) {
  const seg = node.path.length ? node.path[node.path.length - 1] : '(root)';
  if (node.type === 'object') return `${String(seg)} {…}`;
  if (node.type === 'array') return `${String(seg)} [${node.children.length}]`;
  const v = typeof node.value === 'string' ? `"${node.value}"` : String(node.value);
  return `${String(seg)}: ${v}`;
}

function TreeNode({ node, depth = 0, onDoublePath }) {
  const [open, setOpen] = useState(depth < 2);
  const isLeaf = node.children.length === 0;

  const handleDbl = (e) => {
    e.stopPropagation();
    onDoublePath(buildJsonPath(node.path), node);
  };

  return (
    <div style={{ paddingLeft: depth * 10 }}>
      <div
        role="treeitem"
        aria-expanded={!isLeaf && open}
        onClick={() => !isLeaf && setOpen(!open)}
        onDoubleClick={handleDbl}
        className="d-flex align-items-center py-0"
        style={{ cursor: 'pointer', userSelect: 'none', lineHeight: 1.15, fontSize: '0.9rem' }}
        title="Double-click to insert JSON path"
      >
        {!isLeaf && <span className="me-1" aria-hidden="true">{open ? '▾' : '▸'}</span>}
        <span className="text-truncate d-inline-block">{nodeLabel(node)}</span>
      </div>
      {open && node.children.map((ch, i) => (
        <TreeNode key={i} node={ch} depth={depth + 1} onDoublePath={onDoublePath} />
      ))}
    </div>
  );
}

function PathHelper({ show, buildExpr, onCopy }) {
  const [raw, setRaw] = useState('$.');
  if (!show) return null;
  const expr = (() => {
    try { if (!raw.startsWith('$')) throw new Error('Path must start with $'); return buildExpr(raw); }
    catch (e) { return String(e.message); }
  })();
  return (
    <div className="mt-2">
      <label className="form-label">Quick helper</label>
      <div className="input-group input-group-sm">
        <span className="input-group-text">JSON path</span>
        <input className="form-control" value={raw} onChange={(e) => setRaw(e.target.value)} placeholder="$.a[0].b" spellCheck={false}/>
        <span className="input-group-text">Expr</span>
        <input className="form-control" readOnly value={expr}/>
        <button type="button" className="btn btn-outline-secondary" onClick={() => onCopy(expr)} title="Copy expression">Copy</button>
      </div>
    </div>
  );
}

/** util: infer columns from rows */
function inferColumns(rows) {
  const set = new Set();
  rows.forEach(r => Object.keys(r || {}).forEach(k => set.add(k)));
  return Array.from(set);
}

/**
 * JsonImportQueryBuilder
 */
export default function JsonImportQueryBuilder({
  apiBase = '/api/json-imports',
  tableAlias = 'j',
  jsonColumn = 'ji_json',
  sqlPreviewApi = '/api/sql/preview',
}) {
  const [options, setOptions] = useState([]);
  const [sel, setSel] = useState(''); // "source:key"
  const [json, setJson] = useState(null);
  const [loading, setLoading] = useState(false);
  const [err, setErr] = useState(null);

  const [sql, setSql] = useState([
    'SELECT',
    '  ji_source,',
    '  ji_key',
    '  /* double-click paths in the tree to inject columns below */',
    `FROM nifi.json_import AS ${tableAlias}`,
    'WHERE j.ji_source = :source',   // <- alias used
    'ORDER BY ji_key',
    'LIMIT 100;',
  ].join('\n'));

  // preview state
  const [previewRows, setPreviewRows] = useState([]);
  const [previewCols, setPreviewCols] = useState([]); // from API or inferred
  const [previewLoading, setPreviewLoading] = useState(false);
  const [previewErr, setPreviewErr] = useState(null);
  const [limit, setLimit] = useState(100);

  // DT refs
  const tableRef = useRef(null);
  const dtRef = useRef(null);

  // Load dropdown options
  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        const res = await fetch(`${apiBase}/options`);
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        if (!cancelled) setOptions(data.items || []);
      } catch (e) {
        if (!cancelled) setErr(e.message);
      }
    })();
    return () => { cancelled = true; };
  }, [apiBase]);

  // Load JSON for a selected source/key
  const loadOne = useCallback(async (source, key) => {
    setLoading(true); setErr(null); setJson(null);
    try {
      const res = await fetch(`${apiBase}/${encodeURIComponent(source)}/${encodeURIComponent(key)}`);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      setJson(data.json ?? null);
    } catch (e) {
      setErr(e.message);
    } finally {
      setLoading(false);
    }
  }, [apiBase]);

  const onSelect = (e) => {
    const value = e.target.value;
    setSel(value);
    if (value) {
      const idx = value.indexOf(':');
      loadOne(value.slice(0, idx), value.slice(idx + 1));
    }
  };

  const selectedSource = useMemo(() => (sel ? sel.slice(0, sel.indexOf(':')) : ''), [sel]);
  const tree = useMemo(() => (json ? toTree(json, []) : null), [json]);

  // Insert JSON path column
  const handleInsertPath = (path) => {
    const safeAlias = path.replace(/[^A-Za-z0-9_]/g, '_').replace(/^_+/, '');
    const snippet = `JSON_UNQUOTE(JSON_EXTRACT(${tableAlias}.${jsonColumn}, '${path}')) AS ${safeAlias}`;
    if (/\nFROM\s/i.test(sql)) setSql((prev) => prev.replace(/\nFROM\s/i, `,\n  ${snippet}\nFROM `));
    else setSql((prev) => prev + `\n  , ${snippet}\n`);
  };

  const buildExpr = useCallback(
    (path) => `JSON_UNQUOTE(JSON_EXTRACT(${tableAlias}.${jsonColumn}, '${path}'))`,
    [tableAlias, jsonColumn]
  );

  const copyToClipboard = async (text) => {
    try { await navigator.clipboard.writeText(text); } catch { /* ignore */ }
  };

  // Preview SQL
  const runPreview = useCallback(async () => {
    if (!sqlPreviewApi) return;
    setPreviewLoading(true); setPreviewErr(null);
    try {
      const body = { sql, params: { source: selectedSource || null }, limit: Number(limit) || 100 };
      const res = await fetch(sqlPreviewApi, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      });

      const payload = await res.json().catch(() => ({}));
      if (!res.ok) {
        const msg = (payload && (payload.error || payload.message)) || `HTTP ${res.status}`;
        throw new Error(msg);
      }

      const rows = Array.isArray(payload) ? payload : (payload.rows || []);
      const cols = (payload.columns && Array.isArray(payload.columns) && payload.columns.length)
        ? payload.columns
        : inferColumns(rows);

      setPreviewCols(cols);
      setPreviewRows(rows);
    } catch (e) {
      setPreviewErr(String(e.message));
      setPreviewCols([]);
      setPreviewRows([]);
    } finally {
      setPreviewLoading(false);
    }
  }, [sqlPreviewApi, sql, selectedSource, limit]);

  // Init/Update DataTables (DT owns tbody)
  useEffect(() => {
    if (!tableRef.current || !window.$ || !window.$.fn.DataTable) return;

    const $ = window.$;

    // If schema changed or DT not created, (re)create
    const recreate = () => {
      if (dtRef.current) {
        dtRef.current.destroy();
        dtRef.current = null;
      }
      tableRef.current.innerHTML = ''; // fresh thead/tbody

      const columns = (previewCols || []).map(c => ({ title: c, data: c }));
      dtRef.current = $(tableRef.current).DataTable({
        data: previewRows || [],
        columns,
        paging: true,
        searching: true,
        info: true,
        lengthChange: true,
        order: [],
        autoWidth: false,
        destroy: true,
      });
    };

    if (!dtRef.current) {
      // first time
      if (previewCols.length) recreate();
      return;
    }

    // Compare headers to decide if schema changed
    const currentCols = dtRef.current.columns().header().toArray().map(th => th.textContent);
    const schemaChanged = currentCols.length !== previewCols.length ||
      currentCols.some((t, i) => t !== previewCols[i]);

    if (schemaChanged) {
      recreate();
    } else {
      // same schema, just replace data
      dtRef.current.clear();
      dtRef.current.rows.add(previewRows || []);
      dtRef.current.draw();
    }
  }, [previewRows, previewCols]);

  return (
    <div className="container-fluid px-0 w-100">
      {/* Header: dropdown matches left pane width (6/6) */}
      <div className="row g-3 align-items-end">
        <div className="col-md-6">
          <label className="form-label">Source / Key</label>
          <select className="form-select" value={sel} onChange={onSelect}>
            <option value="">— Choose a JSON import —</option>
            {options.map((o) => (
              <option key={`${o.source}:${o.key}`} value={`${o.source}:${o.key}`}>
                {o.source} — {o.key}{o.ts ? ` (${o.ts})` : ''}
              </option>
            ))}
          </select>
        </div>
        <div className="col-md-6 text-end">
          <div className="d-flex gap-2 justify-content-end align-items-center">
            <div className="input-group input-group-sm" style={{ maxWidth: 520 }}>
              <span className="input-group-text">Alias</span>
              <input className="form-control" readOnly value={tableAlias}/>
              <span className="input-group-text">JSON column</span>
              <input className="form-control" readOnly value={jsonColumn}/>
            </div>
          </div>
        </div>
      </div>

      {/* Builder */}
      <div className="row mt-3" style={{ minHeight: 420 }}>
        {/* LEFT: JSON tree */}
        <div
          className="col-md-6 border rounded p-2 overflow-auto"
          style={{
            maxHeight: 'calc(100vh - 260px)',
            overflowY: 'auto',
            overflowX: 'auto',
            whiteSpace: 'nowrap'
          }}
          role="region"
          aria-label="JSON tree"
        >
          {loading && <div className="text-muted">Loading…</div>}
          {err && <div className="text-danger">{String(err)}</div>}
          {!loading && !err && !tree && <div className="text-muted">Select a (source, key) to view JSON…</div>}
          {tree && (
            <div role="tree" aria-label="JSON tree" className="json-tree" style={{ fontSize: '0.9rem', lineHeight: 1.15 }}>
              <TreeNode node={{ ...tree, path: [] }} depth={0} onDoublePath={handleInsertPath} />
            </div>
          )}
        </div>

        {/* RIGHT: SQL editor */}
        <div className="col-md-6">
          <div className="d-flex gap-2 mb-2">
            <button
              className="btn btn-sm btn-outline-secondary"
              type="button"
              title="Reset SQL"
              onClick={() =>
                setSql([
                  'SELECT',
                  '  ji_source,',
                  '  ji_key',
                  '  /* double-click paths in the tree to inject columns below */',
                  `FROM nifi.json_import AS ${tableAlias}`,
                  'WHERE j.ji_source = :source',
                  'ORDER BY ji_key',
                  'LIMIT 100;',
                ].join('\n'))
              }
            >Reset</button>

            <button
              className="btn btn-sm btn-outline-secondary"
              type="button"
              title="Copy SQL to clipboard"
              onClick={() => copyToClipboard(sql)}
            >Copy SQL</button>

            <div className="ms-auto input-group input-group-sm" style={{ maxWidth: 220 }}>
              <span className="input-group-text">Limit</span>
              <input type="number" className="form-control" min="1" step="1"
                     value={limit} onChange={(e) => setLimit(e.target.value)} />
              <button className="btn btn-primary" type="button" onClick={runPreview} disabled={previewLoading || !selectedSource}>
                {previewLoading ? 'Running…' : 'Preview SQL'}
              </button>
            </div>
          </div>

          <textarea
            className="form-control font-monospace"
            rows={18}
            spellCheck={false}
            value={sql}
            onChange={(e) => setSql(e.target.value)}
          />

          <PathHelper show={!!json} buildExpr={buildExpr} onCopy={(expr) => copyToClipboard(expr)} />
        </div>
      </div>

      {/* Preview (full width) */}
      <div className="row mt-3">
        <div className="col-12">
          {previewErr && <div className="alert alert-danger py-2">{previewErr}</div>}
          <div className="table-responsive">
            {/* DT will populate headers/body */}
            <table ref={tableRef} className="table table-sm table-striped w-100"></table>
          </div>
        </div>
      </div>
    </div>
  );
}
