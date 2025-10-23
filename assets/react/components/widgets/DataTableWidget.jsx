// assets/react/components/widgets/DataTableWidget.jsx
import React, { useEffect, useRef } from 'react';
import $ from 'jquery';

// DataTables + Bootstrap 5
import 'datatables.net-bs5';
import 'datatables.net-bs5/css/dataTables.bootstrap5.min.css';

// SearchBuilder is already patched in app.js to add 'date_range_picker'
import moment from 'moment';

export default function DataTableWidget({
  reportId,
  pageLength = 15,
  // Optional shape: { columns: [{ data, title, data_type|type }, ...] }
  schema,
  // Pass-through DT options (ajax, columns, etc.)
  dtOptions = {}
}) {
  const tableRef = useRef(null);

  useEffect(() => {
    const $table = $(tableRef.current);

    // Detect DATE/DATETIME/TIMESTAMP columns from schema (preferred)
    const dateCols = (schema?.columns || [])
      .map((c, i) => ({ i, t: (c.data_type || c.type || '').toLowerCase() }))
      .filter(({ t }) => ['date', 'datetime', 'timestamp'].includes(t))
      .map(({ i }) => i);

    // Ensure proper sorting for date strings
    const dateRender = (data, type) => {
      if (type === 'sort' || type === 'type') {
        const m = moment(data);
        return m.isValid() ? m.valueOf() : 0;
      }
      return data ?? '';
    };

    // Build columnDefs: FORCE SearchBuilder to use our custom DRP condition
    let columnDefs = dtOptions.columnDefs || [];
    if (Array.isArray(schema?.columns)) {
      columnDefs = [
        ...columnDefs,
        ...schema.columns.map((_, i) =>
          dateCols.includes(i)
            ? {
                targets: i,
                searchBuilderType: 'date',
                searchBuilder: { defaultCondition: 'date_range_picker' },
                render: dateRender
              }
            : { targets: i }
        )
      ];
    }

    const options = {
      dom: 'Qlfrtip',          // Q = SearchBuilder visible
      stateSave: true,
      pageLength,
      columnDefs,
      ...dtOptions
    };

    const dt = $table.DataTable(options);

    // Cleanup: destroy SB + DRP instances to avoid leaks on unmount/re-render
    return () => {
      try { dt.searchBuilder?.destroy?.(); } catch {}
      try { dt.destroy(true); } catch {}
      $table.find('.dt-sb-daterange').each(function () {
        const dr = $(this).data('daterangepicker');
        if (dr) { try { dr.remove(); } catch {} }
      });
    };
  // stringify to avoid stale closures but still re-init on changes
  }, [reportId, pageLength, JSON.stringify(schema), JSON.stringify(dtOptions)]);

  return (
    <div className="card shadow-sm">
      <div className="card-body p-2">
        <table
          ref={tableRef}
          className="table table-sm table-striped table-bordered nowrap w-100"
        >
          <thead>
            {Array.isArray(schema?.columns) && (
              <tr>
                {schema.columns.map((c, idx) => (
                  <th key={idx}>{c.title ?? c.data ?? `Col ${idx + 1}`}</th>
                ))}
              </tr>
            )}
          </thead>
          <tbody />
        </table>
      </div>
    </div>
  );
}
