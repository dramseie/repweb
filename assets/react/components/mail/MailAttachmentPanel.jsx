import React, { useEffect, useMemo, useState } from "react";

/** Small helper */
const asArray = (x) => (Array.isArray(x) ? x : x ? [x] : []);

function AttachmentRow({ att, onDelete }) {
  return (
    <tr>
      <td>{att.id}</td>
      <td>
        <span className="badge text-bg-secondary">{att.TYPE}</span>
      </td>
      <td>
        {att.TYPE === "report" ? (
          <>
            <div>Report ID: <code>{att.report_id}</code></div>
            <div>Format: <code>{att.FORMAT}</code></div>
          </>
        ) : (
          <>
            {att.file_url ? (
              <a href={att.file_url} target="_blank" rel="noreferrer">{att.file_url}</a>
            ) : (
              <code>{att.file_path || "-"}</code>
            )}
          </>
        )}
      </td>
      <td>
        {att.is_link ? (
          <span className="badge text-bg-info">Link</span>
        ) : (
          <span className="badge text-bg-dark">Attachment</span>
        )}
        {att.is_link && (
          <span className="ms-2 badge text-bg-{att.is_public_link ? 'success' : 'warning'}">
            {att.is_public_link ? "Public" : "Private"}
          </span>
        )}
      </td>
      <td><code>{att.filename_override || "-"}</code></td>
      <td>{att.POSITION}</td>
      <td className="text-end">
        <button className="btn btn-sm btn-outline-danger" onClick={() => onDelete(att)}>
          <i className="bi bi-trash" /> Delete
        </button>
      </td>
    </tr>
  );
}

