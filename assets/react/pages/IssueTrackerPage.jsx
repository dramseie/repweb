import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';

import IssueAttachmentsPanel from '../components/issues/IssueAttachmentsPanel';
import IssueExternalLinkPanel from '../components/issues/IssueExternalLinkPanel';

const DEFAULT_PAGE_SIZE = 50;
const ISSUE_TYPES = ['bug', 'feature', 'enhancement', 'task', 'epic', 'story', 'spike', 'incident'];
const ISSUE_PRIORITIES = ['critical', 'high', 'medium', 'low', 'trivial'];
const ISSUE_SEVERITIES = ['blocker', 'critical', 'major', 'minor', 'trivial'];
const ISSUE_STATUSES = ['new', 'open', 'in_progress', 'in_review', 'blocked', 'resolved', 'closed', 'reopened'];
const ISSUE_RESOLUTIONS = ['fixed', 'wontfix', 'duplicate', 'invalid', 'cannot_reproduce', 'workaround'];
const SOLVE_STATUS_OPTIONS = ['resolved', 'closed', 'reopened'];
const SOLVED_STATUSES = ['resolved', 'closed'];

const defaultCreateValues = {
  issueNumber: '',
  title: '',
  issueType: ISSUE_TYPES[0],
  priority: ISSUE_PRIORITIES[2],
  severity: ISSUE_SEVERITIES[2],
  status: ISSUE_STATUSES[0],
  reporterEmail: '',
  assigneeEmail: '',
  description: '',
  labels: '',
  estimatedHours: '',
};

