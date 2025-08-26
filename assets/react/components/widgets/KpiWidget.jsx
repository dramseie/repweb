import React from 'react';

export default function KpiWidget({ label = 'KPI', value = 0, sub = '' }) {
  return (
    <div className="p-3">
      <div className="display-6 fw-bold">{value}</div>
      <div className="text-uppercase small text-muted">{label}</div>
      {sub && <div className="text-secondary small">{sub}</div>}
    </div>
  );
}
