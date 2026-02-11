import React, { useCallback, useEffect, useMemo, useState } from 'react';

const buildTree = (items) => {
  const byId = new Map();
  items.forEach((item) => {
    byId.set(item.id, { ...item, children: [] });
  });
  const roots = [];
  byId.forEach((item) => {
    const parentId = item.parent_id;
    if (parentId && byId.has(parentId)) {
      byId.get(parentId).children.push(item);
    } else {
      roots.push(item);
    }
  });
  const sortNodes = (nodes) => {
    nodes.sort((a, b) => {
      if (a.sort_order !== b.sort_order) {
        return (a.sort_order ?? 0) - (b.sort_order ?? 0);
      }
      return a.id - b.id;
    });
    nodes.forEach((node) => sortNodes(node.children));
  };
  sortNodes(roots);
  return roots;
};

const flattenTree = (nodes, level = 0, output = []) => {
  nodes.forEach((node) => {
    output.push({ ...node, level });
    if (node.children.length > 0) {
      flattenTree(node.children, level + 1, output);
    }
  });
  return output;
};

const buildDescendants = (nodes) => {
  const map = new Map();
  const visit = (node) => {
    const descendants = new Set();
    node.children.forEach((child) => {
      descendants.add(child.id);
      const childDesc = visit(child);
      childDesc.forEach((id) => descendants.add(id));
    });
    map.set(node.id, descendants);
    return descendants;
  };
  nodes.forEach((node) => visit(node));
  return map;
};

