import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { useEdgesState, useNodesState } from 'reactflow';
import * as api from '../api';
import Toolbar from './Toolbar';
import Palette from './Palette';
import Canvas from './Canvas';
import NodeCard from './NodeCard.jsx';

const nodeTypes = { taskCard: NodeCard };

const toNode = (n, onDurationChange) => ({
  id: String(n.id),
  type: 'taskCard',
  data: {
    label: n.task_name,
    duration: n.duration ?? '',
    onDurationChange,
  },
  position: n.position || { x: Math.random() * 400, y: Math.random() * 300 },
});

const formatEdgeLabel = (name, duration) => {
  const cleanName = name ? String(name).trim() : '';
  const durationValue = Number.isFinite(duration) ? duration : null;
  if (cleanName && durationValue !== null) {
    return `${cleanName} (${durationValue})`;
  }
  if (cleanName) return cleanName;
  if (durationValue !== null) return String(durationValue);
  return '';
};

const toEdge = (e) => ({
  id: String(e.id),
  source: String(e.source_node_id),
  target: String(e.target_node_id),
  data: {
    name: e.name ?? '',
    duration: e.duration !== null && e.duration !== undefined ? Number(e.duration) : null,
  },
  label: formatEdgeLabel(e.name ?? '', e.duration !== null && e.duration !== undefined ? Number(e.duration) : null),
});