export default function MailAttachmentPanel({ templateId }) {
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");

  // form state
  const [type, setType] = useState("report");                 // 'report' | 'file'
  const [position, setPosition] = useState("");
  const [filenameOverride, setFilenameOverride] = useState("");
  const [isLink, setIsLink] = useState(true);
  const [isPublicLink, setIsPublicLink] = useState(true);

  // report
  const [reportId, setReportId] = useState("");
  const [format, setFormat] = useState("csv");

  // file
  const [file, setFile] = useState(null);
  const [filePath, setFilePath] = useState("");

  const nextPosition = useMemo(() => {
    if (!rows.length) return 0;
    return 1 + Math.max(...rows.map((r) => Number(r.POSITION) || 0));
  }, [rows]);

  useEffect(() => {
    if (!templateId) return;
    let alive = true;
    (async () => {
      setLoading(true);
      setError("");
      try {
        const r = await fetch(`/api/mail/templates/${templateId}/attachments`);
        const data = await r.json();
        if (!r.ok) throw new Error(data?.error || r.statusText);
        if (alive) setRows(asArray(data));
      } catch (e) {
        if (alive) setError(String(e.message || e));
      } finally {
        if (alive) setLoading(false);
      }
    })();
    return () => { alive = false; };
  }, [templateId]);

  async function refresh() {
    const r = await fetch(`/api/mail/templates/${templateId}/attachments`);
    const data = await r.json();
    if (r.ok) setRows(asArray(data));
  }

  async function onDelete(att) {
    if (!window.confirm(`Delete attachment #${att.id}?`)) return;
    const r = await fetch(`/api/mail/templates/${templateId}/attachments/${att.id}`, {
      method: "DELETE",
      headers: { Accept: "application/json" },
    });
    const data = await r.json().catch(() => ({}));
    if (!r.ok) {
      alert(data?.error || `Delete failed (${r.status})`);
      return;
    }
    refresh();
  }

  async function onSubmit(e) {
    e.preventDefault();
    setSaving(true); setError("");
    try {
      if (type === "file") {
        // multipart form-data
        if (!file && !filePath) {
          throw new Error("Choose a file to upload or provide a server file path.");
        }
        const fd = new FormData();
        fd.set("TYPE", "file");
        if (file) fd.set("file", file);
        if (filePath) fd.set("file_path", filePath);
        if (position !== "") fd.set("POSITION", String(position));
        if (filenameOverride) fd.set("filename_override", filenameOverride);
        fd.set("is_link", isLink ? "1" : "0");
        fd.set("is_public_link", isPublicLink ? "1" : "0");
        // FORMAT not used for raw files; optional
        const r = await fetch(`/api/mail/templates/${templateId}/attachments`, { method: "POST", body: fd });
        const data = await r.json();
        if (!r.ok) throw new Error(data?.error || r.statusText);
      } else {
        // JSON body
        const body = {
          TYPE: "report",
          report_id: Number(reportId),
          FORMAT: format,
          POSITION: position === "" ? undefined : Number(position),
          filename_override: filenameOverride || undefined,
          is_link: isLink ? 1 : 0,
          is_public_link: isPublicLink ? 1 : 0,
        };
        const r = await fetch(`/api/mail/templates/${templateId}/attachments`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(body),
        });
        const data = await r.json();
        if (!r.ok) throw new Error(data?.error || r.statusText);
      }

      // reset minimal fields for convenience
      setPosition("");
      setFilenameOverride("");
      setFile(null);
      setFilePath("");
      // keep reportId/format so you can add multiple quickly

      await refresh();
    } catch (e) {
      setError(String(e.message || e));
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="card mt-4">
      <div className="card-header d-flex align-items-center">
        <i className="bi bi-paperclip me-2" />
        <strong>Attachments</strong>
        {loading && <span className="ms-2 text-muted">(loading…)</span>}
        {error && <span className="ms-2 text-danger">{error}</span>}
      </div>

      <div className="card-body">
        {/* List */}
        <div className="table-responsive">
          <table className="table table-sm">
            <thead>
              <tr>
                <th>ID</th>
                <th>Type</th>
                <th>Resource</th>
                <th>Delivery</th>
                <th>Filename</th>
                <th>Pos</th>
                <th className="text-end"></th>
              </tr>
            </thead>
            <tbody>
              {rows.map((att) => (
                <AttachmentRow key={att.id} att={att} onDelete={onDelete} />
              ))}
              {!rows.length && (
                <tr>
                  <td colSpan="7" className="text-center text-muted">No attachments yet.</td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        {/* Add form */}
        <hr />
        <form onSubmit={onSubmit}>
          <div className="row g-3 align-items-end">
            <div className="col-auto">
              <label className="form-label">Type</label>
              <select className="form-select" value={type} onChange={(e) => setType(e.target.value)}>
                <option value="report">Report</option>
                <option value="file">File</option>
              </select>
            </div>

            {type === "report" ? (
              <>
                <div className="col-auto">
                  <label className="form-label">Report ID</label>
                  <input className="form-control" type="number" min="1"
                         value={reportId} onChange={(e) => setReportId(e.target.value)} required />
                </div>
                <div className="col-auto">
                  <label className="form-label">Format</label>
                  <select className="form-select" value={format} onChange={(e) => setFormat(e.target.value)}>
                    <option value="csv">CSV</option>
                    <option value="excel">Excel (XLSX)</option>
                    <option value="json">JSON</option>
                  </select>
                </div>
              </>
            ) : (
              <>
                <div className="col-auto">
                  <label className="form-label">Upload</label>
                  <input className="form-control" type="file"
                         onChange={(e) => setFile(e.target.files?.[0] ?? null)} />
                </div>
                <div className="col-auto">
                  <label className="form-label">or Server Path</label>
                  <input className="form-control" placeholder="/var/www/repweb/public/uploads/..."
                         value={filePath} onChange={(e) => setFilePath(e.target.value)} />
                </div>
              </>
            )}

            <div className="col-auto">
              <label className="form-label">Filename override</label>
              <input className="form-control" placeholder="optional"
                     value={filenameOverride} onChange={(e) => setFilenameOverride(e.target.value)} />
            </div>

            <div className="col-auto">
              <label className="form-label">Position</label>
              <input className="form-control" type="number" min="0" placeholder={String(nextPosition)}
                     value={position} onChange={(e) => setPosition(e.target.value)} />
            </div>

            <div className="col-auto">
              <div className="form-check">
                <input className="form-check-input" type="checkbox" id="att-islink"
                       checked={isLink} onChange={(e) => setIsLink(e.target.checked)} />
                <label htmlFor="att-islink" className="form-check-label">Send as link</label>
              </div>
              <div className="form-check ms-3">
                <input className="form-check-input" type="checkbox" id="att-ispublic"
                       checked={isPublicLink} onChange={(e) => setIsPublicLink(e.target.checked)} disabled={!isLink} />
                <label htmlFor="att-ispublic" className="form-check-label">Public link</label>
              </div>
            </div>

            <div className="col-auto ms-auto">
              <button className="btn btn-primary" disabled={saving || (type === "report" && !reportId)}>
                {saving ? "Saving…" : "Add attachment"}
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  );
}
