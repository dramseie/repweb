// File: assets/react/components/EavHandsontable.jsx
import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { HotTable } from '@handsontable/react';
import 'handsontable/dist/handsontable.full.min.css';
import {
  TEXT_TYPE,
  NUMERIC_TYPE,
  DATE_TYPE,
  CHECKBOX_TYPE,
  registerAllCellTypes,
} from 'handsontable/cellTypes';
registerAllCellTypes();

function mapType(dt) {
  const t = (dt || 'text').toLowerCase();
  if (t.includes('int') || t.includes('num') || ['float', 'double', 'decimal'].includes(t)) return NUMERIC_TYPE;
  if (t.includes('date') || t.includes('time')) return DATE_TYPE;
  if (t.includes('bool')) return CHECKBOX_TYPE;
  return TEXT_TYPE;
}

export default function EavHandsontable({ tenant: initialTenant, entityType: initialType }) {
  const [tenant, setTenant] = useState(initialTenant || '');
  const [entityType, setEntityType] = useState(initialType || '');
  const [tenants, setTenants] = useState([]);
  const [types, setTypes] = useState([]);
  const [columns, setColumns] = useState([]);
  const [rows, setRows] = useState([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(false);
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(0);
  const pageSize = 200;

  const pendingRef = useRef([]);
  const saveTimer = useRef();
  const hotRef = useRef(null);

  // --- measured container size so HOT gets numeric width/height
  const wrapRef = useRef(null);
  const [size, setSize] = useState({ w: 0, h: 0 });

  // One-time helper CSS
  useEffect(() => {
    if (!document.getElementById('eav-style')) {
      const s = document.createElement('style');
      s.id = 'eav-style';
      s.textContent = `
        .ht-edited { background-color:#fff3cd !important; }
        .eav-editor-wrap { width:100%; }
        /* Force the internal holders to follow container width */
        .eav-editor-wrap .handsontable .wtHolder,
        .eav-editor-wrap .handsontable .wtHider { width:100% !important; }
      `;
      document.head.appendChild(s);
    }
  }, []);

  // Keep the grid sized to the available viewport width/height
  useEffect(() => {
    const el = wrapRef.current;
    if (!el) return;

    const measure = () => {
      const rect = el.getBoundingClientRect();
      const toolbarAllowance = 140; // space for filters & pager
      const h = Math.max(240, Math.floor(window.innerHeight - rect.top - toolbarAllowance));
      setSize({ w: Math.floor(rect.width), h });
      hotRef.current?.hotInstance?.render();
    };

    const ro = new ResizeObserver(measure);
    ro.observe(el);
    measure();
    window.addEventListener('resize', measure);
    window.addEventListener('orientationchange', measure);
    return () => {
      ro.disconnect();
      window.removeEventListener('resize', measure);
      window.removeEventListener('orientationchange', measure);
    };
  }, []);

  // Column meta
  const colDefs = useMemo(() => {
    const base = [
      { data: 'ci',     type: TEXT_TYPE },
      { data: 'name',   type: TEXT_TYPE },
      { data: 'status', type: TEXT_TYPE },
    ];
    const others = (Array.isArray(columns) ? columns : []).map((c) => ({
      data: c.attribute_code,
      type: mapType(c.data_type),
    }));
    return base.concat(others);
  }, [columns]);

  // Distribute available width across columns (fixed for first 3, flexible for the rest)
  const colWidths = useMemo(() => {
    const totalW = Math.max(320, size.w);
    const fixed = [220, 260, 120]; // ci, name, status
    const baseFixed = fixed.reduce((a, b) => a + b, 0);
    const flexCount = Math.max(0, colDefs.length - 3);
    if (flexCount === 0) return fixed;

    // account for borders/scrollbar a bit
    const pad = 24;
    const remaining = Math.max(0, totalW - baseFixed - pad);
    const flex = Math.max(120, Math.floor(remaining / flexCount));
    return [...fixed, ...Array(flexCount).fill(flex)];
  }, [size.w, colDefs.length]);

  // ---- meta fetches ----
  useEffect(() => {
    (async () => {
      try {
        const r = await fetch('/api/eav/meta/tenants');
        if (r.ok) setTenants(await r.json());
      } catch {}
    })();
  }, []);
  useEffect(() => {
    if (!tenant) return;
    (async () => {
      try {
        const r = await fetch(`/api/eav/meta/${tenant}/types`);
        if (r.ok) setTypes(await r.json());
      } catch {}
    })();
  }, [tenant]);

  // ---- data/schema ----
  const loadSchema = useCallback(async () => {
    if (!tenant || !entityType) return;
    const r = await fetch(`/api/eav/${tenant}/${entityType}/columns`);
    if (r.ok) setColumns(await r.json());
  }, [tenant, entityType]);

  const loadData = useCallback(async () => {
    if (!tenant || !entityType) return;
    setLoading(true);
    try {
      const params = new URLSearchParams({ limit: String(pageSize), offset: String(page * pageSize) });
      if (search) params.set('search', search);
      const r = await fetch(`/api/eav/${tenant}/${entityType}/rows?${params}`);
      if (r.ok) {
        const data = await r.json();
        setRows(Array.isArray(data.rows) ? data.rows : []);
        setTotal(Number(data.total || 0));
      }
    } finally {
      setLoading(false);
    }
  }, [tenant, entityType, page, search]);

  useEffect(() => { loadSchema(); }, [loadSchema]);
  useEffect(() => { loadData(); }, [loadData]);

  // ---- edits / save ----
  const markCellEdited = useCallback((rowIdx, prop) => {
    const hot = hotRef.current?.hotInstance;
    if (!hot) return;
    const col = typeof prop === 'number' ? prop : hot.propToCol(prop);
    const meta = hot.getCellMeta(rowIdx, col);
    if (!/\bht-edited\b/.test(meta.className || '')) {
      hot.setCellMeta(rowIdx, col, 'className', (meta.className || '') + ' ht-edited');
      hot.render();
    }
  }, []);

  const flush = useCallback(async () => {
    const changes = pendingRef.current;
    if (!changes.length) return;
    pendingRef.current = [];
    const res = await fetch(`/api/eav/${tenant}/${entityType}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ changes }),
    });
    const out = await res.json().catch(() => ({}));
    if (out?.errors?.length) {
      alert(`Saved ${out.applied ?? 0}, ${out.errors.length} failed`);
    }
  }, [tenant, entityType]);

  const scheduleFlush = useCallback(() => {
    clearTimeout(saveTimer.current);
    saveTimer.current = setTimeout(flush, 600);
  }, [flush]);

  const afterChange = useCallback((changes, source) => {
    if (!changes || ['loadData', 'populateFromArray'].includes(source)) return;
    for (const [r, prop, oldVal, newVal] of changes) {
      if (oldVal === newVal) continue;
      const row = rows[r] || {};
      pendingRef.current.push({ ci: row.ci || '', attribute: String(prop), value: newVal });
      markCellEdited(r, prop);
    }
    scheduleFlush();
  }, [rows, scheduleFlush, markCellEdited]);

  return (
    <div ref={wrapRef} className="eav-editor-wrap">
      <div className="d-flex gap-2 align-items-center mb-2">
        <label className="fw-bold me-1">Tenant:</label>
        <select
          className="form-select form-select-sm"
          style={{ maxWidth: 220 }}
          value={tenant}
          onChange={(e) => {
            setTenant(e.target.value);
            setEntityType('');
            setPage(0);
          }}
        >
          <option value="" disabled>Choose tenant…</option>
          {tenants.map((t) => (
            <option key={t} value={t}>{t}</option>
          ))}
        </select>

        <label className="fw-bold ms-2 me-1">Type:</label>
        <select
          className="form-select form-select-sm"
          style={{ maxWidth: 240 }}
          value={entityType}
          onChange={(e) => {
            setEntityType(e.target.value);
            setPage(0);
          }}
          disabled={!tenant}
        >
          <option value="" disabled>{tenant ? 'Choose type…' : 'Select tenant first…'}</option>
          {types.map((tp) => (
            <option key={tp} value={tp}>{tp}</option>
          ))}
        </select>

        <input
          className="form-control ms-2"
          style={{ maxWidth: 340 }}
          placeholder="Search CI/Name/Status..."
          value={search}
          onChange={(e) => {
            setSearch(e.target.value);
            setPage(0);
          }}
        />
        <button
          className="btn btn-sm btn-outline-secondary"
          disabled={loading || !tenant || !entityType}
          onClick={loadData}
        >
          Reload
        </button>
        <span className="ms-auto">{loading ? 'Loading…' : `${rows.length} / ${total}`}</span>
      </div>

      <HotTable
        ref={hotRef}
        data={rows}
        colHeaders={['ci', 'name', 'status', ...columns.map((c) => c.attribute_code)]}
        columns={colDefs}
        colWidths={colWidths}           // <- force use of the full width
        rowHeaders
        dropdownMenu
        filters
        contextMenu
        manualColumnMove
        licenseKey="non-commercial-and-evaluation"
        stretchH="all"
        autoColumnSize={false}          // don't shrink to content
        width={Math.max(320, size.w)}   // numeric width
        height={Math.max(240, size.h)}  // numeric height
        afterChange={afterChange}
        className="w-100"
      />

      <div className="d-flex justify-content-between align-items-center mt-2">
        <button className="btn btn-sm btn-primary" onClick={flush}>Save now</button>
        <div className="btn-group">
          <button
            className="btn btn-sm btn-outline-secondary"
            disabled={page === 0}
            onClick={() => setPage((p) => Math.max(0, p - 1))}
          >
            Prev
          </button>
          <span className="btn btn-sm btn-light">Page {page + 1}</span>
          <button
            className="btn btn-sm btn-outline-secondary"
            disabled={(page + 1) * pageSize >= total}
            onClick={() => setPage((p) => p + 1)}
          >
            Next
          </button>
        </div>
      </div>
    </div>
  );
}
