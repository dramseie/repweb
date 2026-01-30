import React, { useCallback, useMemo, useRef, useState } from 'react';

function formatBytes(size) {
  if (!Number.isFinite(size) || size <= 0) {
    return '—';
  }

  const units = ['B', 'KB', 'MB', 'GB', 'TB'];
  let value = size;
  let index = 0;

  while (value >= 1024 && index < units.length - 1) {
    value /= 1024;
    index += 1;
  }

  const precision = value >= 10 || index === 0 ? 0 : 1;
  return `${value.toFixed(precision)} ${units[index]}`;
}

function formatDate(isoString) {
  if (!isoString) {
    return '—';
  }

  const date = new Date(isoString);
  if (Number.isNaN(date.getTime())) {
    return '—';
  }

  return date.toLocaleString();
}

async function readErrorMessage(response, fallback) {
  try {
    const cloned = response.clone();
    const data = await cloned.json();
    if (data && typeof data === 'object') {
      if (typeof data.message === 'string' && data.message.trim() !== '') {
        return data.message;
      }

      if (Array.isArray(data.errors) && data.errors.length > 0) {
        return data.errors.join(' ');
      }
    }
  } catch (error) {
    // ignore parse failure and return fallback below
  }

  return fallback;
}

function mapAttachments(attachments) {
  if (!Array.isArray(attachments)) {
    return [];
  }

  const copy = attachments.slice();
  copy.sort((a, b) => {
    const tsA = new Date(a?.uploadedAt ?? 0).getTime();
    const tsB = new Date(b?.uploadedAt ?? 0).getTime();
    return (Number.isNaN(tsB) ? 0 : tsB) - (Number.isNaN(tsA) ? 0 : tsA);
  });

  return copy;
}

