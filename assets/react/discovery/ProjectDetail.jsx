import React, { useEffect, useMemo, useState } from 'react';
import { apiDelete, apiGet, apiPatch, apiPost } from './api';

const TABS = [
  { id: 'overview', label: 'Overview' },
  { id: 'applications', label: 'Applications' },
  { id: 'waves', label: 'Waves' },
  { id: 'stakeholders', label: 'Stakeholders' },
  { id: 'sessions', label: 'Sessions' },
];

export default function ProjectDetail({ apiBase, project, selectedWaveId = null, onSelectWave, onProjectUpdated, onProjectDeleted, onRefresh }) {
  const [tab, setTab] = useState('overview');
  const [detail, setDetail] = useState(project);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const [overviewForm, setOverviewForm] = useState(createOverviewState(project));
  const [applicationForm, setApplicationForm] = useState(createApplicationState(project, selectedWaveId));
  const [stakeholderForm, setStakeholderForm] = useState(createStakeholderState());
  const [sessionForm, setSessionForm] = useState(createSessionState());
  const [waveForm, setWaveForm] = useState(createWaveState(project));

  useEffect(() => {
    setDetail(project);
    setOverviewForm(createOverviewState(project));
    setApplicationForm(createApplicationState(project, selectedWaveId));
    setWaveForm(createWaveState(project));
    setTab('overview');
    void fetchProject();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [project.id]);

  useEffect(() => {
    setApplicationForm((prev) => ({ ...prev, waveId: deriveWaveDefault(selectedWaveId) }));
  }, [selectedWaveId]);

  function createOverviewState(data) {
    return {
      name: data.name || '',
      code: data.code || '',
      status: data.status || 'draft',
      ownerEmail: data.ownerEmail || '',
      tenantId: data.tenantId || '',
      legalEntityCi: data.legalEntityCi || '',
      description: data.description || '',
      metadata: data.metadata ? JSON.stringify(data.metadata, null, 2) : '',
    };
  }

  function createApplicationState(data, waveSelection) {
    return {
      tenantId: data.tenantId || '',
      appCi: '',
      appName: '',
      environment: 'prod',
      questionnaireId: '',
      status: 'draft',
      raci: '{}',
      metadata: '{}',
      waveId: deriveWaveDefault(waveSelection),
    };
  }

  function createStakeholderState() {
    return {
      name: '',
      email: '',
      role: '',
      raciRole: '',
      notes: '',
      meta: '{}',
    };
  }

  function createSessionState() {
    return {
      title: '',
      heldAt: '',
      summary: '',
      minutesHtml: '',
      participants: '[]',
      actionItems: '[]',
      createdBy: '',
      sendMail: false,
    };
  }

  function createWaveState(data) {
    return {
      name: '',
      code: '',
      status: 'planned',
      position: (data.waves?.length ?? 0).toString(),
      startAt: '',
      endAt: '',
      metadata: '{}',
    };
  }

  function deriveWaveDefault(waveSelection) {
    if (waveSelection && waveSelection !== 'none') {
      return String(waveSelection);
    }
    return '';
  }

  async function fetchProject() {
    setLoading(true);
    setError(null);
    try {
      const fresh = await apiGet(`${apiBase}/projects/${project.id}`);
      setDetail(fresh);
      setOverviewForm(createOverviewState(fresh));
      setApplicationForm((prev) => ({
        ...createApplicationState(fresh, selectedWaveId),
        ...prev,
        waveId: deriveWaveDefault(selectedWaveId),
      }));
      setWaveForm(createWaveState(fresh));
      onProjectUpdated?.(fresh);
    } catch (err) {
      setError(err.message || 'Failed to load project');
    } finally {
      setLoading(false);
    }
  }

  async function handleSaveOverview(e) {
    e.preventDefault();
    const payload = {
      name: overviewForm.name,
      code: overviewForm.code,
      status: overviewForm.status,
      ownerEmail: overviewForm.ownerEmail || null,
      tenantId: Number(overviewForm.tenantId),
      legalEntityCi: overviewForm.legalEntityCi || null,
      description: overviewForm.description || null,
    };

    if (overviewForm.metadata) {
      try {
        payload.metadata = JSON.parse(overviewForm.metadata);
      } catch (err) {
        setError('Metadata must be valid JSON');
        return;
      }
    } else {
      payload.metadata = null;
    }

    try {
      const updated = await apiPatch(`${apiBase}/projects/${detail.id}`, payload);
      setDetail(updated);
      setOverviewForm(createOverviewState(updated));
      onProjectUpdated?.(updated);
      onRefresh?.();
    } catch (err) {
      setError(err.message || 'Failed to save project');
    }
  }

  async function handleDeleteProject() {
    if (!window.confirm('Delete this project? This action cannot be undone.')) return;
    try {
      await apiDelete(`${apiBase}/projects/${detail.id}`);
      onProjectDeleted?.(detail.id);
      onRefresh?.();
    } catch (err) {
      setError(err.message || 'Failed to delete project');
    }
  }

  async function handleCreateApplication(e) {
    e.preventDefault();
    const payload = {
      tenantId: Number(applicationForm.tenantId),
      appCi: applicationForm.appCi,
      appName: applicationForm.appName,
      environment: applicationForm.environment || null,
      questionnaireId: applicationForm.questionnaireId ? Number(applicationForm.questionnaireId) : null,
      status: applicationForm.status,
    };

    try {
      payload.raci = applicationForm.raci ? JSON.parse(applicationForm.raci) : null;
    } catch (err) {
      setError('RACI must be valid JSON');
      return;
    }

    try {
      payload.metadata = applicationForm.metadata ? JSON.parse(applicationForm.metadata) : null;
    } catch (err) {
      setError('Metadata must be valid JSON');
      return;
    }

    try {
      payload.waveId = applicationForm.waveId ? Number(applicationForm.waveId) : null;

      await apiPost(`${apiBase}/projects/${detail.id}/applications`, payload);
      setApplicationForm(createApplicationState(detail, selectedWaveId));
      await fetchProject();
      onRefresh?.();
    } catch (err) {
      setError(err.message || 'Failed to create application');
    }
  }

  async function updateApplication(appId, patch) {
    try {
      await apiPatch(`${apiBase}/applications/${appId}`, patch);
      await fetchProject();
      onRefresh?.();
    } catch (err) {
      setError(err.message || 'Failed to update application');
    }
  }

  async function deleteApplication(appId) {
    if (!window.confirm('Delete this application?')) return;
    try {
      await apiDelete(`${apiBase}/applications/${appId}`);
      await fetchProject();
      onRefresh?.();
    } catch (err) {
      setError(err.message || 'Failed to delete application');
    }
  }

  async function cloneAnswers(targetId, sourceId) {
    try {
      const result = await apiPost(`${apiBase}/applications/${targetId}/clone`, {
        sourceApplicationId: sourceId,
      });
      await fetchProject();
      onRefresh?.();
      window.alert(`Cloned ${result.answersCopied} answers and ${result.attachmentsCopied} attachments.`);
    } catch (err) {
      setError(err.message || 'Failed to clone answers');
    }
  }

  async function handleCreateStakeholder(e) {
    e.preventDefault();
    const payload = {
      name: stakeholderForm.name,
      email: stakeholderForm.email || null,
      role: stakeholderForm.role || null,
      raciRole: stakeholderForm.raciRole || null,
      notes: stakeholderForm.notes || null,
    };
    try {
      payload.meta = stakeholderForm.meta ? JSON.parse(stakeholderForm.meta) : null;
    } catch (err) {
      setError('Stakeholder meta must be valid JSON');
      return;
    }
    try {
      await apiPost(`${apiBase}/projects/${detail.id}/stakeholders`, payload);
      setStakeholderForm(createStakeholderState());
      await fetchProject();
      onRefresh?.();
    } catch (err) {
      setError(err.message || 'Failed to create stakeholder');
    }
  }

  async function updateStakeholder(id, patch) {
    try {
      await apiPatch(`${apiBase}/stakeholders/${id}`, patch);
      await fetchProject();
      onRefresh?.();
    } catch (err) {
      setError(err.message || 'Failed to update stakeholder');
    }
  }

  async function deleteStakeholder(id) {
    if (!window.confirm('Delete this stakeholder?')) return;
    try {
      await apiDelete(`${apiBase}/stakeholders/${id}`);
      await fetchProject();
      onRefresh?.();
    } catch (err) {
      setError(err.message || 'Failed to delete stakeholder');
    }
  }

  async function handleCreateSession(e) {
    e.preventDefault();
    const payload = {
      title: sessionForm.title,
      heldAt: sessionForm.heldAt || null,
      summary: sessionForm.summary || null,
      minutesHtml: sessionForm.minutesHtml || null,
      createdBy: sessionForm.createdBy || null,
      sendMail: sessionForm.sendMail,
    };
    try {
      payload.participants = sessionForm.participants ? JSON.parse(sessionForm.participants) : null;
    } catch (err) {
      setError('Participants must be valid JSON');
      return;
    }
    try {
      payload.actionItems = sessionForm.actionItems ? JSON.parse(sessionForm.actionItems) : null;
    } catch (err) {
      setError('Action items must be valid JSON');
      return;
    }

    try {
      await apiPost(`${apiBase}/projects/${detail.id}/sessions`, payload);
      setSessionForm(createSessionState());
      await fetchProject();
      onRefresh?.();
    } catch (err) {
      setError(err.message || 'Failed to create session');
    }
  }

  async function sendSessionMail(id) {
    try {
      await apiPost(`${apiBase}/sessions/${id}/send-mail`, {});
      await fetchProject();
      onRefresh?.();
    } catch (err) {
      setError(err.message || 'Failed to send session email');
    }
  }

  const otherApplications = useMemo(() => (detail.applications || []).map((a) => ({ id: a.id, name: a.appName })), [detail.applications]);
  const waveLookup = useMemo(() => {
    const map = new Map();
    (detail.waves || []).forEach((wave) => {
      map.set(wave.id, wave.name);
    });
    return map;
  }, [detail.waves]);

  const filteredApplications = useMemo(() => {
    if (!detail.applications) return [];
    if (selectedWaveId === null) return detail.applications;
    if (selectedWaveId === 'none') {
      return detail.applications.filter((app) => !app.waveId);
    }
    return detail.applications.filter((app) => app.waveId === selectedWaveId);
  }, [detail.applications, selectedWaveId]);

  const waveFilterLabel = useMemo(() => {
    if (selectedWaveId === 'none') return 'Unassigned applications';
    if (selectedWaveId === null) return null;
    const name = waveLookup.get(selectedWaveId) || 'Wave';
    return `Wave: ${name}`;
  }, [selectedWaveId, waveLookup]);

  async function handleCreateWave(e) {
    e.preventDefault();
    if (!waveForm.name.trim()) {
      setError('Wave name is required');
      return;
    }

    const payload = {
      name: waveForm.name,
      code: waveForm.code || null,
      status: waveForm.status,
      position: waveForm.position !== '' ? Number(waveForm.position) : undefined,
      startAt: waveForm.startAt || null,
      endAt: waveForm.endAt || null,
    };

    if (waveForm.metadata) {
      try {
        payload.metadata = JSON.parse(waveForm.metadata);
      } catch (err) {
        setError('Wave metadata must be valid JSON');
        return;
      }
    } else {
      payload.metadata = null;
    }

    try {
      await apiPost(`${apiBase}/projects/${detail.id}/waves`, payload);
      const nextPosition = ((detail.waves?.length ?? 0) + 1).toString();
      setWaveForm({
        name: '',
        code: '',
        status: 'planned',
        position: nextPosition,
        startAt: '',
        endAt: '',
        metadata: '{}',
      });
      await fetchProject();
      onRefresh?.();
    } catch (err) {
      setError(err.message || 'Failed to create wave');
    }
  }

  async function updateWave(id, patch) {
    try {
      await apiPatch(`${apiBase}/waves/${id}`, patch);
      await fetchProject();
      onRefresh?.();
    } catch (err) {
      setError(err.message || 'Failed to update wave');
    }
  }

  async function deleteWave(id) {
    if (!window.confirm('Delete this wave? Applications will remain but lose the wave link.')) return;
    try {
      await apiDelete(`${apiBase}/waves/${id}`);
      if (selectedWaveId === id) {
        onSelectWave?.(null);
      }
      await fetchProject();
      onRefresh?.();
    } catch (err) {
      setError(err.message || 'Failed to delete wave');
    }
  }

  return (
    <div className="project-detail py-2">
      <div className="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h3 className="mb-0">{detail.name}</h3>
          <small className="text-muted">{detail.code}</small>
        </div>
        <div className="d-flex gap-2">
          <button className="btn btn-sm btn-outline-secondary" onClick={() => setTab('waves')}>Manage waves</button>
          <button className="btn btn-outline-danger btn-sm" onClick={handleDeleteProject}>Delete project</button>
        </div>
      </div>

      {error && <div className="alert alert-danger py-2">{error}</div>}
      {loading && <div className="alert alert-info py-2">Loading…</div>}

      <ul className="nav nav-tabs mb-3">
        {TABS.map((t) => (
          <li className="nav-item" key={t.id}>
            <button
              type="button"
              className={`nav-link ${tab === t.id ? 'active' : ''}`}
              onClick={() => setTab(t.id)}
            >
              {t.label}
            </button>
          </li>
        ))}
      </ul>

      {tab === 'overview' && (
        <form className="card card-body" onSubmit={handleSaveOverview}>
          <div className="row g-3">
            <div className="col-md-6">
              <label className="form-label">Name</label>
              <input
                className="form-control"
                value={overviewForm.name}
                onChange={(e) => setOverviewForm((prev) => ({ ...prev, name: e.target.value }))}
                required
              />
            </div>
            <div className="col-md-3">
              <label className="form-label">Code</label>
              <input
                className="form-control"
                value={overviewForm.code}
                onChange={(e) => setOverviewForm((prev) => ({ ...prev, code: e.target.value }))}
                required
              />
            </div>
            <div className="col-md-3">
              <label className="form-label">Status</label>
              <select
                className="form-select"
                value={overviewForm.status}
                onChange={(e) => setOverviewForm((prev) => ({ ...prev, status: e.target.value }))}
              >
                <option value="draft">Draft</option>
                <option value="active">Active</option>
                <option value="closed">Closed</option>
              </select>
            </div>
            <div className="col-md-4">
              <label className="form-label">Owner email</label>
              <input
                type="email"
                className="form-control"
                value={overviewForm.ownerEmail}
                onChange={(e) => setOverviewForm((prev) => ({ ...prev, ownerEmail: e.target.value }))}
              />
            </div>
            <div className="col-md-4">
              <label className="form-label">Tenant ID</label>
              <input
                type="number"
                className="form-control"
                value={overviewForm.tenantId}
                onChange={(e) => setOverviewForm((prev) => ({ ...prev, tenantId: e.target.value }))}
                required
              />
            </div>
            <div className="col-md-4">
              <label className="form-label">Legal entity CI</label>
              <input
                className="form-control"
                value={overviewForm.legalEntityCi}
                onChange={(e) => setOverviewForm((prev) => ({ ...prev, legalEntityCi: e.target.value }))}
              />
            </div>
            <div className="col-12">
              <label className="form-label">Description</label>
              <textarea
                className="form-control"
                rows={3}
                value={overviewForm.description}
                onChange={(e) => setOverviewForm((prev) => ({ ...prev, description: e.target.value }))}
              />
            </div>
            <div className="col-12">
              <label className="form-label">Metadata (JSON)</label>
              <textarea
                className="form-control font-monospace"
                rows={4}
                value={overviewForm.metadata}
                onChange={(e) => setOverviewForm((prev) => ({ ...prev, metadata: e.target.value }))}
              />
            </div>
          </div>
          <div className="mt-3">
            <button className="btn btn-primary" type="submit">Save changes</button>
          </div>
        </form>
      )}

      {tab === 'applications' && (
        <div className="row g-3">
          <div className="col-lg-5">
            <form className="card card-body" onSubmit={handleCreateApplication}>
              <h5>Create application</h5>
              <div className="mb-2">
                <label className="form-label">Tenant ID</label>
                <input
                  type="number"
                  className="form-control form-control-sm"
                  value={applicationForm.tenantId}
                  onChange={(e) => setApplicationForm((prev) => ({ ...prev, tenantId: e.target.value }))}
                  required
                />
              </div>
              <div className="mb-2">
                <label className="form-label">App CI</label>
                <input
                  className="form-control form-control-sm"
                  value={applicationForm.appCi}
                  onChange={(e) => setApplicationForm((prev) => ({ ...prev, appCi: e.target.value }))}
                  required
                />
              </div>
              <div className="mb-2">
                <label className="form-label">App name</label>
                <input
                  className="form-control form-control-sm"
                  value={applicationForm.appName}
                  onChange={(e) => setApplicationForm((prev) => ({ ...prev, appName: e.target.value }))}
                  required
                />
              </div>
              <div className="mb-2">
                <label className="form-label">Environment</label>
                <input
                  className="form-control form-control-sm"
                  value={applicationForm.environment}
                  onChange={(e) => setApplicationForm((prev) => ({ ...prev, environment: e.target.value }))}
                />
              </div>
              <div className="mb-2">
                <label className="form-label">Questionnaire ID</label>
                <input
                  type="number"
                  className="form-control form-control-sm"
                  value={applicationForm.questionnaireId}
                  onChange={(e) => setApplicationForm((prev) => ({ ...prev, questionnaireId: e.target.value }))}
                />
              </div>
              <div className="mb-2">
                <label className="form-label">Status</label>
                <select
                  className="form-select form-select-sm"
                  value={applicationForm.status}
                  onChange={(e) => setApplicationForm((prev) => ({ ...prev, status: e.target.value }))}
                >
                  <option value="draft">Draft</option>
                  <option value="in_progress">In progress</option>
                  <option value="completed">Completed</option>
                  <option value="archived">Archived</option>
                </select>
              </div>
              <div className="mb-2">
                <label className="form-label">Wave</label>
                <select
                  className="form-select form-select-sm"
                  value={applicationForm.waveId}
                  onChange={(e) => setApplicationForm((prev) => ({ ...prev, waveId: e.target.value }))}
                >
                  <option value="">(none)</option>
                  {(detail.waves || []).map((wave) => (
                    <option key={wave.id} value={wave.id}>{wave.name}</option>
                  ))}
                </select>
              </div>
              <div className="mb-2">
                <label className="form-label">RACI (JSON)</label>
                <textarea
                  className="form-control form-control-sm font-monospace"
                  rows={3}
                  value={applicationForm.raci}
                  onChange={(e) => setApplicationForm((prev) => ({ ...prev, raci: e.target.value }))}
                />
              </div>
              <div className="mb-3">
                <label className="form-label">Metadata (JSON)</label>
                <textarea
                  className="form-control form-control-sm font-monospace"
                  rows={3}
                  value={applicationForm.metadata}
                  onChange={(e) => setApplicationForm((prev) => ({ ...prev, metadata: e.target.value }))}
                />
              </div>
              <button className="btn btn-sm btn-success" type="submit">Create</button>
            </form>
          </div>
          <div className="col-lg-7">
            {waveFilterLabel && (
              <div className="alert alert-secondary py-2 d-flex justify-content-between align-items-center">
                <span>{waveFilterLabel}</span>
                <button className="btn btn-sm btn-outline-secondary" type="button" onClick={() => onSelectWave?.(null)}>Clear</button>
              </div>
            )}
            {filteredApplications.map((app) => (
              <div className="card mb-3" key={app.id}>
                <div className="card-body">
                  <div className="d-flex justify-content-between">
                    <div>
                      <h5 className="card-title mb-1">{app.appName}</h5>
                      <small className="text-muted">{app.appCi}</small>
                    </div>
                    <div>
                      <button className="btn btn-sm btn-outline-danger" onClick={() => deleteApplication(app.id)}>Delete</button>
                    </div>
                  </div>
                  <div className="row mt-2">
                    <div className="col-md-4">
                      <label className="form-label">Status</label>
                      <select
                        className="form-select form-select-sm"
                        value={app.status}
                        onChange={(e) => updateApplication(app.id, { status: e.target.value })}
                      >
                        <option value="draft">Draft</option>
                        <option value="in_progress">In progress</option>
                        <option value="completed">Completed</option>
                        <option value="archived">Archived</option>
                      </select>
                    </div>
                    <div className="col-md-4">
                      <label className="form-label">Environment</label>
                      <input
                        className="form-control form-control-sm"
                        value={app.environment || ''}
                        onChange={(e) => updateApplication(app.id, { environment: e.target.value })}
                      />
                    </div>
                    <div className="col-md-4">
                      <label className="form-label">Questionnaire ID</label>
                      <input
                        type="number"
                        className="form-control form-control-sm"
                        value={app.questionnaireId || ''}
                        onChange={(e) => updateApplication(app.id, { questionnaireId: e.target.value ? Number(e.target.value) : null })}
                      />
                    </div>
                    <div className="col-md-4">
                      <label className="form-label">Wave</label>
                      <select
                        className="form-select form-select-sm"
                        value={app.waveId ?? ''}
                        onChange={(e) => updateApplication(app.id, { waveId: e.target.value ? Number(e.target.value) : null })}
                      >
                        <option value="">(none)</option>
                        {(detail.waves || []).map((wave) => (
                          <option key={wave.id} value={wave.id}>{wave.name}</option>
                        ))}
                      </select>
                      {app.waveId && (
                        <small className="text-muted">{waveLookup.get(app.waveId)}</small>
                      )}
                    </div>
                  </div>
                  <div className="mt-3 d-flex align-items-center gap-2">
                    <select
                      className="form-select form-select-sm w-auto"
                      onChange={(e) => {
                        const val = Number(e.target.value);
                        if (!val) return;
                        cloneAnswers(app.id, val);
                        e.target.value = '';
                      }}
                    >
                      <option value="">Clone answers from…</option>
                      {otherApplications
                        .filter((o) => o.id !== app.id)
                        .map((o) => (
                          <option key={o.id} value={o.id}>{o.name}</option>
                        ))}
                    </select>
                    <span className="text-muted small">Responses: {app.responses?.length ?? 0}</span>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {tab === 'waves' && (
        <div className="row g-3">
          <div className="col-lg-4">
            <form className="card card-body" onSubmit={handleCreateWave}>
              <h5>Create wave</h5>
              <div className="mb-2">
                <label className="form-label">Name</label>
                <input
                  className="form-control form-control-sm"
                  value={waveForm.name}
                  onChange={(e) => setWaveForm((prev) => ({ ...prev, name: e.target.value }))}
                  required
                />
              </div>
              <div className="mb-2">
                <label className="form-label">Code</label>
                <input
                  className="form-control form-control-sm"
                  value={waveForm.code}
                  onChange={(e) => setWaveForm((prev) => ({ ...prev, code: e.target.value }))}
                />
              </div>
              <div className="mb-2">
                <label className="form-label">Status</label>
                <select
                  className="form-select form-select-sm"
                  value={waveForm.status}
                  onChange={(e) => setWaveForm((prev) => ({ ...prev, status: e.target.value }))}
                >
                  <option value="planned">Planned</option>
                  <option value="in_progress">In progress</option>
                  <option value="complete">Complete</option>
                  <option value="archived">Archived</option>
                </select>
              </div>
              <div className="mb-2">
                <label className="form-label">Position</label>
                <input
                  type="number"
                  className="form-control form-control-sm"
                  value={waveForm.position}
                  onChange={(e) => setWaveForm((prev) => ({ ...prev, position: e.target.value }))}
                />
              </div>
              <div className="mb-2">
                <label className="form-label">Start date</label>
                <input
                  type="date"
                  className="form-control form-control-sm"
                  value={waveForm.startAt}
                  onChange={(e) => setWaveForm((prev) => ({ ...prev, startAt: e.target.value }))}
                />
              </div>
              <div className="mb-2">
                <label className="form-label">End date</label>
                <input
                  type="date"
                  className="form-control form-control-sm"
                  value={waveForm.endAt}
                  onChange={(e) => setWaveForm((prev) => ({ ...prev, endAt: e.target.value }))}
                />
              </div>
              <div className="mb-3">
                <label className="form-label">Metadata (JSON)</label>
                <textarea
                  className="form-control form-control-sm font-monospace"
                  rows={3}
                  value={waveForm.metadata}
                  onChange={(e) => setWaveForm((prev) => ({ ...prev, metadata: e.target.value }))}
                />
              </div>
              <button className="btn btn-sm btn-success" type="submit">Create wave</button>
            </form>
          </div>
          <div className="col-lg-8">
            {(detail.waves || []).map((wave) => (
              <div className="card mb-3" key={`${wave.id}-${wave.updatedAt}`}>
                <div className="card-body">
                  <div className="d-flex justify-content-between align-items-center mb-2">
                    <div>
                      <h5 className="card-title mb-0">{wave.name}</h5>
                      <small className="text-muted">Applications: {wave.applicationCount ?? 0}</small>
                    </div>
                    <div className="d-flex gap-2">
                      <button
                        className="btn btn-sm btn-outline-primary"
                        type="button"
                        onClick={() => {
                          onSelectWave?.(wave.id);
                          setTab('applications');
                        }}
                      >
                        View apps
                      </button>
                      <button className="btn btn-sm btn-outline-danger" type="button" onClick={() => deleteWave(wave.id)}>Delete</button>
                    </div>
                  </div>
                  <div className="row g-2">
                    <div className="col-md-4">
                      <label className="form-label">Name</label>
                      <input
                        className="form-control form-control-sm"
                        defaultValue={wave.name}
                        onBlur={(e) => {
                          const value = e.target.value.trim();
                          if (value && value !== wave.name) {
                            updateWave(wave.id, { name: value });
                          }
                        }}
                      />
                    </div>
                    <div className="col-md-4">
                      <label className="form-label">Code</label>
                      <input
                        className="form-control form-control-sm"
                        defaultValue={wave.code || ''}
                        onBlur={(e) => {
                          const value = e.target.value.trim();
                          if (value !== (wave.code || '')) {
                            updateWave(wave.id, { code: value || null });
                          }
                        }}
                      />
                    </div>
                    <div className="col-md-4">
                      <label className="form-label">Status</label>
                      <select
                        className="form-select form-select-sm"
                        defaultValue={wave.status}
                        onChange={(e) => updateWave(wave.id, { status: e.target.value })}
                      >
                        <option value="planned">Planned</option>
                        <option value="in_progress">In progress</option>
                        <option value="complete">Complete</option>
                        <option value="archived">Archived</option>
                      </select>
                    </div>
                    <div className="col-md-4">
                      <label className="form-label">Position</label>
                      <input
                        type="number"
                        className="form-control form-control-sm"
                        defaultValue={wave.position}
                        onBlur={(e) => {
                          const numeric = Number(e.target.value);
                          if (!Number.isNaN(numeric) && numeric !== wave.position) {
                            updateWave(wave.id, { position: numeric });
                          }
                        }}
                      />
                    </div>
                    <div className="col-md-4">
                      <label className="form-label">Start date</label>
                      <input
                        type="date"
                        className="form-control form-control-sm"
                        defaultValue={wave.startAt ? wave.startAt.slice(0, 10) : ''}
                        onBlur={(e) => {
                          const value = e.target.value;
                          if (value !== (wave.startAt ? wave.startAt.slice(0, 10) : '')) {
                            updateWave(wave.id, { startAt: value || null });
                          }
                        }}
                      />
                    </div>
                    <div className="col-md-4">
                      <label className="form-label">End date</label>
                      <input
                        type="date"
                        className="form-control form-control-sm"
                        defaultValue={wave.endAt ? wave.endAt.slice(0, 10) : ''}
                        onBlur={(e) => {
                          const value = e.target.value;
                          if (value !== (wave.endAt ? wave.endAt.slice(0, 10) : '')) {
                            updateWave(wave.id, { endAt: value || null });
                          }
                        }}
                      />
                    </div>
                    <div className="col-12">
                      <label className="form-label">Metadata (JSON)</label>
                      <textarea
                        className="form-control form-control-sm font-monospace"
                        rows={3}
                        defaultValue={wave.metadata ? JSON.stringify(wave.metadata, null, 2) : ''}
                        onBlur={(e) => {
                          const value = e.target.value;
                          try {
                            const parsed = value ? JSON.parse(value) : null;
                            const current = wave.metadata ? JSON.stringify(wave.metadata, null, 2) : '';
                            if (value !== current) {
                              updateWave(wave.id, { metadata: parsed });
                            }
                          } catch (parseErr) {
                            window.alert('Metadata must be valid JSON');
                          }
                        }}
                      />
                    </div>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {tab === 'stakeholders' && (
        <div className="row g-3">
          <div className="col-lg-4">
            <form className="card card-body" onSubmit={handleCreateStakeholder}>
              <h5>New stakeholder</h5>
              <div className="mb-2">
                <label className="form-label">Name</label>
                <input
                  className="form-control form-control-sm"
                  value={stakeholderForm.name}
                  onChange={(e) => setStakeholderForm((prev) => ({ ...prev, name: e.target.value }))}
                  required
                />
              </div>
              <div className="mb-2">
                <label className="form-label">Email</label>
                <input
                  type="email"
                  className="form-control form-control-sm"
                  value={stakeholderForm.email}
                  onChange={(e) => setStakeholderForm((prev) => ({ ...prev, email: e.target.value }))}
                />
              </div>
              <div className="mb-2">
                <label className="form-label">Role</label>
                <input
                  className="form-control form-control-sm"
                  value={stakeholderForm.role}
                  onChange={(e) => setStakeholderForm((prev) => ({ ...prev, role: e.target.value }))}
                />
              </div>
              <div className="mb-2">
                <label className="form-label">RACI role</label>
                <select
                  className="form-select form-select-sm"
                  value={stakeholderForm.raciRole}
                  onChange={(e) => setStakeholderForm((prev) => ({ ...prev, raciRole: e.target.value }))}
                >
                  <option value="">(none)</option>
                  <option value="r">Responsible</option>
                  <option value="a">Accountable</option>
                  <option value="c">Consulted</option>
                  <option value="i">Informed</option>
                </select>
              </div>
              <div className="mb-2">
                <label className="form-label">Notes</label>
                <textarea
                  className="form-control form-control-sm"
                  rows={2}
                  value={stakeholderForm.notes}
                  onChange={(e) => setStakeholderForm((prev) => ({ ...prev, notes: e.target.value }))}
                />
              </div>
              <div className="mb-3">
                <label className="form-label">Meta (JSON)</label>
                <textarea
                  className="form-control form-control-sm font-monospace"
                  rows={3}
                  value={stakeholderForm.meta}
                  onChange={(e) => setStakeholderForm((prev) => ({ ...prev, meta: e.target.value }))}
                />
              </div>
              <button className="btn btn-sm btn-success" type="submit">Create</button>
            </form>
          </div>
          <div className="col-lg-8">
            {(detail.stakeholders || []).map((stakeholder) => (
              <div className="card mb-3" key={stakeholder.id}>
                <div className="card-body">
                  <div className="d-flex justify-content-between">
                    <div>
                      <h5 className="card-title mb-1">{stakeholder.name}</h5>
                      <small className="text-muted">{stakeholder.role}</small>
                      {stakeholder.email && (
                        <div className="small">{stakeholder.email}</div>
                      )}
                    </div>
                    <button className="btn btn-sm btn-outline-danger" onClick={() => deleteStakeholder(stakeholder.id)}>Remove</button>
                  </div>
                  <div className="row mt-2">
                    <div className="col-md-4">
                      <label className="form-label">RACI</label>
                      <select
                        className="form-select form-select-sm"
                        value={stakeholder.raciRole || ''}
                        onChange={(e) => updateStakeholder(stakeholder.id, { raciRole: e.target.value || null })}
                      >
                        <option value="">(none)</option>
                        <option value="r">Responsible</option>
                        <option value="a">Accountable</option>
                        <option value="c">Consulted</option>
                        <option value="i">Informed</option>
                      </select>
                    </div>
                    <div className="col-md-8">
                      <label className="form-label">Notes</label>
                      <textarea
                        className="form-control form-control-sm"
                        rows={2}
                        value={stakeholder.notes || ''}
                        onChange={(e) => updateStakeholder(stakeholder.id, { notes: e.target.value })}
                      />
                    </div>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {tab === 'sessions' && (
        <div className="row g-3">
          <div className="col-lg-5">
            <form className="card card-body" onSubmit={handleCreateSession}>
              <h5>Log session</h5>
              <div className="mb-2">
                <label className="form-label">Title</label>
                <input
                  className="form-control form-control-sm"
                  value={sessionForm.title}
                  onChange={(e) => setSessionForm((prev) => ({ ...prev, title: e.target.value }))}
                  required
                />
              </div>
              <div className="mb-2">
                <label className="form-label">Held at (ISO)</label>
                <input
                  className="form-control form-control-sm"
                  value={sessionForm.heldAt}
                  onChange={(e) => setSessionForm((prev) => ({ ...prev, heldAt: e.target.value }))}
                  placeholder="2025-10-28T14:00:00"
                />
              </div>
              <div className="mb-2">
                <label className="form-label">Summary</label>
                <textarea
                  className="form-control form-control-sm"
                  rows={2}
                  value={sessionForm.summary}
                  onChange={(e) => setSessionForm((prev) => ({ ...prev, summary: e.target.value }))}
                />
              </div>
              <div className="mb-2">
                <label className="form-label">Minutes (HTML)</label>
                <textarea
                  className="form-control form-control-sm"
                  rows={3}
                  value={sessionForm.minutesHtml}
                  onChange={(e) => setSessionForm((prev) => ({ ...prev, minutesHtml: e.target.value }))}
                />
              </div>
              <div className="mb-2">
                <label className="form-label">Participants (JSON array)</label>
                <textarea
                  className="form-control form-control-sm font-monospace"
                  rows={2}
                  value={sessionForm.participants}
                  onChange={(e) => setSessionForm((prev) => ({ ...prev, participants: e.target.value }))}
                />
              </div>
              <div className="mb-2">
                <label className="form-label">Action items (JSON array)</label>
                <textarea
                  className="form-control form-control-sm font-monospace"
                  rows={2}
                  value={sessionForm.actionItems}
                  onChange={(e) => setSessionForm((prev) => ({ ...prev, actionItems: e.target.value }))}
                />
              </div>
              <div className="mb-2">
                <label className="form-label">Created by</label>
                <input
                  className="form-control form-control-sm"
                  value={sessionForm.createdBy}
                  onChange={(e) => setSessionForm((prev) => ({ ...prev, createdBy: e.target.value }))}
                />
              </div>
              <div className="form-check mb-3">
                <input
                  className="form-check-input"
                  type="checkbox"
                  id="discovery-send-mail"
                  checked={sessionForm.sendMail}
                  onChange={(e) => setSessionForm((prev) => ({ ...prev, sendMail: e.target.checked }))}
                />
                <label className="form-check-label" htmlFor="discovery-send-mail">Send recap email</label>
              </div>
              <button className="btn btn-sm btn-success" type="submit">Create session</button>
            </form>
          </div>
          <div className="col-lg-7">
            {(detail.sessions || []).map((session) => (
              <div className="card mb-3" key={session.id}>
                <div className="card-body">
                  <div className="d-flex justify-content-between align-items-center">
                    <div>
                      <h5 className="card-title mb-1">{session.title}</h5>
                      <small className="text-muted">{session.heldAt ? new Date(session.heldAt).toLocaleString() : 'Draft'}</small>
                    </div>
                    <button className="btn btn-sm btn-outline-primary" onClick={() => sendSessionMail(session.id)}>Send mail</button>
                  </div>
                  {session.summary && <p className="mt-2 mb-0">{session.summary}</p>}
                  <div className="small text-muted mt-2">
                    Mail status: {session.mailStatus}{session.mailError ? ` (${session.mailError})` : ''}
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
