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
  const [workspaceName, setWorkspaceName] = useState('');
  const [workspaceSaveStatus, setWorkspaceSaveStatus] = useState('idle');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [nodes, setNodes, onNodesChange] = useNodesState([]);
  const [edges, setEdges, onEdgesChange] = useEdgesState([]);
  const [selection, setSelection] = useState({ nodes: [], edges: [] });
  const [edgeDialog, setEdgeDialog] = useState(null);

  const updateNodeDuration = useCallback(async (id, value) => {
    setNodes((prev) => prev.map((node) => (
      node.id === id
        ? { ...node, data: { ...node.data, duration: value } }
        : node
    )));
    await api.updateNode(id, { duration: value });
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

  useEffect(() => {
    const selected = workspaces.find((ws) => String(ws.id) === String(workspaceId));
    setWorkspaceName(selected?.name || '');
  }, [workspaceId, workspaces]);

  const onCreateWorkspace = useCallback(async (name) => {
    const created = await api.createWorkspace({ name });
    if (created) {
      setWorkspaces((prev) => prev.concat(created));
      setWorkspaceId(String(created.id));
    }
  }, []);

  const onSaveWorkspace = useCallback(async () => {
    if (!workspaceId) return;
    const trimmed = workspaceName.trim();
    if (!trimmed) return;
    setWorkspaceSaveStatus('saving');
    try {
      const updated = await api.updateWorkspace(workspaceId, { name: trimmed });
      setWorkspaces((prev) => prev.map((ws) => (
        String(ws.id) === String(workspaceId)
          ? { ...ws, name: updated?.name ?? trimmed }
          : ws
      )));
      setWorkspaceSaveStatus('saved');
      setTimeout(() => setWorkspaceSaveStatus('idle'), 1500);
    } catch (err) {
      setWorkspaceSaveStatus('idle');
      setError(err?.message || 'Unable to save workspace.');
    }
  }, [workspaceId, workspaceName]);

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

  const onEditEdge = useCallback((edge) => {
    if (!edge) return;
    setEdgeDialog({
      id: edge.id,
      name: edge.data?.name ?? '',
      duration: edge.data?.duration ?? '',
    });
  }, []);

  const saveEdgeDialog = useCallback(async () => {
    if (!edgeDialog) return;
    const name = edgeDialog.name ?? '';
    const durationInput = edgeDialog.duration;
    const duration = durationInput === '' ? null : Number(durationInput);
    const nextDuration = Number.isFinite(duration) ? duration : null;
    await api.updateEdge(edgeDialog.id, { name, duration: nextDuration });
    setEdges((prev) => prev.map((item) => (
      item.id === edgeDialog.id
        ? {
            ...item,
            data: { name, duration: nextDuration },
            label: formatEdgeLabel(name, nextDuration),
          }
        : item
    )));
    setEdgeDialog(null);
  }, [edgeDialog, setEdges]);

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
      duration: node.data?.duration ?? null,
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
          onRefreshWorkspaces={loadWorkspaces}
          workspaceName={workspaceName}
          onWorkspaceNameChange={setWorkspaceName}
          onSaveWorkspace={onSaveWorkspace}
          saveStatus={workspaceSaveStatus}
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
      {edgeDialog && (
        <div className="taskdep-dialog-backdrop" onClick={() => setEdgeDialog(null)}>
          <div className="taskdep-dialog" onClick={(event) => event.stopPropagation()}>
            <div className="taskdep-dialog__title">Edit dependency</div>
            <div className="mb-2">
              <label className="form-label">Name</label>
              <input
                type="text"
                className="form-control"
                value={edgeDialog.name}
                onChange={(event) => setEdgeDialog((prev) => ({ ...prev, name: event.target.value }))}
              />
            </div>
            <div>
              <label className="form-label">Duration</label>
              <input
                type="number"
                className="form-control taskdep-input-no-spin"
                min="0"
                step="1"
                value={edgeDialog.duration}
                onChange={(event) => setEdgeDialog((prev) => ({ ...prev, duration: event.target.value }))}
              />
            </div>
            <div className="taskdep-dialog__actions">
              <button type="button" className="btn btn-outline-secondary" onClick={() => setEdgeDialog(null)}>
                Cancel
              </button>
              <button type="button" className="btn btn-primary" onClick={saveEdgeDialog}>
                Save
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
