import React from 'react';

export default function ContextMenu({ pos, onPickDepth, onClose }) {
  if (!pos) return null;

  const stop = (e) => { e.preventDefault(); e.stopPropagation(); };

  const Item = ({ depth, label }) => (
    <button
      type="button"
      className="dropdown-item"
      style={{ cursor: 'pointer' }}
      onMouseDown={stop}
      onClick={(e) => { stop(e); onPickDepth(depth); }}
    >
      {label}
    </button>
  );

  return (
    <div
      className="cmdb-context-menu dropdown-menu show"
      style={{
        position: 'fixed',
        left: pos.x,
        top: pos.y,
        zIndex: 9999,
        pointerEvents: 'auto'
      }}
      onMouseDown={stop}
      onClick={stop}
      onContextMenu={stop}
    >
      <h6 className="dropdown-header">Relation zoom</h6>
      <Item depth={0} label="Only this CI" />
      <Item depth={1} label="1 level" />
      <Item depth={2} label="2 levels" />
      <Item depth={3} label="3 levels" />
      <Item depth={99} label="All connected (safe cap)" />
      <div className="dropdown-divider" />
      <button
        type="button"
        className="dropdown-item text-muted"
        onMouseDown={stop}
        onClick={(e) => { stop(e); onClose(); }}
      >
        Close
      </button>
    </div>
  );
}
