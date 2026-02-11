import React, { useCallback, useState } from 'react';
import ReactFlow, { Background, Controls, MiniMap } from 'reactflow';

export default function Canvas({
  nodes,
  edges,
  nodeTypes,
  onNodesChange,
  onEdgesChange,
  onConnect,
  onNodeClick,
  onNodeDragStop,
  onSelectionChange,
  onDropCreate,
}) {
  const [flowInstance, setFlowInstance] = useState(null);

  const onDragOver = useCallback((event) => {
    event.preventDefault();
    event.dataTransfer.dropEffect = 'copy';
  }, []);

  const onDrop = useCallback((event) => {
    event.preventDefault();
    const taskName = event.dataTransfer.getData('application/x-task-name');
    if (!taskName) return;
    const bounds = event.currentTarget.getBoundingClientRect();
    const position = flowInstance
      ? flowInstance.project({ x: event.clientX - bounds.left, y: event.clientY - bounds.top })
      : { x: 0, y: 0 };
    onDropCreate(taskName, position);
  }, [flowInstance, onDropCreate]);

  return (
    <div style={{ flex: 1 }} onDragOver={onDragOver} onDrop={onDrop}>
      <ReactFlow
        nodes={nodes}
        edges={edges}
        nodeTypes={nodeTypes}
        onInit={setFlowInstance}
        onNodesChange={onNodesChange}
        onEdgesChange={onEdgesChange}
        onConnect={onConnect}
        onNodeClick={(_, node) => onNodeClick(node)}
        onNodeDragStop={onNodeDragStop}
        onSelectionChange={onSelectionChange}
        fitView
      >
        <Background />
        <MiniMap pannable zoomable />
        <Controls />
      </ReactFlow>
    </div>
  );
}
