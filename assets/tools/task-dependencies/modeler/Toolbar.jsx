import React, { useState } from 'react';

export default function Toolbar({
  workspaces,
  workspaceId,
  onWorkspaceChange,
  onCreateWorkspace,
  onRefreshWorkspaces,
  onSave,
  onReload,
  onDeleteSelected,
}) {
  const [name, setName] = useState('');

  return (
    <div className="taskdep-toolbar">
      <select
        className="form-select form-select-sm w-auto"
        value={workspaceId}
        onChange={(event) => onWorkspaceChange(event.target.value)}
        onFocus={onRefreshWorkspaces}
      >
        {workspaces.map((ws) => (
          <option key={ws.id} value={ws.id}>{ws.name}</option>
        ))}
      </select>
      <input
        className="form-control form-control-sm w-auto"
        placeholder="New workspace"
        value={name}
        onChange={(event) => setName(event.target.value)}
      />
      <button
        type="button"
        className="btn btn-sm btn-outline-primary"
        onClick={() => {
          const trimmed = name.trim();
          if (!trimmed) return;
          onCreateWorkspace(trimmed);
          setName('');
        }}
      >
        Add workspace
      </button>
      <button
        type="button"
        className="btn btn-sm btn-primary"
        onClick={onSave}
      >
        Save
      </button>
      <button
        type="button"
        className="btn btn-sm btn-outline-secondary"
        onClick={onReload}
      >
        Reload
      </button>
      <button
        type="button"
        className="btn btn-sm btn-outline-danger"
        onClick={onDeleteSelected}
      >
        Delete selected
      </button>
    </div>
  );
}
