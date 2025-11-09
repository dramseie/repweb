import React from 'react';
import { PsrApi, ProjectDetailDTO, ProjectRow, TaskNode, TaskProgressEntry } from './api';
import { ProgressBar } from './widgets';
import { RAG, WEATHER_ICON } from './constants';

const weatherLabels = ['', 'Stormy', 'Rainy', 'Cloudy', 'Clear', 'Sunny'];
const ragLabels = ['Gray', 'Green', 'Amber', 'Red'];
const ragColors = ['#9ca3af', '#16a34a', '#f59e0b', '#dc2626'];

const formatDateTime = (value: string) => {
  if (!value) return '—';
  const iso = value.includes('T') ? value : value.replace(' ', 'T');
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString('en-US', { dateStyle: 'short', timeStyle: 'short' });
};

export default function ProjectDetail({ projectId, readOnly = false }: { projectId: string; readOnly?: boolean }) {
  const [data, setData] = React.useState<ProjectDetailDTO | null>(null);
  const [snapOpen, setSnapOpen] = React.useState(false);
  const [label, setLabel] = React.useState('');
  const [note, setNote] = React.useState('');

  React.useEffect(() => {
    PsrApi.getProject(projectId).then(setData).catch(console.error);
  }, [projectId]);

  if (!data) {
  return <div className="psr-loading">Loading...</div>;
  }

  const refresh = () => PsrApi.getProject(projectId).then(setData).catch(console.error);

  const setProject = (patch: Partial<ProjectRow>) => {
    PsrApi.upsertProject(data.id, patch)
      .then((updated) => setData({ ...data, ...updated }))
      .catch(console.error);
  };

  return (
    <div className="psr-detail">
      <section className="psr-project-hero">
        <div className="psr-project-hero__top">
          <div>
            <span className="psr-eyebrow">Initiative</span>
            <h1>{data.name}</h1>
            <p className="psr-updated">Last updated · {formatDateTime(data.updatedAt)}</p>
          </div>
          {!readOnly && (
            <button className="psr-button psr-button--primary" onClick={() => setSnapOpen(true)}>
              Publish Snapshot
            </button>
          )}
        </div>

        <div className="psr-hero-controls">
          <div className="psr-control">
            <label>Weather</label>
            {!readOnly ? (
              <select
                value={data.weatherTrend}
                onChange={(event) => setProject({ weatherTrend: Number(event.target.value) as any })}
                className="psr-select"
              >
                {[1, 2, 3, 4, 5].map((value) => (
                  <option key={value} value={value}>
                    {WEATHER_ICON[value]} {weatherLabels[value]}
                  </option>
                ))}
              </select>
            ) : (
              <span className="psr-badge psr-badge--glass">
                {WEATHER_ICON[data.weatherTrend]} {weatherLabels[data.weatherTrend]}
              </span>
            )}
          </div>

          <div className="psr-control">
            <label>RAG</label>
            {!readOnly ? (
              <select
                value={data.ragOverall}
                onChange={(event) => setProject({ ragOverall: Number(event.target.value) as any })}
                className="psr-select"
              >
                <option value={RAG.gray}>Gray</option>
                <option value={RAG.green}>Green</option>
                <option value={RAG.amber}>Amber</option>
                <option value={RAG.red}>Red</option>
              </select>
            ) : (
              <span className="psr-badge" style={{ backgroundColor: ragColors[data.ragOverall] }}>
                {ragLabels[data.ragOverall]}
              </span>
            )}
          </div>

          <div className="psr-control psr-control--progress">
            <label>Progress</label>
            <div className="psr-progress-control">
              {!readOnly && (
                <input
                  type="range"
                  min={0}
                  max={100}
                  value={data.progressPct}
                  onChange={(event) => setProject({ progressPct: Number(event.target.value) })}
                />
              )}
              <div className="psr-progress-meter">
                <ProgressBar value={data.progressPct} />
              </div>
              <span>{data.progressPct}%</span>
            </div>
          </div>
        </div>

        <div className="psr-project-hero__description">
          <label>Description</label>
          {!readOnly ? (
            <textarea
              rows={3}
              defaultValue={data.description || ''}
              onBlur={(event) => setProject({ description: event.target.value })}
              placeholder="Describe the scope, risks, impact..."
              className="psr-textarea"
            />
          ) : (
            <p className="psr-description-text">
              {data.description || <span className="psr-empty">No description saved.</span>}
            </p>
          )}
        </div>
      </section>

      <TaskTree projectId={data.id} nodes={data.tasks} onChanged={refresh} readOnly={readOnly} />

      {!readOnly && snapOpen && (
        <div className="psr-modal">
          <div className="psr-modal__panel psr-modal__panel--wide">
            <header className="psr-modal__header">
              <div>
                <h2>Publish Snapshot</h2>
                <p>Record a milestone to capture the project's current state.</p>
              </div>
              <button type="button" className="psr-modal__close" onClick={() => setSnapOpen(false)} aria-label="Close">
                ×
              </button>
            </header>
            <div className="psr-modal__body psr-modal__body--stack">
              <label className="psr-field">
                <span>Label</span>
                <input
                  className="psr-input"
                  placeholder="Ex. 2025-W42"
                  value={label}
                  onChange={(event) => setLabel(event.target.value)}
                />
              </label>
              <label className="psr-field">
                <span>Note</span>
                <textarea
                  className="psr-textarea"
                  rows={4}
                  placeholder="Observations, risks, decisions... (optional)"
                  value={note}
                  onChange={(event) => setNote(event.target.value)}
                />
              </label>
            </div>
            <footer className="psr-modal__footer">
              <button className="psr-button psr-button--ghost" onClick={() => setSnapOpen(false)}>
                Cancel
              </button>
              <button
                className="psr-button psr-button--primary"
                onClick={() => {
                  PsrApi.takeSnapshot({ label: label || undefined, note: note || undefined })
                    .then(() => {
                      setSnapOpen(false);
                      setLabel('');
                      setNote('');
                    })
                    .catch(console.error);
                }}
              >
                  Publish
              </button>
            </footer>
          </div>
        </div>
      )}
    </div>
  );
}