function toLabel(value) {
  return value
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function extractErrorMessage(response, fallback) {
  return response
    .json()
    .then((data) => {
      if (data && typeof data === 'object') {
        if (typeof data.message === 'string' && data.message.trim() !== '') {
          return data.message;
        }

        if (Array.isArray(data.errors) && data.errors.length > 0) {
          return data.errors.join(' ');
        }
      }

      return fallback;
    })
    .catch(() => fallback);
}

function Modal({ title, onClose, children }) {
  return (
    <div className="modal d-block" tabIndex={-1} role="dialog" style={{ backgroundColor: 'rgba(0,0,0,0.5)' }}>
      <div className="modal-dialog modal-lg" role="document">
        <div className="modal-content">
          <div className="modal-header">
            <h5 className="modal-title">{title}</h5>
            <button type="button" className="btn-close" aria-label="Close" onClick={onClose} />
          </div>
          <div className="modal-body">{children}</div>
        </div>
      </div>
    </div>
  );
}

function IssueForm({
  mode,
  initialValues,
  submitting,
  error,
  onSubmit,
  onCancel,
  pendingAttachments = [],
  onAddAttachments,
  onRemoveAttachment,
  attachmentsStatus,
  attachmentsError,
  renderCreateExtras = null,
}) {
  const [values, setValues] = useState(initialValues);
  const attachmentInputRef = useRef(null);
  const dropZoneRef = useRef(null);
  const [dragActive, setDragActive] = useState(false);

  useEffect(() => {
    setValues(initialValues);
  }, [initialValues]);

  const handleChange = (field) => (event) => {
    setValues((prev) => ({ ...prev, [field]: event.target.value }));
  };

  const handleSubmit = (event) => {
    event.preventDefault();
    onSubmit(values);
  };

  const addFilesFromList = (fileList) => {
    if (!fileList || typeof onAddAttachments !== 'function') {
      return;
    }

    const files = Array.from(fileList).filter((file) => file instanceof File && file.size > 0);
    if (files.length > 0) {
      onAddAttachments(files);
    }
  };

  const handleAttachmentInput = (event) => {
    const { files } = event.target;
    addFilesFromList(files);
    event.target.value = '';
  };

  const handleAttachmentRemove = (index) => {
    if (typeof onRemoveAttachment === 'function') {
      onRemoveAttachment(index);
    }
  };

  const handleBrowseClick = () => {
    if (submitting) {
      return;
    }
    attachmentInputRef.current?.click();
  };

  const handleDragOver = (event) => {
    event.preventDefault();
    event.stopPropagation();
    if (submitting) {
      return;
    }
    if (event.dataTransfer) {
      event.dataTransfer.dropEffect = 'copy';
    }
    setDragActive(true);
  };

  const handleDragLeave = (event) => {
    if (!dropZoneRef.current) {
      return;
    }

    const nextTarget = event.relatedTarget;
    if (nextTarget instanceof Node && dropZoneRef.current.contains(nextTarget)) {
      return;
    }
    setDragActive(false);
  };

  const handleDrop = (event) => {
    event.preventDefault();
    event.stopPropagation();
    setDragActive(false);
    if (submitting) {
      return;
    }
    addFilesFromList(event.dataTransfer?.files);
  };

  const handlePaste = (event) => {
    const files = event.clipboardData?.files;
    if (!files || files.length === 0) {
      return;
    }
    event.preventDefault();
    if (submitting) {
      return;
    }
    addFilesFromList(files);
  };

  const pendingList = Array.isArray(pendingAttachments) ? pendingAttachments : [];

  const formatBytes = (size) => {
    if (!Number.isFinite(size) || size <= 0) {
      return '—';
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    let value = size;
    let unitIndex = 0;

    while (value >= 1024 && unitIndex < units.length - 1) {
      value /= 1024;
      unitIndex += 1;
    }

    const decimals = value >= 10 || unitIndex === 0 ? 0 : 1;
    return `${value.toFixed(decimals)} ${units[unitIndex]}`;
  };

  return (
    <form onSubmit={handleSubmit} className="needs-validation" noValidate>
      {error && (
        <div className="alert alert-danger" role="alert">
          {error}
        </div>
      )}

      {mode === 'edit' && (
        <div className="mb-3">
          <label className="form-label" htmlFor="issue-number">Issue Number</label>
          <input
            id="issue-number"
            type="text"
            className="form-control"
            value={values.issueNumber || '—'}
            readOnly
            disabled
          />
        </div>
      )}

      <div className="row g-3">
        <div className="col-12">
          <label className="form-label" htmlFor="issue-title">Title *</label>
          <input
            id="issue-title"
            name="title"
            type="text"
            className="form-control"
            value={values.title}
            onChange={handleChange('title')}
            required
          />
        </div>
        <div className="col-lg-3 col-md-6">
          <label className="form-label" htmlFor="issue-type">Type *</label>
          <select
            id="issue-type"
            className="form-select"
            value={values.issueType}
            onChange={handleChange('issueType')}
          >
            {ISSUE_TYPES.map((option) => (
              <option key={option} value={option}>{toLabel(option)}</option>
            ))}
          </select>
        </div>
        <div className="col-lg-3 col-md-6">
          <label className="form-label" htmlFor="issue-priority">Priority *</label>
          <select
            id="issue-priority"
            className="form-select"
            value={values.priority}
            onChange={handleChange('priority')}
          >
            {ISSUE_PRIORITIES.map((option) => (
              <option key={option} value={option}>{toLabel(option)}</option>
            ))}
          </select>
        </div>
        <div className="col-lg-3 col-md-6">
          <label className="form-label" htmlFor="issue-severity">Severity *</label>
          <select
            id="issue-severity"
            className="form-select"
            value={values.severity}
            onChange={handleChange('severity')}
          >
            {ISSUE_SEVERITIES.map((option) => (
              <option key={option} value={option}>{toLabel(option)}</option>
            ))}
          </select>
        </div>
        <div className="col-lg-3 col-md-6">
          <label className="form-label" htmlFor="issue-status">Status *</label>
          <select
            id="issue-status"
            className="form-select"
            value={values.status}
            onChange={handleChange('status')}
          >
            {ISSUE_STATUSES.map((option) => (
              <option key={option} value={option}>{toLabel(option)}</option>
            ))}
          </select>
        </div>
        <div className="col-lg-6 col-md-6">
          <label className="form-label" htmlFor="issue-reporter-email">Reporter Email *</label>
          <input
            id="issue-reporter-email"
            name="reporterEmail"
            type="email"
            className="form-control"
            value={values.reporterEmail}
            onChange={handleChange('reporterEmail')}
            required
          />
          <div className="form-text">Must match an existing user account.</div>
        </div>
        <div className="col-lg-6 col-md-6">
          <label className="form-label" htmlFor="issue-assignee-email">Assignee Email</label>
          <input
            id="issue-assignee-email"
            name="assigneeEmail"
            type="email"
            className="form-control"
            value={values.assigneeEmail}
            onChange={handleChange('assigneeEmail')}
            placeholder="Leave blank to keep or clear"
          />
        </div>
        <div className="col-md-6">
          <label className="form-label" htmlFor="issue-labels">Labels</label>
          <input
            id="issue-labels"
            name="labels"
            type="text"
            className="form-control"
            value={values.labels}
            onChange={handleChange('labels')}
            placeholder="Comma-separated (e.g. backend, urgent)"
          />
        </div>
        <div className="col-12">
          <label className="form-label" htmlFor="issue-description">Description</label>
          <textarea
            id="issue-description"
            name="description"
            rows={4}
            className="form-control"
            value={values.description}
            onChange={handleChange('description')}
          />
        </div>
      </div>

      <div className="d-flex justify-content-end gap-2 mt-4">
        <button type="button" className="btn btn-outline-secondary" onClick={onCancel} disabled={submitting}>
          Cancel
        </button>
        <button type="submit" className="btn btn-primary" disabled={submitting}>
          {submitting ? (
            <>
              <span className="spinner-border spinner-border-sm" role="status" aria-hidden="true" />
              <span className="ms-2">Saving…</span>
            </>
          ) : (
            <>
              <i className="bi bi-check-lg" aria-hidden="true" />
              <span className="ms-2">{mode === 'create' ? 'Create Issue' : 'Save Changes'}</span>
            </>
          )}
        </button>
      </div>

      {mode === 'create' && (
        <div className="mt-4">
          <label className="form-label" htmlFor="issue-create-attachments">Attachments</label>
          <div
            ref={dropZoneRef}
            className={`border border-2 rounded p-4 text-center ${dragActive ? 'border-primary bg-light' : 'border-secondary-subtle'}`}
            style={{ borderStyle: 'dashed', cursor: submitting ? 'not-allowed' : 'pointer' }}
            role="button"
            tabIndex={0}
            onClick={handleBrowseClick}
            onKeyDown={(event) => {
              if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                handleBrowseClick();
              }
            }}
            onDragOver={handleDragOver}
            onDragLeave={handleDragLeave}
            onDrop={handleDrop}
            onPaste={handlePaste}
          >
            <p className="mb-1 fw-semibold">Drag & Drop files here</p>
            <p className="mb-1 text-muted small">or click to select files from your computer</p>
            <p className="mb-0 text-muted small">Paste from clipboard (Ctrl + V) is also supported. Up to 50 MB per file.</p>
            <button
              id="issue-create-attachments"
              type="button"
              className="btn btn-outline-primary btn-sm mt-3"
              disabled={submitting}
              onClick={(event) => {
                event.stopPropagation();
                handleBrowseClick();
              }}
            >
              <i className="bi bi-paperclip" aria-hidden="true" />
              <span className="ms-2">Select Files</span>
            </button>
          </div>
          <input
            ref={attachmentInputRef}
            type="file"
            className="d-none"
            multiple
            onChange={handleAttachmentInput}
          />

          {pendingList.length === 0 ? (
            <p className="text-muted small mt-3 mb-0">No attachments selected.</p>
          ) : (
            <ul className="list-group list-group-flush mt-3">
              {pendingList.map((file, index) => (
                <li key={`${file.name}-${file.size}-${file.lastModified}`} className="list-group-item px-0 d-flex justify-content-between align-items-center">
                  <div>
                    <div className="fw-semibold">{file.name}</div>
                    <div className="text-muted small">{formatBytes(file.size)}</div>
                  </div>
                  <button
                    type="button"
                    className="btn btn-link text-danger p-0"
                    onClick={() => handleAttachmentRemove(index)}
                    disabled={submitting}
                  >
                    <i className="bi bi-x-circle" aria-hidden="true" />
                    <span className="visually-hidden">Remove</span>
                  </button>
                </li>
              ))}
            </ul>
          )}

          {attachmentsStatus && (
            <div className="alert alert-info py-2 mt-3" role="status">
              {attachmentsStatus}
            </div>
          )}

          {attachmentsError && (
            <div className="alert alert-warning py-2 mt-3" role="alert">
              {attachmentsError}
            </div>
          )}

          {typeof renderCreateExtras === 'function' ? renderCreateExtras() : null}
        </div>
      )}
    </form>
  );
}

function SolveIssueForm({ issue, initialValues, submitting, error, onSubmit, onCancel }) {
  const [values, setValues] = useState(initialValues);

  useEffect(() => {
    setValues(initialValues);
  }, [initialValues]);

  const handleChange = (field) => (event) => {
    setValues((prev) => ({ ...prev, [field]: event.target.value }));
  };

  const handleSubmit = (event) => {
    event.preventDefault();
    onSubmit(values);
  };

  return (
    <form onSubmit={handleSubmit}>
      {error && (
        <div className="alert alert-danger" role="alert">
          {error}
        </div>
      )}

      <p className="text-muted">
        Update the status of <strong>{issue.issueNumber ?? `Issue #${issue.id}`}</strong> and optionally record a resolution or actual hours.
      </p>

      <div className="row g-3">
        <div className="col-md-6">
          <label className="form-label" htmlFor="solve-status">New Status *</label>
          <select
            id="solve-status"
            className="form-select"
            value={values.status}
            onChange={handleChange('status')}
          >
            {SOLVE_STATUS_OPTIONS.map((option) => (
              <option key={option} value={option}>{toLabel(option)}</option>
            ))}
          </select>
        </div>
        <div className="col-md-6">
          <label className="form-label" htmlFor="solve-resolution">Resolution</label>
          <select
            id="solve-resolution"
            className="form-select"
            value={values.resolution}
            onChange={handleChange('resolution')}
          >
            <option value="">— None —</option>
            {ISSUE_RESOLUTIONS.map((option) => (
              <option key={option} value={option}>{toLabel(option)}</option>
            ))}
          </select>
        </div>
        <div className="col-md-6">
          <label className="form-label" htmlFor="solve-actual-hours">Actual Hours</label>
          <input
            id="solve-actual-hours"
            name="actualHours"
            type="number"
            step="0.25"
            className="form-control"
            value={values.actualHours}
            onChange={handleChange('actualHours')}
          />
          <div className="form-text">Optional: time spent resolving the issue.</div>
        </div>
        <div className="col-12">
          <label className="form-label" htmlFor="solve-notes">Notes</label>
          <textarea
            id="solve-notes"
            name="notes"
            rows={3}
            className="form-control"
            value={values.notes ?? ''}
            onChange={handleChange('notes')}
            placeholder="Optional implementation notes"
          />
        </div>
      </div>

      <div className="d-flex justify-content-end gap-2 mt-4">
        <button type="button" className="btn btn-outline-secondary" onClick={onCancel} disabled={submitting}>
          Cancel
        </button>
        <button type="submit" className="btn btn-success" disabled={submitting}>
          {submitting ? (
            <>
              <span className="spinner-border spinner-border-sm" role="status" aria-hidden="true" />
              <span className="ms-2">Updating…</span>
            </>
          ) : (
            <>
              <i className="bi bi-clipboard-check" aria-hidden="true" />
              <span className="ms-2">Apply</span>
            </>
          )}
        </button>
      </div>
    </form>
  );
}

function mapIssueToFormValues(issue) {
  return {
    issueNumber: issue.issueNumber ?? '',
    title: issue.title ?? '',
    issueType: issue.issueType ?? ISSUE_TYPES[0],
    priority: issue.priority ?? ISSUE_PRIORITIES[2],
    severity: issue.severity ?? ISSUE_SEVERITIES[2],
    status: issue.status ?? ISSUE_STATUSES[0],
    reporterEmail: issue.reporter?.email ?? issue.reporterEmail ?? '',
    assigneeEmail: issue.assignee?.email ?? issue.assigneeEmail ?? '',
    description: issue.description ?? '',
    labels: Array.isArray(issue.labels) ? issue.labels.join(', ') : '',
    estimatedHours: issue.estimatedHours ?? '',
  };
}

function normaliseLabelPayload(value) {
  const trimmed = value.trim();
  if (trimmed === '') {
    return [];
  }

  return trimmed
    .split(',')
    .map((entry) => entry.trim())
    .filter((entry) => entry !== '');
}

function buildIssuePayload(values, { requireReporterEmail }) {
  const reporterEmail = String(values.reporterEmail ?? '').trim();
  const assigneeEmail = String(values.assigneeEmail ?? '').trim();
  const titleTrim = String(values.title ?? '').trim();
  const estimatedTrim = String(values.estimatedHours ?? '').trim();

  if (titleTrim === '') {
    return { error: 'Title is required.' };
  }

  if (requireReporterEmail && reporterEmail === '') {
    return { error: 'Reporter email is required.' };
  }

  if (reporterEmail !== '' && !reporterEmail.includes('@')) {
    return { error: 'Reporter email must be a valid address.' };
  }

  if (assigneeEmail !== '' && !assigneeEmail.includes('@')) {
    return { error: 'Assignee email must be a valid address.' };
  }

  const payload = {
    title: titleTrim,
    issueType: values.issueType,
    priority: values.priority,
    severity: values.severity,
    status: values.status,
    description: String(values.description ?? '').trim() || null,
    labels: normaliseLabelPayload(values.labels ?? ''),
  };

  if (reporterEmail !== '') {
    payload.reporterEmail = reporterEmail;
  }

  if (assigneeEmail !== '') {
    payload.assigneeEmail = assigneeEmail;
  } else {
    payload.assigneeEmail = '';
  }

  if (estimatedTrim !== '') {
    const estimatedNumber = Number(estimatedTrim);
    if (Number.isNaN(estimatedNumber)) {
      return { error: 'Estimated hours must be a number.' };
    }
    payload.estimatedHours = estimatedNumber;
  } else {
    payload.estimatedHours = null;
  }

  return { payload };
}

function IssueTrackerPage() {
  const [issues, setIssues] = useState([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [search, setSearch] = useState('');
  const [modalState, setModalState] = useState(null);

  const queryString = useMemo(() => {
    const params = new URLSearchParams();
    params.set('limit', String(DEFAULT_PAGE_SIZE));
    params.set('page', '1');
    if (search.trim() !== '') params.set('search', search.trim());
    return params.toString();
  }, [search]);

  const loadIssues = useCallback(async () => {
    setLoading(true);
    setError(null);

    try {
      const response = await fetch(`/api/issues?${queryString}`, {
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
        },
      });

      if (!response.ok) {
        throw new Error(`Request failed with status ${response.status}`);
      }

      const payload = await response.json();
      setIssues(Array.isArray(payload.items) ? payload.items : []);
      setTotal(Number(payload.total) || 0);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unknown error');
    } finally {
      setLoading(false);
    }
  }, [queryString]);

  useEffect(() => {
    loadIssues();
  }, [loadIssues]);

  const closeModal = useCallback(() => {
    setModalState(null);
  }, []);

  const refreshAfterAction = useCallback(async () => {
    closeModal();
    await loadIssues();
  }, [closeModal, loadIssues]);

  const openCreateModal = () => {
    setModalState({
      type: 'create',
      initialValues: defaultCreateValues,
      submitting: false,
      error: null,
      pendingAttachments: [],
      attachmentsStatus: null,
      attachmentsError: null,
      pendingExternalLink: null,
    });
  };

  const openEditModal = (issue) => {
    if (!issue?.id) {
      return;
    }

    setModalState({
      type: 'edit',
      issueId: issue.id,
      issue: null,
      initialValues: defaultCreateValues,
      submitting: false,
      loading: true,
      error: null,
    });

    fetch(`/api/issues/${issue.id}`, {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error(`Request failed with status ${response.status}`);
        }
        return response.json();
      })
      .then((data) => {
        setModalState((prev) => {
          if (!prev || prev.type !== 'edit' || prev.issueId !== issue.id) {
            return prev;
          }
          return {
            ...prev,
            issue: data,
            initialValues: mapIssueToFormValues(data),
            loading: false,
          };
        });
      })
      .catch((err) => {
        setModalState((prev) => {
          if (!prev || prev.type !== 'edit' || prev.issueId !== issue.id) {
            return prev;
          }
          return {
            ...prev,
            loading: false,
            error: err instanceof Error ? err.message : 'Unable to load issue details.',
          };
        });
      });
  };
      const addCreateAttachments = useCallback((files) => {
        if (!Array.isArray(files) || files.length === 0) {
          return;
        }

        setModalState((prev) => {
          if (!prev || prev.type !== 'create') {
            return prev;
          }

          const existing = Array.isArray(prev.pendingAttachments) ? prev.pendingAttachments : [];
          const merged = [...existing];

          files.forEach((file) => {
            if (!merged.some((entry) => entry.name === file.name && entry.size === file.size && entry.lastModified === file.lastModified)) {
              merged.push(file);
            }
          });

          return {
            ...prev,
            pendingAttachments: merged,
          };
        });
      }, []);

      const removeCreateAttachment = useCallback((index) => {
        setModalState((prev) => {
          if (!prev || prev.type !== 'create') {
            return prev;
          }

          const current = Array.isArray(prev.pendingAttachments) ? prev.pendingAttachments : [];
          if (index < 0 || index >= current.length) {
            return prev;
          }

          const next = current.slice();
          next.splice(index, 1);

          return {
            ...prev,
            pendingAttachments: next,
          };
        });
      }, []);

      const setCreateExternalLink = useCallback((link) => {
        setModalState((prev) => {
          if (!prev || prev.type !== 'create') {
            return prev;
          }

          let sanitized = null;
          if (link && typeof link === 'object') {
            const toNumericOrNull = (value) => {
              const numeric = Number(value);
              return Number.isFinite(numeric) && numeric > 0 ? numeric : null;
            };

            const projectId = toNumericOrNull(link.projectId);
            const taskId = toNumericOrNull(link.taskId);
            const smartsheetRowId = toNumericOrNull(link.smartsheetRowId);

            if (projectId || taskId || smartsheetRowId) {
              sanitized = {
                projectId,
                taskId,
                smartsheetRowId,
              };
            }
          }

          return {
            ...prev,
            pendingExternalLink: sanitized,
          };
        });
      }, []);


  const openSolveModal = (issue) => {
    if (!issue?.id) {
      return;
    }

    setModalState({
      type: 'solve',
      issue,
      initialValues: {
        status: issue.status === 'closed' ? 'closed' : 'resolved',
        resolution: '',
        actualHours: issue.actualHours ?? '',
        notes: '',
      },
      submitting: false,
      error: null,
    });
  };

  const updateAttachments = useCallback((updater) => {
    setModalState((prev) => {
      if (!prev || prev.type !== 'edit' || !prev.issue) {
        return prev;
      }

      const current = Array.isArray(prev.issue.attachments) ? prev.issue.attachments : [];
      const next = typeof updater === 'function' ? updater(current) : updater;

      return {
        ...prev,
        issue: {
          ...prev.issue,
          attachments: Array.isArray(next) ? next : current,
        },
      };
    });
  }, []);

  const applyIssueUpdate = useCallback((updatedIssue) => {
    if (!updatedIssue || typeof updatedIssue !== 'object') {
      return;
    }

    const updatedId = updatedIssue.id ?? null;

    setModalState((prev) => {
      if (!prev || prev.type !== 'edit') {
        return prev;
      }

      return {
        ...prev,
        issue: updatedIssue,
      };
    });

    if (updatedId !== null) {
      setIssues((prev) => {
        if (!Array.isArray(prev) || prev.length === 0) {
          return prev;
        }

        return prev.map((issue) => (issue && issue.id === updatedId ? { ...issue, ...updatedIssue } : issue));
      });
    }
  }, []);

  const linkExternalSource = useCallback(async (issueId, linkConfig) => {
    if (!issueId || !linkConfig || typeof linkConfig !== 'object') {
      return { ok: true };
    }

    const payload = { source: 'smartsheet' };
    let hasValue = false;

    const assignNumeric = (field, value) => {
      const numeric = Number(value);
      if (Number.isFinite(numeric) && numeric > 0) {
        payload[field] = numeric;
        hasValue = true;
      }
    };

    assignNumeric('projectId', linkConfig.projectId);
    assignNumeric('taskId', linkConfig.taskId);
    assignNumeric('smartsheetRowId', linkConfig.smartsheetRowId);

    if (!hasValue) {
      return { ok: true };
    }

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
        const message = await extractErrorMessage(response, `External linking failed with status ${response.status}`);
        return { ok: false, message };
      }
    } catch (err) {
      return { ok: false, message: err instanceof Error ? err.message : 'External linking failed.' };
    }

    return { ok: true };
  }, []);

  const uploadPendingAttachments = useCallback(async (issueId, files) => {
    const failedFiles = [];
    const failureMessages = [];

    for (const file of files) {
      setModalState((prev) => {
        if (!prev || prev.type !== 'create') {
          return prev;
        }

        return {
          ...prev,
          attachmentsStatus: `Uploading ${file.name}…`,
          attachmentsError: null,
        };
      });

      const formData = new FormData();
      formData.append('file', file, file.name || 'attachment');

      try {
        const response = await fetch(`/api/issues/${issueId}/attachments`, {
          method: 'POST',
          credentials: 'same-origin',
          body: formData,
        });

        if (!response.ok) {
          const message = await extractErrorMessage(response, `Upload failed for ${file.name}.`);
          failedFiles.push(file);
          failureMessages.push(`${file.name}: ${message}`);
        }
      } catch (err) {
        failedFiles.push(file);
        failureMessages.push(`${file.name}: ${err instanceof Error ? err.message : 'Upload failed.'}`);
      }
    }

    setModalState((prev) => {
      if (!prev || prev.type !== 'create') {
        return prev;
      }

      return {
        ...prev,
        attachmentsStatus: null,
        pendingAttachments: failedFiles,
        attachmentsError: failureMessages.length > 0 ? failureMessages.join(' ') : null,
      };
    });

    return { failedFiles, failureMessages };
  }, []);

  const submitCreate = async (values) => {
    const { payload, error: validationError } = buildIssuePayload(values, { requireReporterEmail: true });
    if (validationError) {
      setModalState((prev) => (prev ? { ...prev, error: validationError } : prev));
      return;
    }

    const pending = modalState?.type === 'create' && Array.isArray(modalState.pendingAttachments)
      ? modalState.pendingAttachments
      : [];

    const pendingLink = modalState?.type === 'create'
      ? modalState.pendingExternalLink
      : null;

    setModalState((prev) => (prev ? {
      ...prev,
      submitting: true,
      error: null,
      attachmentsError: null,
    } : prev));

    try {
      const response = await fetch('/api/issues', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(payload),
      });

      if (!response.ok) {
        const message = await extractErrorMessage(response, `Create failed with status ${response.status}`);
        setModalState((prev) => (prev ? { ...prev, submitting: false, error: message } : prev));
        return;
      }

      const createdIssue = await response.json();

      let linkFailureMessage = null;
      if (createdIssue?.id && pendingLink) {
        const { ok, message } = await linkExternalSource(createdIssue.id, pendingLink);
        if (!ok) {
          linkFailureMessage = message || 'External link could not be applied.';
        }
      }

      if (createdIssue?.id && pending.length > 0) {
        const { failureMessages } = await uploadPendingAttachments(createdIssue.id, pending);
        if (failureMessages.length > 0) {
          window.alert(`Issue created, but some attachments failed to upload:\n${failureMessages.join('\n')}`);
        }
      }

      if (linkFailureMessage) {
        window.alert(`Issue created, but external linking failed: ${linkFailureMessage}`);
      }

      await refreshAfterAction();
    } catch (err) {
      setModalState((prev) => (prev ? {
        ...prev,
        submitting: false,
        error: err instanceof Error ? err.message : 'Unable to create issue.',
      } : prev));
    }
  };

  const submitEdit = async (values) => {
    if (!modalState || modalState.type !== 'edit' || !modalState.issueId) {
      return;
    }

    const { payload, error: validationError } = buildIssuePayload(values, { requireReporterEmail: true });
    if (validationError) {
      setModalState((prev) => (prev ? { ...prev, error: validationError } : prev));
      return;
    }

    setModalState((prev) => (prev ? { ...prev, submitting: true, error: null } : prev));

    try {
      const response = await fetch(`/api/issues/${modalState.issueId}`, {
        method: 'PUT',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(payload),
      });

      if (!response.ok) {
        const message = await extractErrorMessage(response, `Update failed with status ${response.status}`);
        setModalState((prev) => (prev ? { ...prev, submitting: false, error: message } : prev));
        return;
      }

      await refreshAfterAction();
    } catch (err) {
      setModalState((prev) => (prev ? {
        ...prev,
        submitting: false,
        error: err instanceof Error ? err.message : 'Unable to update issue.',
      } : prev));
    }
  };

  const submitSolve = async (values) => {
    if (!modalState || modalState.type !== 'solve' || !modalState.issue?.id) {
      return;
    }

    const payload = {
      status: values.status,
    };

    const nowIso = new Date().toISOString();

    if (values.status === 'resolved') {
      payload.resolvedAt = nowIso;
      payload.closedAt = null;
    } else if (values.status === 'closed') {
      payload.closedAt = nowIso;
      if (!modalState.issue.resolvedAt) {
        payload.resolvedAt = nowIso;
      }
    } else if (values.status === 'reopened') {
      payload.resolution = null;
      payload.resolvedAt = null;
      payload.closedAt = null;
    }

    if (values.resolution && values.status !== 'reopened') {
      payload.resolution = values.resolution;
    } else if (values.status === 'reopened') {
      payload.resolution = null;
    }

    const actualTrim = String(values.actualHours ?? '').trim();
    if (actualTrim !== '') {
      const actualNumber = Number(actualTrim);
      if (Number.isNaN(actualNumber)) {
        setModalState((prev) => (prev ? { ...prev, error: 'Actual hours must be numeric.' } : prev));
        return;
      }
      payload.actualHours = actualNumber;
    }

    setModalState((prev) => (prev ? { ...prev, submitting: true, error: null } : prev));

    try {
      const response = await fetch(`/api/issues/${modalState.issue.id}`, {
        method: 'PATCH',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(payload),
      });

      if (!response.ok) {
        const message = await extractErrorMessage(response, `Update failed with status ${response.status}`);
        setModalState((prev) => (prev ? { ...prev, submitting: false, error: message } : prev));
        return;
      }

      await refreshAfterAction();
    } catch (err) {
      setModalState((prev) => (prev ? {
        ...prev,
        submitting: false,
        error: err instanceof Error ? err.message : 'Unable to update issue status.',
      } : prev));
    }
  };

  return (
    <div className="container-fluid py-4">
      <div className="d-flex flex-column flex-xl-row align-items-xl-center gap-3 mb-4">
        <div>
          <h1 className="h3 mb-0">Issue Tracker</h1>
          <small className="text-muted">Showing the {Math.min(issues.length, DEFAULT_PAGE_SIZE)} most recent issues (total {total}).</small>
        </div>
        <div className="ms-xl-auto d-flex gap-2 flex-column flex-sm-row">
          <div className="input-group">
            <span className="input-group-text" id="issue-search-label">
              <i className="bi bi-search" aria-hidden="true" />
            </span>
            <input
              type="search"
              className="form-control"
              placeholder="Search issues (title, description, number)"
              aria-label="Search issues"
              aria-describedby="issue-search-label"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              onKeyDown={(event) => {
                if (event.key === 'Enter') loadIssues();
              }}
            />
          </div>
          <div className="d-flex gap-2">
            <button
              type="button"
              className="btn btn-outline-primary"
              onClick={openCreateModal}
            >
              <i className="bi bi-plus-lg" aria-hidden="true" />
              <span className="ms-2">Create Issue</span>
            </button>
            <button
              type="button"
              className="btn btn-primary"
              disabled={loading}
              onClick={loadIssues}
            >
              {loading ? (
                <span className="spinner-border spinner-border-sm" role="status" aria-hidden="true" />
              ) : (
                <i className="bi bi-arrow-repeat" aria-hidden="true" />
              )}
              <span className="ms-2">Refresh</span>
            </button>
          </div>
        </div>
      </div>

      {error && (
        <div className="alert alert-danger" role="alert">
          Unable to load issues: {error}
        </div>
      )}

      <div className="table-responsive shadow-sm">
        <table className="table table-hover align-middle mb-0">
          <thead className="table-light">
            <tr>
              <th scope="col" style={{ minWidth: '110px' }}>Number</th>
              <th scope="col" style={{ minWidth: '220px' }}>Title</th>
              <th scope="col" style={{ minWidth: '110px' }}>Status</th>
              <th scope="col" style={{ minWidth: '110px' }}>Priority</th>
              <th scope="col" style={{ minWidth: '110px' }}>Severity</th>
              <th scope="col" style={{ minWidth: '160px' }}>Reporter</th>
              <th scope="col" style={{ minWidth: '160px' }}>Assignee</th>
              <th scope="col" style={{ minWidth: '150px' }} className="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            {issues.length === 0 && !loading ? (
              <tr>
                <td colSpan={8} className="text-center text-muted py-5">
                  No issues found.
                </td>
              </tr>
            ) : (
              issues.map((issue) => {
                const solved = SOLVED_STATUSES.includes(issue.status ?? '');
                return (
                  <tr key={issue.id ?? issue.issueNumber}>
                    <td className="fw-semibold">{issue.issueNumber || '—'}</td>
                    <td>
                      <div className="fw-semibold text-truncate" style={{ maxWidth: '360px' }}>{issue.title || 'Untitled issue'}</div>
                      {issue.description ? (
                        <div className="text-muted small text-truncate" style={{ maxWidth: '360px' }}>{issue.description}</div>
                      ) : null}
                    </td>
                    <td><span className="badge bg-secondary text-uppercase">{issue.status || 'unknown'}</span></td>
                    <td className="text-capitalize">{issue.priority || '—'}</td>
                    <td className="text-capitalize">{issue.severity || '—'}</td>
                    <td>{issue.reporter?.email ?? issue.reporter?.name ?? issue.reporterName ?? '—'}</td>
                    <td>{issue.assignee?.email ?? issue.assignee?.name ?? issue.assigneeName ?? '—'}</td>
                    <td className="text-end">
                      <div className="btn-group btn-group-sm" role="group" aria-label="Issue actions">
                        <button
                          type="button"
                          className="btn btn-outline-secondary"
                          onClick={() => openEditModal(issue)}
                        >
                          <i className="bi bi-pencil" aria-hidden="true" />
                          <span className="ms-1 d-none d-lg-inline">Edit</span>
                        </button>
                        <button
                          type="button"
                          className="btn btn-outline-success"
                          onClick={() => openSolveModal(issue)}
                          disabled={solved}
                        >
                          <i className="bi bi-clipboard-check" aria-hidden="true" />
                          <span className="ms-1 d-none d-lg-inline">Solve</span>
                        </button>
                      </div>
                    </td>
                  </tr>
                );
              })
            )}
          </tbody>
        </table>
      </div>

      {modalState?.type === 'create' && (
        <Modal title="Create Issue" onClose={closeModal}>
          <IssueForm
            mode="create"
            initialValues={modalState.initialValues}
            submitting={modalState.submitting}
            error={modalState.error}
            onSubmit={submitCreate}
            onCancel={closeModal}
            pendingAttachments={modalState.pendingAttachments ?? []}
            onAddAttachments={addCreateAttachments}
            onRemoveAttachment={removeCreateAttachment}
            attachmentsStatus={modalState.attachmentsStatus}
            attachmentsError={modalState.attachmentsError}
            renderCreateExtras={() => (
              <IssueExternalLinkPanel
                issueId={null}
                linkedProject={null}
                linkedTask={null}
                pendingLink={modalState.pendingExternalLink}
                onPendingChange={setCreateExternalLink}
              />
            )}
          />
        </Modal>
      )}

      {modalState?.type === 'edit' && (
        <Modal title="Edit Issue" onClose={closeModal}>
          {modalState.loading ? (
            <div className="d-flex justify-content-center py-5">
              <div className="spinner-border" role="status" aria-hidden="true" />
            </div>
          ) : (
            <>
              <IssueForm
                mode="edit"
                initialValues={modalState.initialValues}
                submitting={modalState.submitting}
                error={modalState.error}
                onSubmit={submitEdit}
                onCancel={closeModal}
              />

              {modalState.issue && (
                <>
                  <IssueExternalLinkPanel
                    issueId={modalState.issue.id ?? modalState.issueId}
                    linkedProject={modalState.issue.project ?? null}
                    linkedTask={modalState.issue.task ?? null}
                    onIssueUpdate={applyIssueUpdate}
                  />

                  <IssueAttachmentsPanel
                    issueId={modalState.issueId}
                    attachments={modalState.issue.attachments ?? []}
                    onAttachmentsChange={updateAttachments}
                  />
                </>
              )}
            </>
          )}
        </Modal>
      )}

      {modalState?.type === 'solve' && (
        <Modal title="Resolve Issue" onClose={closeModal}>
          <SolveIssueForm
            issue={modalState.issue}
            initialValues={modalState.initialValues}
            submitting={modalState.submitting}
            error={modalState.error}
            onSubmit={submitSolve}
            onCancel={closeModal}
          />
        </Modal>
      )}
    </div>
  );
}

export default IssueTrackerPage;
