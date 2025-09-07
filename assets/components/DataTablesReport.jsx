// src/assets/react/components/DataTablesReport.jsx
import React, { useEffect, useRef, useState } from 'react';
import $ from 'jquery';

// Core + Bootstrap 5
import 'datatables.net-bs5';
import 'datatables.net-bs5/css/dataTables.bootstrap5.min.css';

// Buttons (+ deps)
import 'datatables.net-buttons-bs5';
import 'datatables.net-buttons-bs5/css/buttons.bootstrap5.min.css';
import 'datatables.net-buttons/js/buttons.html5';
import 'datatables.net-buttons/js/buttons.print';
import 'datatables.net-buttons/js/buttons.colVis';

// Other extensions
import 'datatables.net-colreorder-bs5';
import 'datatables.net-fixedheader-bs5';
import 'datatables.net-scroller-bs5';
import 'datatables.net-scroller-bs5/css/scroller.bootstrap5.min.css';

// SearchBuilder (requires DateTime)
import 'datatables.net-searchbuilder-bs5';
import 'datatables.net-searchbuilder-bs5/css/searchBuilder.bootstrap5.min.css';
import 'datatables.net-datetime';

// File generators
import JSZip from 'jszip';
import pdfMake from 'pdfmake/build/pdfmake';
import pdfFonts from 'pdfmake/build/vfs_fonts';
window.JSZip = JSZip;
pdfMake.vfs = pdfFonts.vfs;
window.pdfMake = pdfMake;

