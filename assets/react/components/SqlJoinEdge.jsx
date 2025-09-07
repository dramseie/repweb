import React from 'react';
import { BaseEdge, getBezierPath } from 'reactflow';

export default function SqlJoinEdge({ id, sourceX, sourceY, targetX, targetY, markerEnd, data }) {
  const [path] = getBezierPath({ sourceX, sourceY, targetX, targetY });
  return (
    <>
      <BaseEdge id={id} path={path} markerEnd={markerEnd} />
      <foreignObject x={(sourceX + targetX) / 2 - 50} y={(sourceY + targetY) / 2 - 18} width={100} height={36}>
        <div
          className="bg-white border rounded px-1 py-0.5 text-xs text-center shadow"
          style={{ userSelect: 'none', cursor: 'pointer' }}
          onClick={() => {
            // Cycle join type on click (INNER -> LEFT -> RIGHT -> …)
            const order = ['INNER', 'LEFT', 'RIGHT'];
            const i = order.indexOf(data?.joinType || 'INNER');
            // mutate label; parent state updates on next render
            data.joinType = order[(i + 1) % order.length];
          }}
          title="Click to change join type"
        >
          {data?.joinType || 'INNER'}
        </div>
      </foreignObject>
    </>
  );
}
