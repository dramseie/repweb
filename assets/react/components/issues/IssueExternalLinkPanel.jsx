import React, { useEffect, useMemo, useState } from 'react';

function IssueExternalLinkPanel({
  issueId,
  linkedProject,
  linkedTask,
  onIssueUpdate,
  pendingLink = null,
  onPendingChange,
}) {
  const [projects, setProjects] = useState([]);
  const [tasks, setTasks] = useState([]);
  const [selectedProjectId, setSelectedProjectId] = useState('');
  const [selectedTaskId, setSelectedTaskId] = useState('');
  const [rowId, setRowId] = useState('');
  const [loadingProjects, setLoadingProjects] = useState(false);
  const [loadingTasks, setLoadingTasks] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [statusMessage, setStatusMessage] = useState(null);
  const [errorMessage, setErrorMessage] = useState(null);

  useEffect(() => {
    let cancelled = false;
    const loadProjects = async () => {
      setLoadingProjects(true);
      try {
        const response = await fetch('/api/smartsheet/projects', {
          credentials: 'same-origin',
          headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
          const message = await extractError(response, `Failed to load Smartsheet projects (status ${response.status}).`);
          throw new Error(message);
        }

        const data = await response.json();
        if (!cancelled) {
          const items = Array.isArray(data.items) ? data.items : [];
          setProjects(items);
        }
      } catch (err) {
        if (!cancelled) {
          setErrorMessage(err instanceof Error ? err.message : 'Unable to load Smartsheet projects.');
        }
      } finally {
        if (!cancelled) {
          setLoadingProjects(false);
        }
      }
    };

    loadProjects();

    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(() => {
    if (isDeferred) {
      if (pendingLink?.projectId) {
        setSelectedProjectId(String(pendingLink.projectId));
      } else {
        setSelectedProjectId('');
      }
      return;
    }

    if (linkedProject?.id) {
      setSelectedProjectId(String(linkedProject.id));
    } else if (!linkedProject) {
      setSelectedProjectId('');
    }
  }, [isDeferred, linkedProject, pendingLink]);

  useEffect(() => {
    if (isDeferred) {
      if (pendingLink?.taskId) {
        setSelectedTaskId(String(pendingLink.taskId));
      } else {
        setSelectedTaskId('');
      }

      if (pendingLink?.smartsheetRowId) {
        setRowId(String(pendingLink.smartsheetRowId));
      } else {
        setRowId('');
      }

      return;
    }

    if (linkedTask?.id) {
      setSelectedTaskId(String(linkedTask.id));
    } else if (!linkedTask) {
      setSelectedTaskId('');
    }

    if (linkedTask?.smartsheetRowId) {
      setRowId(String(linkedTask.smartsheetRowId));
    } else if (!linkedTask) {
      setRowId('');
    }
  }, [isDeferred, linkedTask, pendingLink]);

  useEffect(() => {
    let cancelled = false;

    const loadTasks = async () => {
      if (!selectedProjectId) {
        setTasks([]);
        return;
      }

      setLoadingTasks(true);
      try {
        const response = await fetch(`/api/smartsheet/projects/${selectedProjectId}/tasks`, {
          credentials: 'same-origin',
          headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
          const message = await extractError(response, `Failed to load Smartsheet tasks (status ${response.status}).`);
          throw new Error(message);
        }

        const data = await response.json();
        if (!cancelled) {
          const items = Array.isArray(data.items) ? data.items : [];
          setTasks(items);
        }
      } catch (err) {
        if (!cancelled) {
          setErrorMessage(err instanceof Error ? err.message : 'Unable to load Smartsheet tasks.');
        }
      } finally {
        if (!cancelled) {
          setLoadingTasks(false);
        }
      }
    };

    loadTasks();

    return () => {
      cancelled = true;
    };
  }, [selectedProjectId]);

  const isDeferred = issueId == null;

  const hasDeferredLink = useMemo(() => {
    if (!pendingLink) {
      return false;
    }

    return ['projectId', 'taskId', 'smartsheetRowId'].some((key) => {
      const value = pendingLink[key];
      return typeof value === 'number' && Number.isFinite(value) && value > 0;
    });
  }, [pendingLink]);

  const hasActiveLink = useMemo(() => {
    return isDeferred ? hasDeferredLink : Boolean(linkedProject || linkedTask);
  }, [hasDeferredLink, isDeferred, linkedProject, linkedTask]);

  const handleProjectChange = (event) => {
    setSelectedProjectId(event.target.value);
    setSelectedTaskId('');
    setStatusMessage(null);
    setErrorMessage(null);
    if (isDeferred && typeof onPendingChange === 'function') {
      onPendingChange(null);
    }
  };

  const handleTaskChange = (event) => {
    setSelectedTaskId(event.target.value);
    setStatusMessage(null);
    setErrorMessage(null);
    if (isDeferred && typeof onPendingChange === 'function') {
      onPendingChange(null);
    }
  };

  const handleRowChange = (event) => {
    setRowId(event.target.value);
    setStatusMessage(null);
    setErrorMessage(null);
    if (isDeferred && typeof onPendingChange === 'function') {
      onPendingChange(null);
    }
  };

  const handleLink = async () => {
    const payload = { source: 'smartsheet' };
    const trimmedRow = rowId.trim();

    if (selectedProjectId) {
      payload.projectId = Number(selectedProjectId);
    }

    if (selectedTaskId) {
      payload.taskId = Number(selectedTaskId);
    }

    if (trimmedRow !== '') {
      const parsed = Number(trimmedRow);
      if (!Number.isFinite(parsed) || parsed <= 0) {
        setErrorMessage('Smartsheet row id must be a positive number.');
        return;
      }
      payload.smartsheetRowId = parsed;
    }

    if (payload.projectId === undefined && payload.taskId === undefined && payload.smartsheetRowId === undefined) {
      setErrorMessage('Select a project/task or enter a Smartsheet row id before linking.');
      return;
    }

    if (isDeferred) {
      if (typeof onPendingChange === 'function') {
        onPendingChange({
          projectId: payload.projectId ?? null,
          taskId: payload.taskId ?? null,
          smartsheetRowId: payload.smartsheetRowId ?? null,
        });
      }

      setStatusMessage('External link will be applied after the issue is created.');
      setErrorMessage(null);
      return;
    }

    setSubmitting(true);
    setErrorMessage(null);
    setStatusMessage('Linking issue to Smartsheet source…');

    try {
      const response = await fetch(`/api/issues/${issueId}/external-link`, {
        method: 'PUT',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(payload),
      });

      if (!response.ok) {
        const message = await extractError(response, `Linking failed with status ${response.status}.`);
        throw new Error(message);
      }

      const data = await response.json();
      if (data && typeof data === 'object') {
        setStatusMessage(data.message ?? 'Issue linked successfully.');
        if (data.issue && typeof onIssueUpdate === 'function') {
          onIssueUpdate(data.issue);
        }
      } else {
        setStatusMessage('Issue linked successfully.');
      }
    } catch (err) {
      setErrorMessage(err instanceof Error ? err.message : 'Unable to link issue to Smartsheet source.');
      setStatusMessage(null);
    } finally {
      setSubmitting(false);
    }
  };

  const handleUnlink = async () => {
    if (isDeferred) {
      if (typeof onPendingChange === 'function') {
        onPendingChange(null);
      }

      setStatusMessage('External link will be cleared after the issue is created.');
      setErrorMessage(null);
      setSelectedProjectId('');
      setSelectedTaskId('');
      setRowId('');
      return;
    }

    setSubmitting(true);
    setErrorMessage(null);
    setStatusMessage('Removing external link…');

    try {
      const response = await fetch(`/api/issues/${issueId}/external-link`, {
        method: 'PUT',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ unlink: true }),
      });

      if (!response.ok) {
        const message = await extractError(response, `Unlink failed with status ${response.status}.`);
        throw new Error(message);
      }

      const data = await response.json();
      setStatusMessage(data.message ?? 'External link removed.');
      if (data.issue && typeof onIssueUpdate === 'function') {
        onIssueUpdate(data.issue);
      }
    } catch (err) {
      setErrorMessage(err instanceof Error ? err.message : 'Unable to remove external link.');
      setStatusMessage(null);
    } finally {
      setSubmitting(false);
    }
  };

  const resolveProjectLabel = (projectId) => {
    const numericId = Number(projectId);
    if (!Number.isFinite(numericId) || numericId <= 0) {
      return null;
    }

    const match = projects.find((project) => project?.id === numericId);
    if (match) {
      return match.projectName ?? match.projectCode ?? `Project ${numericId}`;
    }

    return `Project ${numericId}`;
  };

  const resolveTaskLabel = (taskId) => {
    const numericId = Number(taskId);
    if (!Number.isFinite(numericId) || numericId <= 0) {
      return null;
    }

    const match = tasks.find((taskItem) => taskItem?.id === numericId);
    if (match) {
      return match.taskName ?? `Task ${numericId}`;
    }

    return `Task ${numericId}`;
  };

  const currentProjectLabel = isDeferred
    ? (pendingLink?.projectId ? resolveProjectLabel(pendingLink.projectId) : null)
    : (linkedProject ? (linkedProject.projectName ?? linkedProject.projectCode ?? `Project ${linkedProject.id}`) : null);

  const currentTaskLabel = isDeferred
    ? (pendingLink?.taskId ? resolveTaskLabel(pendingLink.taskId) : null)
    : (linkedTask ? (linkedTask.taskName ?? `Task ${linkedTask.id}`) : null);

  const currentRowLabel = isDeferred && !currentTaskLabel && pendingLink?.smartsheetRowId
    ? `Row ${pendingLink.smartsheetRowId}`
    : null;

  const linkButtonLabel = isDeferred ? 'Stage Smartsheet Link' : 'Link Smartsheet Source';
  const unlinkButtonLabel = isDeferred ? 'Clear Pending Link' : 'Remove Link';

  return (
    <div className="mt-4">
      <h2 className="h6 mb-3">External Source Link</h2>
      <div className="card border-0 shadow-sm">
        <div className="card-body">
          <p className="text-muted small mb-3">
            Link this issue to a Smartsheet project plan task to keep work items in sync. Select a project and optionally a task, or provide a Smartsheet row id directly.
          </p>

          <div className="row g-3">
            <div className="col-lg-5">
              <label className="form-label" htmlFor={`smartsheet-project-${issueId}`}>Smartsheet Project</label>
              <select
                id={`smartsheet-project-${issueId}`}
                className="form-select"
                value={selectedProjectId}
                onChange={handleProjectChange}
                disabled={loadingProjects || submitting}
              >
                <option value="">Select a project…</option>
                {projects.map((project) => (
                  <option key={project.id} value={project.id}>
                    {project.projectName ?? project.projectCode ?? `Project ${project.id}`}
                  </option>
                ))}
              </select>
              {loadingProjects && <div className="form-text text-muted">Loading projects…</div>}
            </div>

            <div className="col-lg-4">
              <label className="form-label" htmlFor={`smartsheet-task-${issueId}`}>Smartsheet Task</label>
              <select
                id={`smartsheet-task-${issueId}`}
                className="form-select"
                value={selectedTaskId}
                onChange={handleTaskChange}
                disabled={!selectedProjectId || loadingTasks || submitting}
              >
                <option value="">Select a task…</option>
                {tasks.map((taskItem) => (
                  <option key={taskItem.id} value={taskItem.id}>
                    {taskItem.taskName ?? `Task ${taskItem.id}`}
                  </option>
                ))}
              </select>
              {selectedProjectId && loadingTasks && <div className="form-text text-muted">Loading tasks…</div>}
            </div>

            <div className="col-lg-3">
              <label className="form-label" htmlFor={`smartsheet-row-${issueId}`}>Smartsheet Row ID</label>
              <input
                id={`smartsheet-row-${issueId}`}
                type="number"
                className="form-control"
                value={rowId}
                onChange={handleRowChange}
                min={1}
                step={1}
                disabled={submitting}
                placeholder="Optional"
              />
              <div className="form-text">Use when you have a row id but no task listing.</div>
            </div>
          </div>

          <div className="mt-3 small">
            <span className="fw-semibold">Current link:</span>{' '}
            {hasActiveLink ? (
              <span>
                {currentProjectLabel ?? 'Project not set'}
                {currentTaskLabel ? (
                  <>
                    {' -> '}
                    {currentTaskLabel}
                  </>
                ) : currentRowLabel ? (
                  <>
                    {' -> '}
                    {currentRowLabel}
                  </>
                ) : null}
              </span>
            ) : (
              'None'
            )}
          </div>

          <div className="d-flex gap-2 mt-4">
            <button
              type="button"
              className="btn btn-primary"
              onClick={handleLink}
              disabled={submitting}
            >
              {submitting ? 'Working…' : linkButtonLabel}
            </button>
            <button
              type="button"
              className="btn btn-outline-danger"
              onClick={handleUnlink}
              disabled={submitting || !hasActiveLink}
            >
              {unlinkButtonLabel}
            </button>
          </div>

          {statusMessage && (
            <div className="alert alert-info py-2 mt-3 mb-0" role="status">
              {statusMessage}
            </div>
          )}

          {errorMessage && (
            <div className="alert alert-warning py-2 mt-3 mb-0" role="alert">
              {errorMessage}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

async function extractError(response, fallback) {
  try {
    const data = await response.clone().json();
    if (data && typeof data === 'object') {
      if (typeof data.message === 'string' && data.message.trim() !== '') {
        return data.message;
      }
      if (Array.isArray(data.errors) && data.errors.length > 0) {
        return data.errors.join(' ');
      }
    }
  } catch (err) {
    // ignore json parsing error and use fallback
  }
  return fallback;
}

export default IssueExternalLinkPanel;
