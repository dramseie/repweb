import React from 'react';

export default function Palette({ tasks, filter, onFilterChange, onCreate }) {
  return (
    <div className="taskdep-panel">
      <div className="taskdep-title mb-2">Tasks</div>
      <input
        className="form-control form-control-sm mb-2"
        placeholder="Filter tasks"
        value={filter}
        onChange={(event) => onFilterChange(event.target.value)}
      />
      <div className="taskdep-list">
        {tasks.length === 0 && (
          <div className="text-muted small">No tasks found.</div>
        )}
        {tasks.map((task) => (
          <div
            key={task}
            className="taskdep-item"
            draggable
            onDragStart={(event) => {
              event.dataTransfer.setData('application/x-task-name', task);
              event.dataTransfer.effectAllowed = 'copy';
            }}
          >
            <span>{task}</span>
            <button
              type="button"
              className="btn btn-sm btn-outline-primary"
              onClick={() => onCreate(task)}
            >
              Add
            </button>
          </div>
        ))}
      </div>
    </div>
  );
}