function TaskTree({ projectId, nodes, onChanged, readOnly = false }: { projectId: string; nodes: TaskNode[]; onChanged: () => void; readOnly?: boolean }) {
  const [expanded, setExpanded] = React.useState<Record<string, boolean>>({});
  const toggle = (id: string) => setExpanded((state) => ({ ...state, [id]: !state[id] }));

  return (
    <section className="psr-task-section">
      <header className="psr-section-header">
        <div>
          <h2>Work Breakdown</h2>
          <p>Track the status of every milestone and capture detailed notes.</p>
        </div>
        {!readOnly && (
          <button
            className="psr-button psr-button--soft"
            onClick={() => PsrApi.createTask({ projectId, name: 'New Task', rag: 1, progressPct: 0 }).then(onChanged)}
          >
            + Add Task
          </button>
        )}
      </header>

      <div className="psr-task-list">
        {nodes.length === 0 && (
          <div className="psr-empty-state">
            <p>No tasks defined yet.</p>
            {!readOnly && <span>Add your first milestones to shape execution.</span>}
          </div>
        )}
        {nodes.map((node) => (
          <TaskRow key={node.id} node={node} depth={0} expanded={expanded} toggle={toggle} onChanged={onChanged} readOnly={readOnly} />
        ))}
      </div>
    </section>
  );
}

function TaskRow({ node, depth, expanded, toggle, onChanged, readOnly = false }: { node: TaskNode; depth: number; expanded: Record<string, boolean>; toggle: (id: string) => void; onChanged: () => void; readOnly?: boolean }) {
  const hasChildren = (node.children?.length || 0) > 0;
  const update = (patch: Partial<TaskNode>) => PsrApi.updateTask(node.id, patch).then(onChanged);
  const del = () => {
    if (confirm('Delete task and its subtasks?')) {
      PsrApi.deleteTask(node.id).then(onChanged);
    }
  };
  const [logOpen, setLogOpen] = React.useState(false);
  const logCount = node.progressLog?.length ?? 0;
  const appendLog = React.useCallback(
    async (note: string, progress: number) => {
      await PsrApi.addTaskProgressLog(node.id, { note, progressPct: progress });
      onChanged();
    },
    [node.id, onChanged]
  );

  return (
    <>
      <div className="psr-task-card" style={{ marginLeft: depth * 22 }}>
        <div className="psr-task-card__header">
          <div className="psr-task-card__title">
            {hasChildren ? (
              <button
                type="button"
                className="psr-toggle"
                onClick={() => toggle(node.id)}
                aria-label={expanded[node.id] ? 'Collapse section' : 'Expand section'}
              >
                {expanded[node.id] ? '▾' : '▸'}
              </button>
            ) : (
              <span className="psr-toggle psr-toggle--placeholder">•</span>
            )}
            {!readOnly ? (
              <input
                className="psr-input psr-input--title"
                defaultValue={node.name}
                onBlur={(event) => update({ name: event.target.value })}
              />
            ) : (
              <h3>{node.name}</h3>
            )}
          </div>
          <div className="psr-task-card__badge">
            <span className="psr-badge" style={{ backgroundColor: ragColors[node.rag] }}>
              {ragLabels[node.rag]}
            </span>
          </div>
        </div>

        <div className="psr-task-card__grid">
          <label className="psr-field psr-field--compact">
            <span>WBS</span>
            {!readOnly ? (
              <input
                className="psr-input"
                defaultValue={node.wbsCode || ''}
                onBlur={(event) => update({ wbsCode: event.target.value || null })}
              />
            ) : (
              <p>{node.wbsCode || '—'}</p>
            )}
          </label>
          <label className="psr-field psr-field--compact">
            <span>Progress</span>
            <div className="psr-progress-inline">
              {!readOnly && (
                <input
                  type="range"
                  min={0}
                  max={100}
                  defaultValue={node.progressPct}
                  onChange={(event) => update({ progressPct: Number(event.target.value) })}
                />
              )}
              <span>{node.progressPct}%</span>
            </div>
          </label>
          <label className="psr-field psr-field--compact">
            <span>Start</span>
            {!readOnly ? (
              <input
                type="date"
                className="psr-input"
                defaultValue={node.startDate || ''}
                onBlur={(event) => update({ startDate: event.target.value || null })}
              />
            ) : (
              <p>{node.startDate || '—'}</p>
            )}
          </label>
          <label className="psr-field psr-field--compact">
            <span>Due</span>
            {!readOnly ? (
              <input
                type="date"
                className="psr-input"
                defaultValue={node.dueDate || ''}
                onBlur={(event) => update({ dueDate: event.target.value || null })}
              />
            ) : (
              <p>{node.dueDate || '—'}</p>
            )}
          </label>
        </div>

        <div className="psr-task-card__footer">
          <button type="button" className="psr-button psr-button--link" onClick={() => setLogOpen(true)}>
            Protocol{logCount ? ` (${logCount})` : ''}
          </button>
          {!readOnly && (
            <button type="button" className="psr-button psr-button--danger" onClick={del}>
              Delete
            </button>
          )}
        </div>
      </div>

      {logOpen && (
        <TaskProgressDialog task={node} readOnly={readOnly} onClose={() => setLogOpen(false)} onAdd={appendLog} />
      )}

      {hasChildren && expanded[node.id] &&
        node.children!.map((child) => (
          <TaskRow key={child.id} node={child} depth={depth + 1} expanded={expanded} toggle={toggle} onChanged={onChanged} readOnly={readOnly} />
        ))}
    </>
  );
}

