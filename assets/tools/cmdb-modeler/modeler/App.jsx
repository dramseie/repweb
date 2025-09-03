import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { useEdgesState, useNodesState } from 'reactflow';
import * as api from '../api';
import Toolbar from './Toolbar';
import Palette from './Palette';
import Inspector from './Inspector';
import Canvas from './Canvas';
import useKeybinds from './useKeybinds';
import ELK from 'elkjs/lib/elk.bundled.js';
import NodeCard from './NodeCard.jsx';
import ContextMenu from './ContextMenu.jsx';

const nodeTypes = { cmdbCard: NodeCard };
const elk = new ELK();

export default function App() {
  const [types, setTypes] = useState([]);
  const [relTypes, setRelTypes] = useState([]);
  const [nodes, setNodes, onNodesChange] = useNodesState([]);
  const [edges, setEdges, onEdgesChange] = useEdgesState([]);
  const [selNode, setSelNode] = useState(null);
  const [typeFilter, setTypeFilter] = useState('');
  const [ctxMenu, setCtxMenu] = useState(null);

  // initial load
  useEffect(() => {
    (async () => {
      const [t, rt, g] = await Promise.all([
        api.getTypes(),
        api.getRelTypes(),
        api.getGraph()
      ]);
      setTypes(t);
      setRelTypes(rt);

      const iconMap = Object.fromEntries((t || []).map(x => [x.code, x.icon || '🧩']));

      setNodes(
        g.nodes.map(n => ({
          id: n.id,
          type: 'cmdbCard',
          data: { label: n.label, type: n.type, icon: iconMap[n.type] },
          position: n.position || { x: Math.random() * 600, y: Math.random() * 400 }
        }))
      );
      setEdges(
        g.edges.map(e => ({
          id: String(e.id),
          source: e.source,
          target: e.target,
          label: e.label
        }))
      );
    })();
  }, [setNodes, setEdges]);

  const onConnect = useCallback(
    async ({ source, target }) => {
      const type = relTypes[0]?.code || 'depends_on';
      const r = await api.createEdge(source, target, type);
      setEdges(eds => eds.concat({ id: String(r.id), source, target, label: type }));
    },
    [relTypes, setEdges]
  );

  const onSaveLayout = useCallback(async () => {
    const payload = Object.fromEntries(
      nodes.map(n => [n.id, { x: n.position.x, y: n.position.y }])
    );
    await api.saveLayout('default', payload);
  }, [nodes]);

  const onCreateNode = useCallback(
    async (typeCode) => {
      const name = prompt('CI display name?') || 'New CI';
      const res = await api.createNode(typeCode, { name });
      const iconMap = Object.fromEntries(types.map(x => [x.code, x.icon || '🧩']));
      setNodes(nds =>
        nds.concat({
          id: res.ci,
          type: 'cmdbCard',
          data: { label: res.label, type: typeCode, icon: iconMap[typeCode] },
          position: { x: 120 + Math.random() * 240, y: 80 + Math.random() * 160 }
        })
      );
    },
    [types, setNodes]
  );

  const onDeleteNode = useCallback(async () => {
    if (!selNode) return;
    await api.deleteNode(selNode.id);
    setEdges(eds => eds.filter(e => e.source !== selNode.id && e.target !== selNode.id));
    setNodes(nds => nds.filter(n => n.id !== selNode.id));
    setSelNode(null);
  }, [selNode, setEdges, setNodes]);

  const onUpdateNode = useCallback(
    async (patch) => {
      if (!selNode) return;
      await api.updateNode(selNode.id, { name: patch.name, attrs: patch.attrs || {} });
      setNodes(nds =>
        nds.map(n =>
          n.id === selNode.id ? { ...n, data: { ...n.data, label: patch.name } } : n
        )
      );
    },
    [selNode, setNodes]
  );

  const onSearch = useCallback(
    (q) => {
      if (!q) return;
      const n =
        nodes.find(n => n.id === q) ||
        nodes.find(n => (n.data?.label || '').toLowerCase().includes(q.toLowerCase()));
      if (n) setSelNode(n);
    },
    [nodes]
  );

  const filteredNodes = useMemo(
    () => (typeFilter ? nodes.filter(n => n.data.type === typeFilter) : nodes),
    [nodes, typeFilter]
  );

  // Auto-layout with ELK (layered)
  const onAutoLayout = useCallback(async () => {
    const g = {
      id: 'root',
      layoutOptions: { 'elk.algorithm': 'layered', 'elk.direction': 'RIGHT' },
      children: filteredNodes.map(n => ({ id: n.id, width: 160, height: 60 })),
      edges: edges.map(e => ({ id: e.id, sources: [e.source], targets: [e.target] }))
    };
    const res = await elk.layout(g);
    const pos = Object.fromEntries(
      (res.children || []).map(c => [c.id, { x: c.x || 0, y: c.y || 0 }])
    );
    setNodes(nds => nds.map(n => (pos[n.id] ? { ...n, position: pos[n.id] } : n)));
  }, [filteredNodes, edges, setNodes]);

  // keybinds
  useEffect(() => useKeybinds({ onSave: onSaveLayout, onDelete: onDeleteNode }), [
    onSaveLayout,
    onDeleteNode
  ]);

  // Context menu (relation zoom)
  const onNodeContextMenu = useCallback((evt, node) => {
    evt.preventDefault();
    setSelNode(node);
    setCtxMenu({ x: evt.clientX, y: evt.clientY, node });
  }, []);

  const onPickDepth = useCallback(
    async (depth) => {
      if (!ctxMenu?.node) return;
      const g = await api.getGraphEgo(ctxMenu.node.id, depth >= 99 ? 10 : depth);
      const iconMap = Object.fromEntries(types.map(t => [t.code, t.icon || '🧩']));
      setNodes(
        g.nodes.map(n => ({
          id: n.id,
          type: 'cmdbCard',
          data: { label: n.label, type: n.type, icon: iconMap[n.type] },
          position: n.position || { x: Math.random() * 600, y: Math.random() * 400 }
        }))
      );
      setEdges(g.edges.map(e => ({ id: String(e.id), source: e.source, target: e.target, label: e.label })));
      setCtxMenu(null);
    },
    [ctxMenu, types, setNodes, setEdges]
  );

  return (
    <div className="cmdb-wrap">
      <Palette types={types} onCreate={(type) => onCreateNode(type)} />
      <div className="cmdb-canvas" onContextMenu={(e) => e.preventDefault()}>
        <Toolbar
          onSave={onSaveLayout}
          onAutoLayout={onAutoLayout}
          onReload={() => window.location.reload()}
          types={types}
          onFilterType={setTypeFilter}
          onSearch={onSearch}
        />
        <Canvas
          nodes={filteredNodes}
          edges={edges}
          nodeTypes={nodeTypes}
          onNodesChange={onNodesChange}
          onEdgesChange={onEdgesChange}
          onConnect={onConnect}
          onNodeClick={setSelNode}
          onNodeContextMenu={onNodeContextMenu}
          onDropCreate={(type) => onCreateNode(type)}
        />
        {/* Context menu overlay */}
        <ContextMenu pos={ctxMenu} onPickDepth={onPickDepth} onClose={() => setCtxMenu(null)} />
      </div>
      <div className="cmdb-right">
        <Inspector sel={selNode} onUpdate={onUpdateNode} onDelete={onDeleteNode} />
      </div>
    </div>
  );
}
