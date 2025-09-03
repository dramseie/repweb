import React from 'react';
import { typeColors } from './theme/colors';

const isFa = (s) => typeof s === 'string' && (s.includes('fa-') || s.startsWith('fa '));
const Icon = ({ icon, color }) => {
  if (!icon) return <span style={{ fontSize: 16 }}>🧩</span>;
  if (isFa(icon)) return <i className={icon} aria-hidden="true" style={{ fontSize: 16, lineHeight: 1, color }} />;
  return <span style={{ fontSize: 16 }}>{icon}</span>;
};

export default function Palette({ types, onCreate }) {
  return (
    <div className="cmdb-palette">
      <div className="fw-bold mb-2">CI Types</div>
      {types.map(t => {
        const color = typeColors[t.code] || undefined;
        return (
          <div
            key={t.code}
            className="d-flex justify-content-between align-items-center border rounded p-2 mb-2"
            style={{ width: '100%' }}
          >
            <span style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }}>
              <Icon icon={t.icon} color={color} /> {t.label}
            </span>
            <button
              className="btn btn-sm btn-outline-primary"
              style={{ marginLeft: 'auto' }}
              onClick={() => onCreate(t.code)}
            >
              Add
            </button>
          </div>
        );
      })}
    </div>
  );
}
