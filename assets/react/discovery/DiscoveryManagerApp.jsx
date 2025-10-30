import React, { useEffect, useMemo, useState } from 'react';
import { apiGet, apiPost } from './api';
import ProjectDetail from './ProjectDetail.jsx';

export default function DiscoveryManagerApp({ apiBase = '/api/discovery' }) {
  const [projects, setProjects] = useState([]);
  const [selectedId, setSelectedId] = useState(null);
  const [selectedWaveId, setSelectedWaveId] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [showCreate, setShowCreate] = useState(false);
  const [newProject, setNewProject] = useState({ tenantId: '', code: '', name: '', ownerEmail: '' });

  async function loadProjects() {
    setLoading(true);
    setError(null);
    try {
      const data = await apiGet(`${apiBase}/projects`);
      setProjects(data);
      if (data.length && !selectedId) {
        setSelectedId(data[0].id);
      }
    } catch (err) {
      setError(err.message || 'Failed to load projects');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadProjects();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [apiBase]);

  const selectedProject = useMemo(
    () => projects.find((p) => p.id === selectedId) || null,
    [projects, selectedId]
  );

  async function handleCreateProject(e) {
    e.preventDefault();
    const payload = {
      tenantId: Number(newProject.tenantId),
      code: newProject.code,
      name: newProject.name,
      ownerEmail: newProject.ownerEmail || null,
    };
    if (!payload.tenantId || !payload.code || !payload.name) {
      setError('Tenant, code and name are required');
      return;
    }
    try {
      const created = await apiPost(`${apiBase}/projects`, payload);
      setProjects((prev) => [created, ...prev]);
      setSelectedId(created.id);
      setSelectedWaveId(null);
      setShowCreate(false);
      setNewProject({ tenantId: '', code: '', name: '', ownerEmail: '' });
    } catch (err) {
      setError(err.message || 'Failed to create project');
    }
  }

  function handleProjectUpdated(updated) {
    setProjects((prev) => prev.map((p) => (p.id === updated.id ? updated : p)));
  }

  function handleProjectDeleted(id) {
    setProjects((prev) => prev.filter((p) => p.id !== id));
    if (selectedId === id) {
      setSelectedId(null);
      setSelectedWaveId(null);
    }
  }

  const selectedProjectWaves = selectedProject?.waves ?? [];
  const selectedProjectApps = selectedProject?.applications ?? [];
  const unassignedCount = useMemo(
    () => selectedProjectApps.filter((app) => !app.waveId).length,
    [selectedProjectApps]
  );

  return (
    <div className="container-fluid py-3 discovery-manager">
      <div className="row">
        <div className="col-md-3 border-end">
          <div className="d-flex align-items-center justify-content-between mb-2">
            <h4 className="mb-0">Projects</h4>
            <button className="btn btn-sm btn-primary" onClick={() => setShowCreate((v) => !v)}>
              {showCreate ? 'Close' : 'New'}
            </button>
          </div>
          {error && <div className="alert alert-danger py-1">{error}</div>}
          {showCreate && (
            <form className="card card-body mb-3" onSubmit={handleCreateProject}>
              <div className="mb-2">
                <label className="form-label">Tenant ID</label>
                <input
                  type="number"
                  className="form-control form-control-sm"
                  value={newProject.tenantId}
                  onChange={(e) => setNewProject((prev) => ({ ...prev, tenantId: e.target.value }))}
                  required
                />
              </div>
              <div className="mb-2">
                <label className="form-label">Code</label>
                <input
                  type="text"
                  className="form-control form-control-sm"
                  value={newProject.code}
                  onChange={(e) => setNewProject((prev) => ({ ...prev, code: e.target.value }))}
                  required
                />
              </div>
              <div className="mb-2">
                <label className="form-label">Name</label>
                <input
                  type="text"
                  className="form-control form-control-sm"
                  value={newProject.name}
                  onChange={(e) => setNewProject((prev) => ({ ...prev, name: e.target.value }))}
                  required
                />
              </div>
              <div className="mb-3">
                <label className="form-label">Owner email</label>
                <input
                  type="email"
                  className="form-control form-control-sm"
                  value={newProject.ownerEmail}
                  onChange={(e) => setNewProject((prev) => ({ ...prev, ownerEmail: e.target.value }))}
                />
              </div>
              <button className="btn btn-sm btn-success" type="submit">Create</button>
            </form>
          )}
          <div className="list-group">
            {loading && <div className="list-group-item">Loading…</div>}
            {!loading && projects.length === 0 && <div className="list-group-item">No projects yet.</div>}
            {projects.map((project) => (
              <button
                key={project.id}
                type="button"
                className={`list-group-item list-group-item-action ${selectedId === project.id ? 'active' : ''}`}
                onClick={() => {
                  setSelectedId(project.id);
                  setSelectedWaveId(null);
                }}
              >
                <div className="d-flex justify-content-between align-items-center">
                  <span>{project.name}</span>
                  <span className="badge bg-light text-dark">{project.status}</span>
                </div>
                <small className="d-block text-muted">{project.code}</small>
                <small className="d-block text-muted">Apps: {project.applicationCount ?? project.applications?.length ?? 0}</small>
                {typeof project.waveCount === 'number' && (
                  <small className="d-block text-muted">Waves: {project.waveCount}</small>
                )}
              </button>
            ))}
          </div>
          {selectedProject && (
            <div className="mt-3">
              <div className="d-flex align-items-center justify-content-between mb-2">
                <h6 className="mb-0">Waves</h6>
                <button
                  className="btn btn-link btn-sm px-0"
                  onClick={() => setSelectedWaveId(null)}
                  type="button"
                >
                  Clear
                </button>
              </div>
              <div className="list-group">
                <button
                  type="button"
                  className={`list-group-item list-group-item-action ${selectedWaveId === null ? 'active' : ''}`}
                  onClick={() => setSelectedWaveId(null)}
                >
                  <div className="d-flex justify-content-between align-items-center">
                    <span>All applications</span>
                    <span className="badge bg-light text-dark">{selectedProjectApps.length}</span>
                  </div>
                </button>
                <button
                  type="button"
                  className={`list-group-item list-group-item-action ${selectedWaveId === 'none' ? 'active' : ''}`}
                  onClick={() => setSelectedWaveId('none')}
                >
                  <div className="d-flex justify-content-between align-items-center">
                    <span>Unassigned</span>
                    <span className="badge bg-light text-dark">{unassignedCount}</span>
                  </div>
                </button>
                {selectedProjectWaves.map((wave) => (
                  <button
                    key={wave.id}
                    type="button"
                    className={`list-group-item list-group-item-action ${selectedWaveId === wave.id ? 'active' : ''}`}
                    onClick={() => setSelectedWaveId(wave.id)}
                  >
                    <div className="d-flex justify-content-between align-items-center">
                      <span>{wave.name}</span>
                      <span className="badge bg-light text-dark">{wave.applicationCount ?? 0}</span>
                    </div>
                    {wave.startAt && (
                      <small className="text-muted d-block">Starts {new Date(wave.startAt).toLocaleDateString()}</small>
                    )}
                  </button>
                ))}
              </div>
            </div>
          )}
        </div>
        <div className="col-md-9">
          {selectedProject ? (
            <ProjectDetail
              apiBase={apiBase}
              project={selectedProject}
              selectedWaveId={selectedWaveId}
              onSelectWave={setSelectedWaveId}
              onProjectUpdated={handleProjectUpdated}
              onProjectDeleted={handleProjectDeleted}
              onRefresh={loadProjects}
            />
          ) : (
            <div className="text-muted mt-4">Select a project to view details.</div>
          )}
        </div>
      </div>
    </div>
  );
}
