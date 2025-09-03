import React, { useCallback } from 'react';
import ReactFlow, { Background, Controls, MiniMap } from 'reactflow';

export default function Canvas({
  nodes, edges, nodeTypes,
  onNodesChange, onEdgesChange, onConnect,
  onNodeClick, onNodeContextMenu, onDropCreate
}) {
  const onDragOver = useCallback((e) => { e.preventDefault(); e.dataTransfer.dropEffect = 'copy'; }, []);
  const onDrop = useCallback((e) => {
    e.preventDefault();
    const type = e.dataTransfer.getData('application/x-cmdb-type');
    if (type) onDropCreate(type, e.clientX, e.clientY);
  }, [onDropCreate]);

  return (
    <div style={{ flex: 1 }} onDragOver={onDragOver} onDrop={onDrop}>
      <ReactFlow
        nodes={nodes}
        edges={edges}
        nodeTypes={nodeTypes}
        onNodesChange={onNodesChange}
        onEdgesChange={onEdgesChange}
        onConnect={onConnect}
        onNodeClick={(_, n) => onNodeClick(n)}
        onNodeContextMenu={onNodeContextMenu}
        fitView
      >
        <Background />
        <MiniMap pannable zoomable />
        <Controls />
      </ReactFlow>
    </div>
  );
}
