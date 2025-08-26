// assets/react/components/widgets/PivotWidget.jsx
import React from 'react';
import PivotReport from '../PivotReport'; // <-- fixed path

export default function PivotWidget({ reportId }) {
  return <PivotReport reportId={reportId} />;
}