function IssueAttachmentsPanel({ issueId, attachments, onAttachmentsChange }) {
  const fileInputRef = useRef(null);
  const dropZoneRef = useRef(null);
  const [dragActive, setDragActive] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [status, setStatus] = useState(null);
  const [error, setError] = useState(null);

  const sortedAttachments = useMemo(() => mapAttachments(attachments), [attachments]);

  const mergeAttachments = useCallback((updater) => {
    if (typeof onAttachmentsChange !== 'function') {
      return;
    }

    onAttachmentsChange((prev) => {
      const previous = Array.isArray(prev) ? prev : [];
      const next = updater(previous);
      return mapAttachments(next);
    });
  }, [onAttachmentsChange]);

  const resetInput = () => {
    if (fileInputRef.current) {
      fileInputRef.current.value = '';
    }
  };

  const handleFiles = useCallback(async (fileList) => {
    const files = Array.from(fileList ?? []).filter((entry) => entry instanceof File && entry.size > 0);
    if (files.length === 0) {
      return;
    }

    setError(null);
    setUploading(true);

    const uploaded = [];
    const failures = [];

    for (const file of files) {
      setStatus(`Uploading ${file.name}…`);

      const formData = new FormData();
      formData.append('file', file, file.name || 'attachment');

      try {
        const response = await fetch(`/api/issues/${issueId}/attachments`, {
          method: 'POST',
          credentials: 'same-origin',
          body: formData,
        });

        if (!response.ok) {
          const message = await readErrorMessage(response, `Upload failed with status ${response.status}`);
          throw new Error(message);
        }

        const payload = await response.json();
        uploaded.push(payload);
      } catch (err) {
        failures.push(err instanceof Error ? err.message : 'Upload failed.');
      }
    }

    if (uploaded.length > 0) {
      mergeAttachments((previous) => {
        const byId = new Map();
        previous.forEach((att) => {
          if (att && att.id != null) {
            byId.set(att.id, att);
          }
        });
        uploaded.forEach((att) => {
          if (att && att.id != null) {
            byId.set(att.id, att);
          }
        });
        return Array.from(byId.values());
      });
    }

    if (failures.length > 0) {
      setError(failures.join(' '));
    }

    setStatus(null);
    setUploading(false);
    resetInput();
  }, [issueId, mergeAttachments]);

  const handleDelete = useCallback(async (attachmentId) => {
    if (!Number.isInteger(attachmentId)) {
      return;
    }

    const target = sortedAttachments.find((item) => item?.id === attachmentId);
    if (!target) {
      return;
    }

    if (typeof window !== 'undefined' && !window.confirm(`Remove ${target.originalFilename || target.filename || 'attachment'}?`)) {
      return;
    }

    setError(null);
    setStatus('Removing attachment…');

    try {
      const response = await fetch(`/api/issues/${issueId}/attachments/${attachmentId}`, {
        method: 'DELETE',
        credentials: 'same-origin',
      });

      if (!response.ok) {
        const message = await readErrorMessage(response, `Delete failed with status ${response.status}`);
        throw new Error(message);
      }

      mergeAttachments((previous) => previous.filter((att) => att?.id !== attachmentId));
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unable to remove attachment.');
    } finally {
      setStatus(null);
    }
  }, [issueId, mergeAttachments, sortedAttachments]);

  const handleBrowseClick = () => {
    resetInput();
    fileInputRef.current?.click();
  };

  const handleInputChange = (event) => {
    void handleFiles(event.target.files);
  };

  const handleDragOver = (event) => {
    event.preventDefault();
    event.stopPropagation();
    setDragActive(true);
  };

  const handleDragLeave = (event) => {
    if (!dropZoneRef.current || !dropZoneRef.current.contains(event.relatedTarget)) {
      setDragActive(false);
    }
  };

  const handleDrop = (event) => {
    event.preventDefault();
    event.stopPropagation();
    setDragActive(false);
    void handleFiles(event.dataTransfer?.files);
  };

  const handlePaste = (event) => {
    const files = event.clipboardData?.files;
    if (files && files.length > 0) {
      event.preventDefault();
      void handleFiles(files);
    }
  };

  return (
    <section className="mt-4">
      <h6 className="mb-3">Attachments</h6>

      <div
        ref={dropZoneRef}
        className={`border border-2 rounded p-4 text-center mb-3 ${dragActive ? 'border-primary bg-light' : 'border-secondary-subtle'}`}
        style={{ borderStyle: 'dashed', cursor: 'pointer' }}
        role="button"
        tabIndex={0}
        onClick={handleBrowseClick}
        onDragOver={handleDragOver}
        onDragLeave={handleDragLeave}
        onDrop={handleDrop}
        onPaste={handlePaste}
      >
        <p className="mb-1 fw-semibold">Drag & Drop files here</p>
        <p className="mb-1 text-muted small">or click to browse your computer</p>
        <p className="mb-0 text-muted small">You can also paste from clipboard (Ctrl + V).</p>
      </div>

      <input
        ref={fileInputRef}
        type="file"
        className="d-none"
        multiple
        onChange={handleInputChange}
      />

      {status && (
        <div className="alert alert-info py-2" role="status">
          {status}
        </div>
      )}

      {error && (
        <div className="alert alert-danger py-2" role="alert">
          {error}
        </div>
      )}

      {sortedAttachments.length === 0 ? (
        <p className="text-muted small">No attachments yet.</p>
      ) : (
        <div className="table-responsive">
          <table className="table table-sm align-middle mb-0">
            <thead>
              <tr>
                <th scope="col">File</th>
                <th scope="col">Size</th>
                <th scope="col">Uploaded</th>
                <th scope="col">Added By</th>
                <th scope="col" className="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              {sortedAttachments.map((attachment) => {
                const id = attachment?.id ?? `${attachment?.filename}-${attachment?.uploadedAt}`;
                const displayName = attachment?.originalFilename || attachment?.filename || 'attachment';
                const downloadUrl = attachment?.downloadUrl
                  ?? (Number.isInteger(issueId) && Number.isInteger(attachment?.id)
                    ? `/api/issues/${issueId}/attachments/${attachment.id}/download`
                    : null);
                const addedBy = attachment?.user?.email || '—';

                return (
                  <tr key={id}>
                    <td>
                      {downloadUrl ? (
                        <a href={downloadUrl} className="link-primary" target="_blank" rel="noopener noreferrer">
                          {displayName}
                        </a>
                      ) : (
                        <span>{displayName}</span>
                      )}
                    </td>
                    <td>{formatBytes(typeof attachment?.fileSize === 'number' ? attachment.fileSize : Number(attachment?.fileSize))}</td>
                    <td>{formatDate(attachment?.uploadedAt)}</td>
                    <td>{addedBy}</td>
                    <td className="text-end">
                      <div className="btn-group btn-group-sm" role="group" aria-label="Attachment actions">
                        {downloadUrl && (
                          <a
                            className="btn btn-outline-primary"
                            href={downloadUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                          >
                            <i className="bi bi-download" aria-hidden="true" />
                          </a>
                        )}
                        <button
                          type="button"
                          className="btn btn-outline-danger"
                          onClick={() => handleDelete(attachment?.id)}
                          disabled={uploading}
                        >
                          <i className="bi bi-trash" aria-hidden="true" />
                        </button>
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
    </section>
  );
}

export default IssueAttachmentsPanel;
