import React from 'react';
import { Handle, Position } from 'reactflow';

export default function NodeCard({ data }) {
  return (
    <div className="taskdep-node">
      <Handle type="target" position={Position.Left} />
      <Handle type="source" position={Position.Right} />
      <div className="taskdep-node__title">{data?.label || 'Task'}</div>
      <div className="taskdep-node__subtitle">
        <div className="taskdep-node__controls">
          <div className="taskdep-node__control">
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
          <div className="taskdep-node__control">
            <label className="form-label mb-1">Mode</label>
            <select
              className="form-select form-select-sm"
              value={data?.durationMode ?? 'ignore'}
              onChange={(event) => data?.onDurationModeChange?.(event.target.value)}
            >
              <option value="ignore">Ignore</option>
              <option value="enforce">Enforce</option>
              <option value="min">Min</option>
              <option value="max">Max</option>
            </select>
          </div>
        </div>
      </div>
    </div>
  );
}
