// assets/react/components/widgets/PlotlyWidget.jsx
import React from 'react';
import PlotlyChartFromReport from '../PlotlyChartFromReport'; // adjust path if different

export default function PlotlyWidget({
  reportId,
  height = 360,
  initialChart = 'line',
  initialAgg = 'none',
}) {
  return (
    <PlotlyChartFromReport
      reportId={reportId}
      height={height}
      initialChart={initialChart}
      initialAgg={initialAgg}
    />
  );
}
