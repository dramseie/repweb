// assets/tools/cmdb-modeler/modeler/NodeCard.jsx
import React from 'react';
import { typeColors } from './theme/colors';
import { Handle, Position } from 'reactflow';


function NodeCard({ data }) {
  const icon = data?.icon;
  const label = data?.label ?? '';
  const typeCode = data?.type ?? '';
  const color = typeColors[typeCode] || '#adb5bd'; // default gray

  return (
    <div
      style={{
        padding: '8px 10px',
        border: `2px solid ${color}`,
        borderRadius: 8,
        background: '#fff',
        minWidth: 140,
        boxShadow: '0 2px 4px rgba(0,0,0,.1)',
        cursor: 'grab',
      }}
      title={label}
    >
      <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
        {icon?.startsWith('fa-') ? (
          <i className={icon} style={{ fontSize: 18, color }} />
        ) : (
          <span style={{ fontSize: 18 }}>{icon || '🧩'}</span>
        )}
        <div style={{ fontWeight: 600, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
          {label}
        </div>
      </div>
      <div style={{ fontSize: 11, color: '#6c757d', marginTop: 2 }}>{typeCode}</div>

      {/* Connection handles */}
      <Handle type="target" position={Position.Top} />
      <Handle type="source" position={Position.Bottom} />
    </div>
  );
}

export default NodeCard;
