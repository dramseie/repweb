import React from 'react';
import { Handle, Position } from 'reactflow';

export default function NodeCard({ data }) {
  return (
    <div className="taskdep-node">
      <Handle type="target" position={Position.Left} />
      <Handle type="source" position={Position.Right} />
      <div className="taskdep-node__title">{data?.label || 'Task'}</div>
      <div className="taskdep-node__subtitle">
        <label className="form-label mb-1">Duration</label>
        <input
          type="number"
          className="form-control form-control-sm taskdep-input-no-spin"
          min="0"
          step="1"
          value={data?.duration ?? ''}
          onChange={(event) => data?.onDurationChange?.(event.target.value)}
        />
      </div>
    </div>
  );
}