function TaskProgressDialog({ task, onClose, onAdd, readOnly }: { task: TaskNode; onClose: () => void; onAdd: (note: string, progress: number) => Promise<void>; readOnly: boolean }) {
  const entries = task.progressLog ?? [];
  const [note, setNote] = React.useState('');
  const [progress, setProgress] = React.useState(task.progressPct);
  const [submitting, setSubmitting] = React.useState(false);
  const [error, setError] = React.useState<string | null>(null);

  React.useEffect(() => {
    setProgress(task.progressPct);
  }, [task.id, task.progressPct]);

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    const trimmed = note.trim();
    if (!trimmed) {
      setError('Please enter a description.');
      return;
    }
    setSubmitting(true);
    setError(null);
    try {
      await onAdd(trimmed, progress);
      setNote('');
    } catch (err) {
      console.error(err);
  setError(err instanceof Error ? err.message : 'Unable to save the note.');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="psr-modal">
      <div className="psr-modal__panel">
        <header className="psr-modal__header psr-modal__header--accent">
          <div>
            <span className="psr-eyebrow">Protocol</span>
            <h2>{task.name}</h2>
          </div>
          <button type="button" className="psr-modal__close" onClick={onClose} aria-label="Close">
            ×
          </button>
        </header>

        <div className="psr-modal__body">
          <div className="psr-progress-log">
            {entries.length ? (
              <ul>
                {entries.map((entry: TaskProgressEntry, index) => (
                  <li key={entry.id} className="psr-progress-log__entry">
                    <div className="psr-progress-log__marker">
                      <span>{entry.progressPct}%</span>
                      {index < entries.length - 1 && <span className="psr-progress-log__line" aria-hidden="true" />}
                    </div>
                    <div className="psr-progress-log__content">
                      <time>{formatDateTime(entry.createdAt)}</time>
                      <p>{entry.note}</p>
                    </div>
                  </li>
                ))}
              </ul>
            ) : (
              <div className="psr-empty-log">No notes recorded for this task.</div>
            )}
          </div>

          {!readOnly && (
            <form className="psr-progress-form" onSubmit={handleSubmit}>
              <label className="psr-field">
                <span>Progress (%)</span>
                <input
                  type="number"
                  min={0}
                  max={100}
                  className="psr-input"
                  value={progress}
                  onChange={(event) => setProgress(Math.max(0, Math.min(100, Number(event.target.value) || 0)))}
                  disabled={submitting}
                />
              </label>
              <label className="psr-field">
                <span>Description</span>
                <textarea
                  className="psr-textarea"
                  rows={3}
                  value={note}
                  onChange={(event) => setNote(event.target.value)}
                  placeholder="Describe progress, blockers, next steps..."
                  disabled={submitting}
                />
              </label>
              {error && <div className="psr-inline-alert">{error}</div>}
              <div className="psr-modal__footer">
                <button type="button" className="psr-button psr-button--ghost" onClick={onClose} disabled={submitting}>
                  Close
                </button>
                <button type="submit" className="psr-button psr-button--primary" disabled={submitting || !note.trim()}>
                  Add
                </button>
              </div>
            </form>
          )}
        </div>
      </div>
    </div>
  );
}