// ---------------- helpers ----------------
function reviveColumnDefsFromParams(params) {
  const raw = params?.columnDefs;
  if (!Array.isArray(raw)) return [];
  return raw.map((d) => {
    const def = { ...d };
    let body =
      typeof def.createdCellBody === 'string'
        ? def.createdCellBody
        : (typeof def.createdCell === 'string' ? def.createdCell : null);

    if (body) {
      body = body.replace(/\\u0027/gi, "'");
      try {
        def.createdCell = new Function('td', 'cellData', 'rowData', 'row', 'col', body);
      } catch (_) {
        /* ignore invalid rule */
      }
      delete def.createdCellBody;
    }
    return def;
  });
}
function summarizeRuleBody(def) {
  const s = def.createdCellBody || def.createdCell?.toString?.() || '';
  const m = String(s).match(/if\s*\((.+?)\)\s*\{/);
  return m ? m[1] : '(custom cell rule)';
}

// Resolve a target (index or string header/column name) → numeric index
function makeIndexResolver(headers, columns) {
  return function resolveTargetToIndex(t) {
    if (Number.isInteger(t) && t >= 0) return t;
    if (typeof t === 'string') {
      let idx = headers.findIndex(h => h === t);
      if (idx >= 0) return idx;
      idx = columns.findIndex(c => String(c) === t || String(c).replaceAll('_', ' ') === t);
      if (idx >= 0) return idx;
    }
    return -1;
  };
}
function normalizeRulesForPersist(rules, resolveIndex) {
  const normalized = [];
  for (const r of rules || []) {
    const idx = resolveIndex(r?.targets);
    if (idx >= 0) normalized.push({ ...r, targets: idx });
  }
  return normalized;
}
// -----------------------------------------------------------

export default function DataTablesReport() {
  const hostEl = document.getElementById('react-datatables-report');

  const repid    = Number(hostEl?.dataset?.repid || 0);
  const reptitle = hostEl?.dataset?.reptitle || '';
  const repdesc  = hostEl?.dataset?.repdesc  || '';

  const colsUrl = hostEl?.dataset?.colsUrl || (repid ? `/api/dt/${repid}/columns` : '');
  const dataUrl = hostEl?.dataset?.dataUrl || (repid ? `/api/dt/${repid}` : '');

  const apiKey  = hostEl?.dataset?.apikey || window.REPWEB_API_KEY || '';

  const reportParams = hostEl?.dataset?.repparam ? JSON.parse(hostEl.dataset.repparam) : {};
  const initialRawRules = Array.isArray(reportParams?.columnDefs) ? reportParams.columnDefs : [];
  const initialRevived  = reviveColumnDefsFromParams(reportParams);

  const [err, setErr] = useState(null);
  const [columns, setColumns] = useState([]);
  const [headers, setHeaders] = useState([]);
  const [rulesRaw, setRulesRaw] = useState(initialRawRules);
  const [rulesPretty, setRulesPretty] = useState(initialRevived);

  const tableRef = useRef(null);
  const dtRef = useRef(null);
  const bootingRef = useRef(false);

  // resolver ref (filled during boot when we know columns/headers)
  const resolveIndexRef = useRef(null);

  // ----- conditional formatting modal state -----
  const [cfOpen, setCfOpen] = useState(false);
  const [cfDraft, setCfDraft] = useState({
    columnIndex: null,
    operator: 'equals',
    value1: '',
    value2: '',
    textColor: '#d00000',
    bgColor: '',
  });

  const [removingIndex, setRemovingIndex] = useState(null);

  useEffect(() => {
    if (cfOpen) document.body.classList.add('modal-open');
    else document.body.classList.remove('modal-open');
    return () => document.body.classList.remove('modal-open');
  }, [cfOpen]);

  // helper: base64url encode JSON (unchanged)
  const encodeState = (obj) => {
    const json = JSON.stringify(obj || {});
    const b64 = btoa(unescape(encodeURIComponent(json)));
    return b64.replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/,'');
  };
  const buildExportState = (dt) => {
    const visIdx = dt.columns(':visible').indexes().toArray();
    const allIdx = dt.columns().indexes().toArray();
    const orderedCols = (visIdx.length ? visIdx : allIdx).map(i => ({
      data: dt.column(i).dataSrc(),
      title: dt.column(i).header().innerText,
      visible: dt.column(i).visible(),
      position: i
    }));
    let searchBuilderJson;
    try {
      if (dt.searchBuilder && dt.searchBuilder.getDetails) {
        const details = dt.searchBuilder.getDetails();
        if (details && Object.keys(details).length) searchBuilderJson = details;
      }
    } catch {}
    return {
      search: dt.search(),
      order: dt.order(),
      columns: orderedCols,
      searchBuilder: searchBuilderJson || null,
    };
  };
  const apiUrlFromState = (reportId, dt, apiKey, opts = {}) => {
    const state = buildExportState(dt);
    const s = encodeState(state);
    const limit = opts.limit ?? 100;
    const offset = opts.offset ?? 0;
    const base = `/api/report/${reportId}.json`;
    const qs = new URLSearchParams({
      api_key: apiKey,
      state: s,
      limit: String(limit),
      offset: String(offset),
    }).toString();
    return `${base}?${qs}`;
  };
  const pickCsvDelimiterByLocale = () => {
    const lang = (navigator.language || 'en-US').toLowerCase();
    const euroLocales = ['fr','de','it','es','pt','nl','sv','da','fi','no','pl','cs','sk','sl','hu','ro','el'];
    return euroLocales.some(l => lang.startsWith(l)) ? ';' : ',';
  };
  const downloadResponse = async (res, fallbackName) => {
    if (!res.ok) {
      let msg = `Export failed (${res.status})`;
      try { const j = await res.json(); if (j?.error) msg = j.error; } catch {}
      throw new Error(msg);
    }
    const blob = await res.blob();
    let filename = fallbackName;
    const cd = res.headers.get('Content-Disposition') || '';
    const m = cd.match(/filename\*?=(?:UTF-8''|")?([^\";]+)/i);
    if (m && m[1]) filename = decodeURIComponent(m[1].replace(/"/g, ''));
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = filename; a.click();
    window.URL.revokeObjectURL(url);
  };
  const exportServerSide = async (format = 'xlsx', extraQuery = '') => {
    const dt = dtRef.current;
    if (!dt) return;
    const state = buildExportState(dt);
    const fname = `report-${repid || 'export'}.${format}`;
    const res = await fetch(`/api/dt/${repid}/export.${format}${extraQuery}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(state),
      credentials: 'include'
    });
    await downloadResponse(res, fname);
  };

  const firstFieldRef = useRef(null);
  const openSettings = (colIndex = null) => {
    setCfDraft(d => ({ ...d, columnIndex: colIndex }));
    setCfOpen(true);
    setTimeout(() => firstFieldRef.current?.focus(), 0);
  };

  // ---------- persist rules (normalize targets → numeric, POST replace) ----------
  async function persistRules(nextRaw) {
    const resolveIndex = resolveIndexRef.current || ((t)=>t);
    const normalized = normalizeRulesForPersist(nextRaw, resolveIndex);
    try {
      const r = await fetch(`/api/report/${repid}/repparam/columnDefs`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ replace: true, columnDefs: normalized })
      });
      return r.ok;
    } catch {
      return false;
    }
  }

  // ----------- remove by "clear & re-add" -----------
  async function removeRuleAt(index) {
    if (index == null || index < 0 || index >= rulesRaw.length) return;
    setRemovingIndex(index);
    const nextRaw = rulesRaw.filter((_r, i) => i !== index);
    try {
      const ok = await persistRules(nextRaw);
      if (!ok) throw new Error('Failed to update rules');
      setRulesRaw(nextRaw);
      setRulesPretty(reviveColumnDefsFromParams({ columnDefs: nextRaw }));
    } catch (e) {
      alert(`Failed to remove rule\n\n${e?.message || e}`);
    } finally {
      setRemovingIndex(null);
    }
  }

  // Save rule (MERGE multiple conditions for the same column)
  async function saveConditionalRule() {
    const { columnIndex, operator, value1, value2, textColor, bgColor } = cfDraft;
    if (columnIndex == null) {
      alert('Please choose a column');
      return;
    }

    let cond = '';
    const v1 = String(value1).trim();
    const v2 = String(value2).trim();
    const numExpr = "parseFloat(String(cellData).replace(/[^\\d.\\-]/g,''))";
    if (operator === 'equals')    cond = `String(cellData) === ${JSON.stringify(v1)}`;
    if (operator === 'contains')  cond = `String(cellData).includes(${JSON.stringify(v1)})`;
    if (operator === 'lt')        cond = `(${numExpr}) <  ${Number(v1)}`;
    if (operator === 'lte')       cond = `(${numExpr}) <= ${Number(v1)}`;
    if (operator === 'gt')        cond = `(${numExpr}) >  ${Number(v1)}`;
    if (operator === 'gte')       cond = `(${numExpr}) >= ${Number(v1)}`;
    if (operator === 'between')   cond = `(()=>{const n=(${numExpr});return !isNaN(n) && n>=${Number(v1)} && n<=${Number(v2)};})()`;

    const styles = [];
    if (textColor) styles.push(`$(td).css('color', ${JSON.stringify(textColor)})`);
    if (bgColor)   styles.push(`$(td).css('background-color', ${JSON.stringify(bgColor)})`);
    const newBody = `try{ if (${cond}) { ${styles.join('; ')}; } }catch(e){}`;

    const resolveIndex = resolveIndexRef.current || ((t)=>t);
    const numericColumnIndex = resolveIndex(columnIndex);
    const existingIdx = rulesRaw.findIndex(r => resolveIndex(r?.targets) === numericColumnIndex);

    if (existingIdx >= 0) {
      const prevBody =
        rulesRaw[existingIdx]?.createdCellBody ||
        rulesRaw[existingIdx]?.createdCell?.toString?.() ||
        '';
      const mergedBody = `try{ ${prevBody} }catch(e){}; try{ ${newBody} }catch(e){}`;
      const nextRaw = [...rulesRaw];
      nextRaw[existingIdx] = { targets: numericColumnIndex, createdCellBody: mergedBody };

      const ok = await persistRules(nextRaw);
      if (!ok) { alert('Failed to save merged rule'); return; }

      setRulesRaw(nextRaw);
      setRulesPretty(reviveColumnDefsFromParams({ columnDefs: nextRaw }));
      setCfOpen(false);
      return;
    }

    // append a brand-new rule
    const newRule = { targets: numericColumnIndex, createdCellBody: newBody };
    const res = await fetch(`/api/report/${repid}/repparam/columnDefs`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(newRule),
      credentials: 'same-origin'
    });

    if (!res.ok) {
      const nextRaw = [...rulesRaw, newRule];
      const ok = await persistRules(nextRaw);
      if (!ok) { alert('Save failed'); return; }
      setRulesRaw(nextRaw);
      setRulesPretty(reviveColumnDefsFromParams({ columnDefs: nextRaw }));
      setCfOpen(false);
      return;
    }

    const nextRaw = [...rulesRaw, newRule];
    setRulesRaw(nextRaw);
    setRulesPretty(reviveColumnDefsFromParams({ columnDefs: nextRaw }));
    setCfOpen(false);
  }

  // ----- DataTable (re)boot -----
  async function bootDataTable() {
    if (bootingRef.current) return;
    bootingRef.current = true;
    setErr(null);
    try {
      // destroy any existing DT
      try { dtRef.current?.destroy(true); dtRef.current = null; } catch {}

      const res = await fetch(colsUrl, { credentials: 'same-origin' });
      if (!res.ok) throw new Error(`Columns HTTP ${res.status}`);
      const { columns: cols = [] } = await res.json();
      if (!Array.isArray(cols) || cols.length === 0) throw new Error('No columns returned');

      setColumns(cols);
      const hdrs = cols.map(c => String(c).replaceAll('_',' '));
      setHeaders(hdrs);

      resolveIndexRef.current = makeIndexResolver(hdrs, cols);

      const $table = $(tableRef.current);
      const $thead = $table.find('thead').empty();
      $thead.append('<tr>' + cols.map(c => `<th>${String(c).replaceAll('_',' ')}</th>`).join('') + '</tr>');

      const revivedDefs = reviveColumnDefsFromParams({ columnDefs: rulesRaw });

      const dt = $table.DataTable({
        dom:
          "<'row g-2 align-items-center'<'col-md-7 dt-toolbar-left d-flex align-items-center'B><'col-md-5 dt-toolbar-right'f>>" +
          "<'row'<'col-12'tr>>" +
          "<'row'<'col-md-5'i><'col-md-7'p>>",

        processing: true,
        serverSide: true,

        ajax: {
          url: dataUrl,
          type: 'GET',
          dataSrc: 'data',
          data: function (d) {
            try {
              const api = new $.fn.dataTable.Api(this);
              if (api.searchBuilder && api.searchBuilder.getDetails) {
                const details = api.searchBuilder.getDetails();
                if (details && Object.keys(details).length) {
                  d.searchBuilderJson = JSON.stringify(details);
                } else {
                  delete d.searchBuilderJson;
                }
              }
            } catch {}
          }
        },

        columns: cols.map(c => ({ data: c })),
        columnDefs: revivedDefs,

        pageLength: 50,
        lengthMenu: [25, 50, 100, 250],
        fixedHeader: true,
        colReorder: true,
        responsive: false,
        scrollY: '60vh',
        scrollX: true,
        scrollCollapse: true,
        deferRender: true,
        scroller: true,

        stateSave: true,
        language: { searchBuilder: { button: '<i class="fas fa-filter"></i> Filter' } },
        searchBuilder: { serverSide: true },

        buttons: [
          { extend: 'colvis',     text: '<i class="fas fa-columns me-1"></i> Columns', className: 'btn btn-sm btn-outline-secondary' },
          { extend: 'copyHtml5',  text: '<i class="fas fa-copy me-1"></i> Copy',       className: 'btn btn-sm btn-outline-secondary' },
          {
            text: '<i class="fas fa-file-excel me-1"></i> XLSX',
            className: 'btn btn-sm btn-success',
            action: async () => { try { await exportServerSide('xlsx'); } catch (e) { alert(e.message || 'XLSX export failed'); } }
          },
          {
            text: '<i class="fas fa-file-csv me-1"></i> CSV',
            className: 'btn btn-sm btn-outline-primary',
            action: async () => {
              try { const delim = pickCsvDelimiterByLocale(); await exportServerSide('csv', `?delimiter=${encodeURIComponent(delim)}`); }
              catch (e) { alert(e.message || 'CSV export failed'); }
            }
          },
          {
            text: '<i class="fas fa-link me-1"></i> API URL',
            className: 'btn btn-sm btn-outline-dark',
            action: async () => {
              try {
                const k = hostEl?.dataset?.apikey || window.REPWEB_API_KEY || '';
                if (!k) { alert('No API key configured'); return; }
                const url = apiUrlFromState(repid, dtRef.current, k, { limit: 100, offset: 0 });
                await navigator.clipboard.writeText(`${window.location.origin}${url}`);
                alert('API URL copied to clipboard');
              } catch { alert('Failed to copy API URL'); }
            }
          },
          { extend: 'pdfHtml5',   text: '<i class="fas fa-file-pdf me-1"></i> PDF',    className: 'btn btn-sm btn-outline-danger' },
          { extend: 'print',      text: '<i class="fas fa-print me-1"></i> Print',     className: 'btn btn-sm btn-outline-secondary' },
          { extend: 'searchBuilder', text: '<i class="fas fa-filter me-1"></i> Filter', className: 'btn btn-sm btn-outline-primary' },
          {
            text: '<i className="fas fa-gear me-1"></i> Settings',
            className: 'btn btn-sm btn-outline-secondary',
            action: (_e, api) => {
              const firstVisible = api.columns(':visible').indexes().toArray()[0] ?? null;
              openSettings(firstVisible);
            }
          },
          {
            text: '<i class="fas fa-rotate-left me-1"></i> Reset',
            className: 'btn btn-sm btn-outline-warning',
            action: function (_e, dt) { try { dt.state.clear(); } catch {} window.location.reload(); }
          }
        ],

        order: []
      });

      dtRef.current = dt;
    } catch (e) {
      setErr(e?.message || 'Failed to initialize DataTable');
    } finally {
      bootingRef.current = false;
    }
  }

  // boot only when route/urls change
  useEffect(() => {
    bootDataTable();
    return () => { try { dtRef.current?.destroy(true); dtRef.current = null; } catch {} };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [colsUrl, dataUrl, repid]);

  // REBOOT whenever the rule set changes (after Save/Remove)
  useEffect(() => {
    // close modal/backdrop if open, then rebuild DT on next tick
    document.body.classList.remove('modal-open');
    if (cfOpen) setCfOpen(false);
    const id = setTimeout(() => { bootDataTable(); }, 0);
    return () => clearTimeout(id);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [rulesRaw]);

  // -------------- UI --------------
  const colNameForIndex = (t) => {
    if (Number.isInteger(t)) return headers[t] ?? `#${t}`;
    const byHeader = headers.find(h => h === t);
    if (byHeader) return byHeader;
    const idx = columns.findIndex(c => String(c) === t || String(c).replaceAll('_',' ') === t);
    return idx >= 0 ? (headers[idx] ?? `#${idx}`) : String(t);
  };

  const handleColumnSelect = (e) => {
    const idx = Number(e.target.value);
    setCfDraft(d => ({ ...d, columnIndex: Number.isNaN(idx) ? null : idx }));
  };

  return (
    <>
      {cfOpen && (
        <>
          <div
            className="modal fade show"
            style={{ display: 'block', zIndex: 20000 }}
            tabIndex="-1"
            role="dialog"
          >
            <div className="modal-dialog modal-md" role="document">
              <div className="modal-content">
                <div className="modal-header">
                  <h6 className="modal-title">Conditional formatting</h6>
                  <button type="button" className="btn-close" aria-label="Close" onClick={() => setCfOpen(false)} />
                </div>

                <div className="modal-body">
                  <div className="mb-2">
                    <label className="form-label">Column</label>
                    <select
                      ref={firstFieldRef}
                      className="form-select form-select-sm"
                      value={cfDraft.columnIndex ?? ''}
                      onChange={handleColumnSelect}
                    >
                      <option value="" disabled>Choose a column…</option>
                      {headers.map((h, i) => (
                        <option key={i} value={i}>{h}</option>
                      ))}
                    </select>
                  </div>

                  <div className="mb-2">
                    <label className="form-label">Operator</label>
                    <select
                      className="form-select form-select-sm"
                      value={cfDraft.operator}
                      onChange={(e) => setCfDraft((d) => ({ ...d, operator: e.target.value }))}>
                      <option value="equals">= equals (text)</option>
                      <option value="contains">contains (text)</option>
                      <option value="lt">&lt; less than (number)</option>
                      <option value="lte">≤ less or equal (number)</option>
                      <option value="gt">&gt; greater than (number)</option>
                      <option value="gte">≥ greater or equal (number)</option>
                      <option value="between">between (number)</option>
                    </select>
                  </div>

                  <div className="mb-2">
                    <label className="form-label">Value</label>
                    <input
                      className="form-control form-control-sm"
                      type="text"
                      value={cfDraft.value1}
                      onChange={(e) => setCfDraft((d) => ({ ...d, value1: e.target.value }))}/>
                  </div>

                  {cfDraft.operator === 'between' && (
                    <div className="mb-2">
                      <label className="form-label">And</label>
                      <input
                        className="form-control form-control-sm"
                        type="text"
                        value={cfDraft.value2}
                        onChange={(e) => setCfDraft((d) => ({ ...d, value2: e.target.value }))}/>
                    </div>
                  )}

                  <div className="row">
                    <div className="col-6 mb-2">
                      <label className="form-label">Text color</label>
                      <input className="form-control form-control-sm" type="color"
                        value={cfDraft.textColor || '#000000'}
                        onChange={(e) => setCfDraft((d) => ({ ...d, textColor: e.target.value }))}/>
                    </div>
                    <div className="col-6 mb-2">
                      <label className="form-label">Background</label>
                      <input className="form-control form-control-sm" type="color"
                        value={cfDraft.bgColor || '#000000'}
                        onChange={(e) => setCfDraft((d) => ({ ...d, bgColor: e.target.value }))}/>
                    </div>
                  </div>

                  <hr />
                  <div className="mb-1 fw-semibold small">Existing rules</div>
                  {rulesRaw.length === 0 ? (
                    <div className="small text-muted">No rules saved yet.</div>
                  ) : (
                    <ul className="list-group list-group-flush">
                      {rulesRaw.map((r, i) => (
                        <li key={i} className="list-group-item d-flex justify-content-between align-items-start px-0">
                          <div className="me-2">
                            <div className="small">
                              <span className="badge text-bg-light me-1">{colNameForIndex(r.targets)}</span>
                              <span className="text-muted">{summarizeRuleBody(rulesPretty[i] || r)}</span>
                            </div>
                          </div>
                          <button
                            className="btn btn-sm btn-outline-danger"
                            onClick={() => removeRuleAt(i)}
                            disabled={removingIndex === i}
                          >
                            {removingIndex === i ? 'Removing…' : 'Remove'}
                          </button>
                        </li>
                      ))}
                    </ul>
                  )}
                </div>

                <div className="modal-footer">
                  <button className="btn btn-sm btn-secondary" onClick={() => setCfOpen(false)}>
                    Cancel
                  </button>
                  <button className="btn btn-sm btn-primary" onClick={saveConditionalRule}>
                    Save
                  </button>
                </div>
              </div>
            </div>
          </div>
          <div className="modal-backdrop fade show" style={{ zIndex: 19990 }}></div>
        </>
      )}

      <div className="card shadow-sm">
        <div className="card-body">
          <div className="mb-2">
            <div className="fw-bold">{reptitle || (repid ? `Report #${repid}` : 'Report')}</div>
            {repdesc && (
              <div className="small text-muted" dangerouslySetInnerHTML={{ __html: repdesc }} />
            )}
            {err && <div className="badge text-bg-danger ms-2 align-middle">{err}</div>}
          </div>

          <table
            ref={tableRef}
            id="dt"
            className="table table-sm table-striped table-bordered nowrap w-100"
            style={{ width: '100%' }}
          >
            <thead />
            <tbody />
          </table>
        </div>
      </div>
    </>
  );
}
