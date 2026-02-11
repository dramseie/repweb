import React, { useState } from 'react';

export default function Toolbar({
  workspaces,
  workspaceId,
  onWorkspaceChange,
  onCreateWorkspace,
  onRefreshWorkspaces,
  workspaceName,
  onWorkspaceNameChange,
  onSaveWorkspace,
  saveStatus,
  onSave,
  onReload,
  onDeleteSelected,
}) {
  const [newName, setNewName] = useState('');

  const saveClass = saveStatus === 'saved'
    ? 'btn btn-sm btn-success'
    : 'btn btn-sm btn-primary';

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
        placeholder="Workspace name"
        value={workspaceName}
        onChange={(event) => onWorkspaceNameChange(event.target.value)}
      />
      <button
        type="button"
        className={saveClass}
        onClick={onSaveWorkspace}
      >
        Save
      </button>
      <input
        className="form-control form-control-sm w-auto"
        placeholder="New workspace"
        value={newName}
        onChange={(event) => setNewName(event.target.value)}
      />
      <button
        type="button"
        className="btn btn-sm btn-outline-primary"
        onClick={() => {
          const trimmed = newName.trim();
          if (!trimmed) return;
          onCreateWorkspace(trimmed);
          setNewName('');
        }}
      >
        Add workspace
      </button>
      <button
        type="button"
        className="btn btn-sm btn-outline-secondary"
        onClick={onSave}
      >
        Save layout
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
