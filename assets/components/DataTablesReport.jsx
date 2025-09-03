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

// File generators for built-in html5 buttons (you can keep them for quick client exports)
import JSZip from 'jszip';
import pdfMake from 'pdfmake/build/pdfmake';
import pdfFonts from 'pdfmake/build/vfs_fonts';
window.JSZip = JSZip;
pdfMake.vfs = pdfFonts.vfs;
window.pdfMake = pdfMake;

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
      // normalize '\u0027' to real quotes in case JSON came that way
      body = body.replace(/\\u0027/gi, "'");
      try {
        def.createdCell = new Function('td', 'cellData', 'rowData', 'row', 'col', body);
      } catch (e) {
        console.error('Invalid createdCellBody in repparam:', body, e);
      }
      delete def.createdCellBody;
    }
    return def;
  });
}



export default function DataTablesReport() {
  const hostEl = document.getElementById('react-datatables-report');

  const repid    = Number(hostEl?.dataset?.repid || 0);
  const reptitle = hostEl?.dataset?.reptitle || '';
  const repdesc  = hostEl?.dataset?.repdesc  || '';

  const colsUrl = hostEl?.dataset?.colsUrl || (repid ? `/api/dt/${repid}/columns` : '');
  const dataUrl = hostEl?.dataset?.dataUrl || (repid ? `/api/dt/${repid}` : '');

  const apiKey  = hostEl?.dataset?.apikey || window.REPWEB_API_KEY || '';

	const reportParams = hostEl?.dataset?.repparam ? JSON.parse(hostEl.dataset.repparam) : {};
	const extraColumnDefs = reviveColumnDefsFromParams(reportParams);


  const tableRef = useRef(null);
  const dtRef = useRef(null);

  const [err, setErr] = useState(null);

	// helper: base64url encode JSON
	const encodeState = (obj) => {
	  const json = JSON.stringify(obj || {});
	  const b64 = btoa(unescape(encodeURIComponent(json)));
	  return b64.replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/,'');
	};

	const apiUrlFromState = (reportId, dt, apiKey, opts = {}) => {
	  const state = buildExportState(dt); // you already have this helper
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


  // --- helpers for server-side export ---
  const pickCsvDelimiterByLocale = () => {
    const lang = (navigator.language || 'en-US').toLowerCase();
    // Excel in many European locales prefers semicolon
    const euroLocales = ['fr', 'de', 'it', 'es', 'pt', 'nl', 'sv', 'da', 'fi', 'no', 'pl', 'cs', 'sk', 'sl', 'hu', 'ro', 'el'];
    return euroLocales.some(l => lang.startsWith(l)) ? ';' : ',';
  };

  const buildExportState = (dt) => {
    // Columns in the order currently shown (respect visibility)
    const visIdx = dt.columns(':visible').indexes().toArray();
    const allIdx = dt.columns().indexes().toArray();

    // Prefer visible columns; server will enforce its own allow-list anyway
    const orderedCols = (visIdx.length ? visIdx : allIdx).map(i => ({
      data: dt.column(i).dataSrc(),
      title: dt.column(i).header().innerText,
      visible: dt.column(i).visible(),
      // include position for server to respect current order if desired
      position: i
    }));

    // Include SearchBuilder JSON if present
    let searchBuilderJson;
    try {
      if (dt.searchBuilder && dt.searchBuilder.getDetails) {
        const details = dt.searchBuilder.getDetails();
        if (details && Object.keys(details).length) {
          searchBuilderJson = details; // send as object; server can json_encode
        }
      }
    } catch {}

    return {
      search: dt.search(),
      order: dt.order(),          // [[colIdx,'asc'|'desc'], ...] based on current DT
      columns: orderedCols,
      searchBuilder: searchBuilderJson || null,
      // place for your own custom filters if you add them in the future:
      // filters: { ... }
    };
  };

  const downloadResponse = async (res, fallbackName) => {
    if (!res.ok) {
      // try to surface JSON error {error:"..."} if server sent one
      let msg = `Export failed (${res.status})`;
      try { const j = await res.json(); if (j?.error) msg = j.error; } catch {}
      throw new Error(msg);
    }
    const blob = await res.blob();

    // Try to parse filename from Content-Disposition
    let filename = fallbackName;
    const cd = res.headers.get('Content-Disposition') || '';
    const m = cd.match(/filename\*?=(?:UTF-8''|")?([^\";]+)/i);
    if (m && m[1]) {
      filename = decodeURIComponent(m[1].replace(/"/g, ''));
    }

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
  // --- end helpers ---

  useEffect(() => {
    let alive = true;

    async function boot() {
      setErr(null);
      try {
        // 1) fetch columns
        const res = await fetch(colsUrl, { credentials: 'same-origin' });
        if (!res.ok) throw new Error(`Columns HTTP ${res.status}`);
        const { columns: cols = [] } = await res.json();
        if (!alive) return;
        if (!Array.isArray(cols) || cols.length === 0) throw new Error('No columns returned');

        // 2) build thead
        const $table = $(tableRef.current);
        const $thead = $table.find('thead').empty();
        $thead.append('<tr>' + cols.map(c => `<th>${String(c).replaceAll('_',' ')}</th>`).join('') + '</tr>');

        // 3) init DataTable
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
		  columnDefs: extraColumnDefs,   // 👈 add this line
          // Compact + scrolling
          pageLength: 50,
          lengthMenu: [25, 50, 100, 250],
          fixedHeader: true,
          colReorder: true,
          responsive: false,     // allow real horizontal scroll
          scrollY: '60vh',
          scrollX: true,
          scrollCollapse: true,
          deferRender: true,
          scroller: true,        // requires paging

          stateSave: true,
			language: {
				searchBuilder: {
					button: '<i class="fas fa-filter"></i> Filter'
				}
			},
          // Enable SearchBuilder feature (required for the button)
          searchBuilder: {
            serverSide: true
          },

          // Buttons (order requested)
          buttons: [
            // --- Keep your existing client-side buttons for quick local exports ---
            { extend: 'colvis',     text: '<i class="fas fa-columns me-1"></i> Columns', className: 'btn btn-sm btn-outline-secondary' },
            { extend: 'copyHtml5',  text: '<i class="fas fa-copy me-1"></i> Copy',       className: 'btn btn-sm btn-outline-secondary' },

            // --- New: Server-side export buttons (OpenSpout via Symfony) ---
            {
              text: '<i class="fas fa-file-excel me-1"></i> XLSX',
              className: 'btn btn-sm btn-success',
              action: async () => {
                try { await exportServerSide('xlsx'); }
                catch (e) { alert(e.message || 'XLSX export failed'); }
              }
            },
            {
              text: '<i class="fas fa-file-csv me-1"></i> CSV',
              className: 'btn btn-sm btn-outline-primary',
              action: async () => {
                try {
                  const delim = pickCsvDelimiterByLocale();
                  await exportServerSide('csv', `?delimiter=${encodeURIComponent(delim)}`);
                } catch (e) { alert(e.message || 'CSV export failed'); }
              }
            },

			// Add a new button in the DataTables `buttons` array:
			{
			  text: '<i class="fas fa-link me-1"></i> API URL',
			  className: 'btn btn-sm btn-outline-dark',
			  action: async () => {
				try {
				  const apiKey = hostEl?.dataset?.apikey || window.REPWEB_API_KEY || '';
				  if (!apiKey) { alert('No API key configured'); return; }
				  const url = apiUrlFromState(repid, dtRef.current, apiKey, { limit: 100, offset: 0 });
				  await navigator.clipboard.writeText(`${window.location.origin}${url}`);
				  // Optional: open in new tab
				  // window.open(url, '_blank', 'noopener');
				  alert('API URL copied to clipboard');
				} catch (e) {
				  alert('Failed to copy API URL');
				}
			  }
			},

            { extend: 'pdfHtml5',   text: '<i class="fas fa-file-pdf me-1"></i> PDF',    className: 'btn btn-sm btn-outline-danger' },
            { extend: 'print',      text: '<i class="fas fa-print me-1"></i> Print',     className: 'btn btn-sm btn-outline-secondary' },

            // Built-in SearchBuilder toggle
            { extend: 'searchBuilder',
              text: '<i class="fas fa-filter me-1"></i> Filter',
              className: 'btn btn-sm btn-outline-primary',
            },

            { text: '<i class="fas fa-rotate-left me-1"></i> Reset',
              className: 'btn btn-sm btn-outline-warning',
              action: function (_e, dt) {
                try { dt.state.clear(); } catch {}
                window.location.reload();
              }
            }
          ],

          order: []
        });

        dtRef.current = dt;
	
      } catch (e) {
        console.error(e);
        if (alive) setErr(e?.message || 'Failed to initialize DataTable');
      }
    }

    boot();

    return () => {
      alive = false;
      try { if (dtRef.current) { dtRef.current.destroy(true); dtRef.current = null; } } catch {}
    };
  }, [colsUrl, dataUrl, repid]);

  return (
    <div className="card shadow-sm">
	
      <div className="card-body">
        <div className="mb-2">
          <div className="fw-bold">
            {reptitle || (repid ? `Report #${repid}` : 'Report')}
          </div>
            {repdesc && (
				<div
					className="small text-muted"
					dangerouslySetInnerHTML={{ __html: repdesc }}
				/>
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
  );
}
