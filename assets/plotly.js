import React from 'react';
import { createRoot } from 'react-dom/client';
import PlotlyChartFromReport from './react/components/PlotlyChartFromReport.jsx';

const el = document.getElementById('react-plotly-report');
if (el) {
  const reportId = el.dataset.reportId;
  const apiBase  = el.dataset.apiBase || '/api/plotly';
  createRoot(el).render(
    <PlotlyChartFromReport reportId={reportId} apiBase={apiBase} />
  );
}
