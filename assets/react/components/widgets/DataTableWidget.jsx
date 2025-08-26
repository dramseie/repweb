// assets/react/components/widgets/DataTableWidget.jsx
import React, { useEffect, useRef } from 'react';
import $ from 'jquery';
import 'datatables.net-bs5';
import 'datatables.net-bs5/css/dataTables.bootstrap5.min.css';

export default function DataTableWidget({ reportId, pageLength = 15 }) {
  const ref = useRef(null);

  useEffect(() => {
    const $el = $(ref.current);
    const table = $el.DataTable({
      ajax: `/api/dt/${reportId}/data`, // your backend
      columns: [],                      // server-driven columns
      pageLength,
      destroy: true,
      deferRender: true,
      autoWidth: false,
      searching: true,
      ordering: true,
      responsive: true,
    });
    return () => table.destroy();
  }, [reportId, pageLength]);

  return <table ref={ref} className="table table-sm table-striped w-100" />;
}