export default function App() {
  const [tasks, setTasks] = useState([]);
  const [taskFilter, setTaskFilter] = useState('');
  const [workspaces, setWorkspaces] = useState([]);
  const [workspaceId, setWorkspaceId] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [nodes, setNodes, onNodesChange] = useNodesState([]);
  const [edges, setEdges, onEdgesChange] = useEdgesState([]);
  const [selection, setSelection] = useState({ nodes: [], edges: [] });

  const updateNodeDuration = useCallback((id, value) => {
    setNodes((prev) => prev.map((node) => (
      node.id === id
        ? { ...node, data: { ...node.data, duration: value } }
        : node
    )));
  }, [setNodes]);

  const buildNode = useCallback((row) => {
    const id = String(row.id);
    return toNode(row, (value) => updateNodeDuration(id, value));
  }, [updateNodeDuration]);

  const filteredTasks = useMemo(() => {
    if (!taskFilter) return tasks;
    const needle = taskFilter.toLowerCase();
    return tasks.filter((task) => task.toLowerCase().includes(needle));
  }, [tasks, taskFilter]);

  const loadTasks = useCallback(async () => {
    const list = await api.getTasks();
    setTasks(Array.isArray(list) ? list : []);
  }, []);

  const loadWorkspaces = useCallback(async () => {
    const list = await api.getWorkspaces();
    if (Array.isArray(list) && list.length > 0) {
      setWorkspaces(list);
      setWorkspaceId((prev) => prev || String(list[0].id));
      return;
    }
    const created = await api.createWorkspace({ name: 'Default' });
    setWorkspaces(created ? [created] : []);
    setWorkspaceId(created ? String(created.id) : '');
  }, []);

  const loadGraph = useCallback(async (id) => {
    if (!id) return;
    const graph = await api.getGraph(id);
    setNodes((graph.nodes || []).map(buildNode));
    setEdges((graph.edges || []).map(toEdge));
  }, [setNodes, setEdges, buildNode]);

  useEffect(() => {
    setLoading(true);
    setError('');
    Promise.all([loadTasks(), loadWorkspaces()])
      .catch((err) => setError(err?.message || 'Unable to load task dependencies.'))
      .finally(() => setLoading(false));
  }, [loadTasks, loadWorkspaces]);

  useEffect(() => {
    if (!workspaceId) return;
    setLoading(true);
    setError('');
    loadGraph(workspaceId)
      .catch((err) => setError(err?.message || 'Unable to load graph.'))
      .finally(() => setLoading(false));
  }, [workspaceId, loadGraph]);

  const onCreateWorkspace = useCallback(async (name) => {
    const created = await api.createWorkspace({ name });
    if (created) {
      setWorkspaces((prev) => prev.concat(created));
      setWorkspaceId(String(created.id));
    }
  }, []);

  const onCreateNode = useCallback(async (taskName, position = null) => {
    if (!workspaceId || !taskName) return;
    const payload = { workspaceId, taskName };
    if (position) payload.position = position;
    const res = await api.createNode(payload);
    if (!res?.id) return;
    const nextNode = buildNode(res);
    setNodes((prev) => {
      const existing = prev.find((n) => n.id === nextNode.id);
      if (existing) {
        return prev.map((n) => (
          n.id === nextNode.id
            ? { ...n, position: nextNode.position, data: { ...n.data, onDurationChange: nextNode.data.onDurationChange } }
            : n
        ));
      }
      return prev.concat(nextNode);
    });
  }, [workspaceId, setNodes, buildNode]);

  const onConnect = useCallback(async ({ source, target }) => {
    if (!workspaceId || !source || !target) return;
    const res = await api.createEdge({ workspaceId, sourceId: source, targetId: target });
    if (!res?.id) return;
    setEdges((prev) => prev.concat({
      id: String(res.id),
      source,
      target,
      data: { name: res.name ?? '', duration: res.duration ?? null },
      label: formatEdgeLabel(res.name ?? '', res.duration ?? null),
    }));
  }, [workspaceId, setEdges]);

  const onEditEdge = useCallback(async (edge) => {
    if (!edge) return;
    const currentName = edge.data?.name ?? '';
    const currentDuration = edge.data?.duration ?? '';
    const name = window.prompt('Dependency name:', currentName);
    if (name === null) return;
    const durationInput = window.prompt('Duration (number):', currentDuration === null ? '' : String(currentDuration));
    if (durationInput === null) return;
    const duration = durationInput === '' ? null : Number(durationInput);
    const nextDuration = Number.isFinite(duration) ? duration : null;
    await api.updateEdge(edge.id, { name, duration: nextDuration });
    setEdges((prev) => prev.map((item) => (
      item.id === edge.id
        ? {
            ...item,
            data: { name, duration: nextDuration },
            label: formatEdgeLabel(name, nextDuration),
          }
        : item
    )));
  }, [setEdges]);

  const onNodeDragStop = useCallback(async (_event, node) => {
    if (!node?.id) return;
    await api.updateNode(node.id, { position: node.position });
  }, []);

  const onDeleteSelected = useCallback(async () => {
    if (!selection.nodes.length && !selection.edges.length) return;
    await Promise.all(selection.edges.map((edge) => api.deleteEdge(edge.id)));
    await Promise.all(selection.nodes.map((node) => api.deleteNode(node.id)));
    setEdges((prev) => prev.filter((edge) => !selection.edges.some((sel) => sel.id === edge.id)));
    setNodes((prev) => prev.filter((node) => !selection.nodes.some((sel) => sel.id === node.id)));
    setSelection({ nodes: [], edges: [] });
  }, [selection, setEdges, setNodes]);

  const onSaveLayout = useCallback(async () => {
    if (!workspaceId) return;
    const payload = nodes.map((node) => ({
      id: node.id,
      position: node.position,
    }));
    await api.saveLayout(workspaceId, payload);
  }, [workspaceId, nodes]);

  return (
    <div className="taskdep-wrap">
      <Palette
        tasks={filteredTasks}
        filter={taskFilter}
        onFilterChange={setTaskFilter}
        onCreate={onCreateNode}
      />
      <div className="taskdep-canvas">
        <Toolbar
          workspaces={workspaces}
          workspaceId={workspaceId}
          onWorkspaceChange={setWorkspaceId}
          onCreateWorkspace={onCreateWorkspace}
          onSave={onSaveLayout}
          onReload={() => loadGraph(workspaceId)}
          onDeleteSelected={onDeleteSelected}
        />
        {error && <div className="alert alert-warning m-2">{error}</div>}
        {loading && <div className="text-muted m-2">Loading…</div>}
        {!loading && (
          <Canvas
            nodes={nodes}
            edges={edges}
            nodeTypes={nodeTypes}
            onNodesChange={onNodesChange}
            onEdgesChange={onEdgesChange}
            onConnect={onConnect}
            onNodeClick={() => {}}
            onEdgeClick={onEditEdge}
            onNodeDragStop={onNodeDragStop}
            onSelectionChange={setSelection}
            onDropCreate={onCreateNode}
          />
        )}
      </div>
    </div>
  );
}
