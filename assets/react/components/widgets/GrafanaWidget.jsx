// assets/react/components/widgets/GrafanaWidget.jsx
import React from 'react';

export default function GrafanaWidget({ src = 'about:blank', height = 360 }) {
  return (
    <iframe
      title="grafana"
      src={src}
      frameBorder="0"
      style={{ width: '100%', height }}
      allowFullScreen
    />
  );
}
