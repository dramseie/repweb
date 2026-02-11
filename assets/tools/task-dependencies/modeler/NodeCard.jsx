import React from 'react';

export default function NodeCard({ data }) {
  return (
    <div className="taskdep-node">
      <div className="taskdep-node__title">{data?.label || 'Task'}</div>
      <div className="taskdep-node__subtitle">Dependency</div>
    </div>
  );
}
