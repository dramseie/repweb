// assets/react/components/mail/MailTemplateForm.jsx
import React, { useMemo, useRef, useState } from "react";
import TrumboField from "../common/TrumboField";
import MailAttachmentPanel from "./MailAttachmentPanel";

const emailRegex =
  // simple but robust enough for UI validation
  /^[^\s@]+@[^\s@]+\.[^\s@]+$/i;

function splitEmails(str = "") {
  return str
    .split(/[,\s;]+/)
    .map((s) => s.trim())
    .filter(Boolean);
}

function joinEmails(arr = []) {
  return (arr || []).join(", ");
}

export default function MailTemplateForm({ initial, onSaved, onCancel }) {
  const [tpl, setTpl] = useState(
    initial || {
      NAME: "",
      SUBJECT: "",
      from_email: "",
      reply_to: "",
      body_html: "",
      body_text: "",
      to: [],
      cc: [],
      is_active: true,
      logo_path: null,
    }
  );

  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const fileInputRef = useRef(null);

  const invalidTo = useMemo(
    () => (tpl.to || []).filter((e) => !emailRegex.test(e)),
    [tpl.to]
  );
  const invalidCc = useMemo(
    () => (tpl.cc || []).filter((e) => !emailRegex.test(e)),
    [tpl.cc]
  );
  const fromInvalid = useMemo(
    () => tpl.from_email && !emailRegex.test(tpl.from_email),
    [tpl.from_email]
  );
  const replyInvalid = useMemo(
    () => tpl.reply_to && !emailRegex.test(tpl.reply_to),
    [tpl.reply_to]
  );

  const canSave =
    tpl.NAME.trim() &&
    tpl.SUBJECT.trim() &&
    tpl.from_email.trim() &&
    !fromInvalid &&
    !replyInvalid &&
    invalidTo.length === 0 &&
    invalidCc.length === 0;

  const save = async () => {
    setSaving(true);
    setError("");
    try {
      const resp = await fetch("/api/mail/templates", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(tpl),
      });
      const data = await resp.json().catch(() => ({}));
      if (!resp.ok) throw new Error(data?.error || resp.statusText);

      // If backend returns id on create/update, keep it in local state
      if (data?.id && !tpl.id) setTpl((old) => ({ ...old, id: data.id }));

      onSaved?.(data);
    } catch (e) {
      setError(String(e.message || e));
    } finally {
      setSaving(false);
    }
  };

  const uploadLogo = async (file) => {
    if (!tpl.id || !file) return;
    const fd = new FormData();
    fd.set("file", file);
    const resp = await fetch(`/api/mail/templates/${tpl.id}/logo`, {
      method: "POST",
      body: fd,
    });
    const data = await resp.json().catch(() => ({}));
    if (!resp.ok) {
      alert(data?.error || "Logo upload failed");
      return;
    }
    // Assume backend returns { logo_path, logo_url? }
    setTpl((prev) => ({ ...prev, logo_path: data.logo_path, logo_url: data.logo_url }));
  };

  return (
    <div className="container mt-3">
      <h3 className="mb-3">{tpl.id ? `Edit Template #${tpl.id}` : "New Mail Template"}</h3>

      {error && <div className="alert alert-danger">{error}</div>}

      <div className="row g-3">
        <div className="col-md-6">
          <label className="form-label">Name</label>
          <input
            className="form-control"
            value={tpl.NAME}
            onChange={(e) => setTpl({ ...tpl, NAME: e.target.value })}
            placeholder="Daily Ops Report"
          />
        </div>

        <div className="col-md-6">
          <label className="form-label">Subject</label>
          <input
            className="form-control"
            value={tpl.SUBJECT}
            onChange={(e) => setTpl({ ...tpl, SUBJECT: e.target.value })}
            placeholder="Ops Dashboard – {{date}}"
          />
        </div>

        <div className="col-md-6">
          <label className="form-label">From email</label>
          <input
            className={`form-control ${fromInvalid ? "is-invalid" : ""}`}
            value={tpl.from_email}
            onChange={(e) => setTpl({ ...tpl, from_email: e.target.value.trim() })}
            placeholder="reports@repweb.local"
          />
          {fromInvalid && <div className="invalid-feedback">Invalid email</div>}
        </div>

        <div className="col-md-6">
          <label className="form-label">Reply‑To (optional)</label>
          <input
            className={`form-control ${replyInvalid ? "is-invalid" : ""}`}
            value={tpl.reply_to || ""}
            onChange={(e) => setTpl({ ...tpl, reply_to: e.target.value.trim() })}
            placeholder="team@repweb.local"
          />
          {replyInvalid && <div className="invalid-feedback">Invalid email</div>}
        </div>

        <div className="col-md-12">
          <label className="form-label">To (comma or space separated)</label>
          <input
            className={`form-control ${invalidTo.length ? "is-invalid" : ""}`}
            value={joinEmails(tpl.to)}
            onChange={(e) => setTpl({ ...tpl, to: splitEmails(e.target.value) })}
            placeholder="you@example.com, boss@example.com"
          />
          {!!invalidTo.length && (
            <div className="invalid-feedback">
              Invalid: {invalidTo.join(", ")}
            </div>
          )}
        </div>

        <div className="col-md-12">
          <label className="form-label">Cc (comma or space separated)</label>
          <input
            className={`form-control ${invalidCc.length ? "is-invalid" : ""}`}
            value={joinEmails(tpl.cc)}
            onChange={(e) => setTpl({ ...tpl, cc: splitEmails(e.target.value) })}
            placeholder="audit@example.com"
          />
          {!!invalidCc.length && (
            <div className="invalid-feedback">
              Invalid: {invalidCc.join(", ")}
            </div>
          )}
        </div>

        <div className="col-md-12">
          <div className="form-check mt-2">
            <input
              className="form-check-input"
              type="checkbox"
              id="tpl-active"
              checked={!!tpl.is_active}
              onChange={(e) => setTpl({ ...tpl, is_active: e.target.checked })}
            />
            <label className="form-check-label" htmlFor="tpl-active">
              Active
            </label>
          </div>
        </div>

        <div className="col-md-12">
          <label className="form-label">Body (HTML)</label>
          <TrumboField
            value={tpl.body_html}
            onChange={(html) => setTpl({ ...tpl, body_html: html })}
            height={360}
            trumbowygOptions={{
              // enable when your upload endpoint is ready:
              // plugins: {
              //   upload: {
              //     serverPath: "/api/uploads/images",
              //     fileFieldName: "file",
              //     urlPropertyName: "url"
              //   }
              // }
            }}
          />
        </div>

        {/* Optional plaintext body */}
        <div className="col-md-12">
          <label className="form-label">Plain text (fallback, optional)</label>
          <textarea
            className="form-control"
            rows={4}
            value={tpl.body_text || ""}
            onChange={(e) => setTpl({ ...tpl, body_text: e.target.value })}
            placeholder="If a client can't render HTML, this text is used."
          />
        </div>

        {/* Logo upload (only after template exists) */}
        <div className="col-md-12">
          <label className="form-label d-block">Logo (optional)</label>
          {tpl.logo_url ? (
            <div className="mb-2">
              <img src={tpl.logo_url} alt="Logo" style={{ maxHeight: 48 }} />
            </div>
          ) : tpl.logo_path ? (
            <div className="mb-2">
              <code>{tpl.logo_path}</code>
            </div>
          ) : null}

          <div className="d-flex gap-2">
            <input
              ref={fileInputRef}
              type="file"
              className="form-control"
              disabled={!tpl.id}
              onChange={(e) => uploadLogo(e.target.files?.[0])}
            />
            {!tpl.id && (
              <span className="text-muted align-self-center">
                Save first to enable logo upload
              </span>
            )}
          </div>
        </div>

        <div className="col-12 mt-2">
          <button
            className="btn btn-primary me-2"
            onClick={save}
            disabled={!canSave || saving}
            title={!canSave ? "Fix invalid fields first" : "Save template"}
          >
            {saving ? "Saving…" : "💾 Save"}
          </button>
          <button className="btn btn-secondary" onClick={onCancel}>
            Cancel
          </button>
        </div>
      </div>

      {/* Attachments panel appears once we have an id */}
      {tpl.id ? (
        <MailAttachmentPanel templateId={tpl.id} />
      ) : (
        <div className="alert alert-info mt-4">
          Save the template first to add attachments.
        </div>
      )}
    </div>
  );
}