const TaskManager = () => {
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [newTaskName, setNewTaskName] = useState('');
  const [editingId, setEditingId] = useState(null);
  const [editingName, setEditingName] = useState('');
  const [dragId, setDragId] = useState(null);
  const [dragOverId, setDragOverId] = useState(null);

  const fetchItems = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const response = await fetch('/api/smartsheet/task-manager');
      const payload = await response.json();
      if (!response.ok) {
        throw new Error(payload?.error || 'Unable to load tasks.');
      }
      setItems(Array.isArray(payload?.items) ? payload.items : []);
    } catch (err) {
      setError(err?.message || 'Unable to load tasks.');
      setItems([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchItems();
  }, [fetchItems]);

  const tree = useMemo(() => buildTree(items), [items]);
  const flatItems = useMemo(() => flattenTree(tree), [tree]);
  const descendantsMap = useMemo(() => buildDescendants(tree), [tree]);

  const siblingsByParent = useMemo(() => {
    const map = new Map();
    items.forEach((item) => {
      const parentKey = item.parent_id ?? null;
      if (!map.has(parentKey)) map.set(parentKey, []);
      map.get(parentKey).push(item);
    });
    map.forEach((list) => {
      list.sort((a, b) => {
        if (a.sort_order !== b.sort_order) return (a.sort_order ?? 0) - (b.sort_order ?? 0);
        return a.id - b.id;
      });
    });
    return map;
  }, [items]);

  const nextSortOrder = useCallback((parentId) => {
    const list = siblingsByParent.get(parentId ?? null) || [];
    if (list.length === 0) return 0;
    const maxSort = Math.max(...list.map((item) => item.sort_order ?? 0));
    return maxSort + 1;
  }, [siblingsByParent]);

  const createTask = useCallback(async (taskName, parentId = null) => {
    const response = await fetch('/api/smartsheet/task-manager/task', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ taskName, parentId }),
    });
    const payload = await response.json();
    if (!response.ok) {
      throw new Error(payload?.error || 'Unable to create task.');
    }
    return payload;
  }, []);

  const updateTask = useCallback(async (id, updates) => {
    const response = await fetch(`/api/smartsheet/task-manager/task/${id}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(updates),
    });
    const payload = await response.json();
    if (!response.ok) {
      throw new Error(payload?.error || 'Unable to update task.');
    }
    return payload;
  }, []);

  const deleteTask = useCallback(async (id) => {
    const response = await fetch(`/api/smartsheet/task-manager/task/${id}`, { method: 'DELETE' });
    const payload = await response.json();
    if (!response.ok) {
      throw new Error(payload?.error || 'Unable to delete task.');
    }
    return payload;
  }, []);

  const handleAddRoot = async () => {
    const name = newTaskName.trim();
    if (!name) return;
    try {
      await createTask(name, null);
      setNewTaskName('');
      fetchItems();
    } catch (err) {
      setError(err?.message || 'Unable to add task.');
    }
  };

  const handleAddChild = async (parentId) => {
    const name = window.prompt('New task name');
    if (!name) return;
    try {
      await createTask(name.trim(), parentId);
      fetchItems();
    } catch (err) {
      setError(err?.message || 'Unable to add child task.');
    }
  };

  const startEdit = (item) => {
    setEditingId(item.id);
    setEditingName(item.task_name);
  };

  const cancelEdit = () => {
    setEditingId(null);
    setEditingName('');
  };

  const saveEdit = async (item) => {
    const name = editingName.trim();
    if (!name) return;
    try {
      await updateTask(item.id, { taskName: name });
      cancelEdit();
      fetchItems();
    } catch (err) {
      setError(err?.message || 'Unable to rename task.');
    }
  };

  const moveTask = async (id, parentId) => {
    if (id === parentId) return;
    const descendants = descendantsMap.get(id);
    if (descendants && descendants.has(parentId)) return;
    try {
      await updateTask(id, { parentId, sortOrder: nextSortOrder(parentId) });
      fetchItems();
    } catch (err) {
      setError(err?.message || 'Unable to move task.');
    }
  };

  const moveSibling = async (item, direction) => {
    const parentKey = item.parent_id ?? null;
    const siblings = siblingsByParent.get(parentKey) || [];
    const index = siblings.findIndex((sibling) => sibling.id === item.id);
    if (index < 0) return;
    const swapIndex = direction === 'up' ? index - 1 : index + 1;
    if (swapIndex < 0 || swapIndex >= siblings.length) return;
    const current = siblings[index];
    const target = siblings[swapIndex];
    try {
      await Promise.all([
        updateTask(current.id, { sortOrder: target.sort_order ?? 0 }),
        updateTask(target.id, { sortOrder: current.sort_order ?? 0 }),
      ]);
      fetchItems();
    } catch (err) {
      setError(err?.message || 'Unable to reorder task.');
    }
  };

  const handleDelete = async (item) => {
    if (!window.confirm(`Delete "${item.task_name}" and all children?`)) return;
    try {
      await deleteTask(item.id);
      fetchItems();
    } catch (err) {
      setError(err?.message || 'Unable to delete task.');
    }
  };

  const handleDropOnRoot = async (event) => {
    event.preventDefault();
    if (!dragId) return;
    await moveTask(dragId, null);
    setDragId(null);
    setDragOverId(null);
  };

  const handleDropOnItem = async (event, itemId) => {
    event.preventDefault();
    if (!dragId) return;
    await moveTask(dragId, itemId);
    setDragId(null);
    setDragOverId(null);
  };

  return (
    <div className="taskmgr">
      <div className="taskmgr__toolbar">
        <div className="taskmgr__field">
          <label className="form-label">New root task</label>
          <div className="input-group input-group-sm">
            <input
              type="text"
              className="form-control"
              value={newTaskName}
              onChange={(event) => setNewTaskName(event.target.value)}
              placeholder="Task name"
            />
            <button type="button" className="btn btn-primary" onClick={handleAddRoot}>
              Add
            </button>
          </div>
        </div>
        <button type="button" className="btn btn-outline-secondary btn-sm" onClick={fetchItems}>
          Refresh
        </button>
      </div>

      {error && <div className="alert alert-warning">{error}</div>}
      {loading && <div className="text-muted">Loading tasks…</div>}

      {!loading && (
        <div
          className={`taskmgr__list ${dragOverId === 'root' ? 'is-drag-over' : ''}`}
          onDragOver={(event) => event.preventDefault()}
          onDrop={handleDropOnRoot}
          onDragEnter={() => setDragOverId('root')}
          onDragLeave={() => setDragOverId(null)}
        >
          {flatItems.length === 0 && <div className="text-muted">No tasks found.</div>}
          {flatItems.map((item) => (
            <div
              key={item.id}
              className={`taskmgr__row ${dragOverId === item.id ? 'is-drag-over' : ''}`}
              style={{ paddingLeft: `${item.level * 18 + 8}px` }}
              draggable
              onDragStart={() => setDragId(item.id)}
              onDragOver={(event) => event.preventDefault()}
              onDragEnter={() => setDragOverId(item.id)}
              onDragLeave={() => setDragOverId(null)}
              onDrop={(event) => handleDropOnItem(event, item.id)}
            >
              <div className="taskmgr__title">
                {editingId === item.id ? (
                  <input
                    type="text"
                    className="form-control form-control-sm"
                    value={editingName}
                    onChange={(event) => setEditingName(event.target.value)}
                  />
                ) : (
                  <span>{item.task_name}</span>
                )}
              </div>
              <div className="taskmgr__actions">
                {editingId === item.id ? (
                  <>
                    <button type="button" className="btn btn-sm btn-success" onClick={() => saveEdit(item)}>
                      Save
                    </button>
                    <button type="button" className="btn btn-sm btn-outline-secondary" onClick={cancelEdit}>
                      Cancel
                    </button>
                  </>
                ) : (
                  <>
                    <button type="button" className="btn btn-sm btn-outline-primary" onClick={() => handleAddChild(item.id)}>
                      Add child
                    </button>
                    <button type="button" className="btn btn-sm btn-outline-secondary" onClick={() => startEdit(item)}>
                      Rename
                    </button>
                    <button type="button" className="btn btn-sm btn-outline-secondary" onClick={() => moveSibling(item, 'up')}>
                      Up
                    </button>
                    <button type="button" className="btn btn-sm btn-outline-secondary" onClick={() => moveSibling(item, 'down')}>
                      Down
                    </button>
                    <button type="button" className="btn btn-sm btn-outline-danger" onClick={() => handleDelete(item)}>
                      Delete
                    </button>
                  </>
                )}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
};

export default TaskManager;
