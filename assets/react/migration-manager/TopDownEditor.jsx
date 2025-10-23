import React, { useEffect, useMemo, useState } from "react";

/** ---------- tiny fetch helpers ---------- */
async function jget(url) {
  const r = await fetch(url, { headers: { "Accept": "application/json" }});
  return r.json();
}
async function jsend(url, method, body) {
  const r = await fetch(url, {
    method,
    headers: { "Content-Type": "application/json", "Accept": "application/json" },
    body: JSON.stringify(body ?? {})
  });
  return r.json();
}

/** ---------- small reusable UI bits ---------- */
function Section({ title, children, actions }) {
  return (
    <section className="mb-6">
      <div className="flex items-center justify-between mb-2">
        <div className="font-medium">{title}</div>
        {actions}
      </div>
      {children}
    </section>
  );
}

function Row({ children }) {
  return <div className="flex gap-2 flex-wrap items-center">{children}</div>;
}

function Button({ variant="default", ...props }) {
  const cls = {
    default: "px-3 py-1 rounded border hover:bg-gray-50",
    primary: "px-3 py-1 rounded border bg-black text-white hover:opacity-90",
    danger:  "px-3 py-1 rounded border border-red-600 text-red-600 hover:bg-red-50",
    ghost:   "px-2 py-1 rounded hover:bg-gray-100",
  }[variant];
  return <button className={cls} {...props} />;
}

function Input({ label, ...props }) {
  return (
    <label className="text-sm mr-2">
      <span className="mr-1 text-gray-600">{label}</span>
      <input className="px-2 py-1 border rounded" {...props} />
    </label>
  );
}

function Select({ label, children, ...props }) {
  return (
    <label className="text-sm mr-2">
      <span className="mr-1 text-gray-600">{label}</span>
      <select className="px-2 py-1 border rounded" {...props}>
        {children}
      </select>
    </label>
  );
}

/** ---------- TopDownEditor ---------- */
export default function TopDownEditor() {
  // selection state
  const [project, setProject]   = useState(null);
  const [wave, setWave]         = useState(null);

  // datasets
  const [projects, setProjects]   = useState([]);
  const [waves, setWaves]         = useState([]);
  const [containers, setContainers] = useState([]);

  // forms
  const [pForm, setPForm] = useState({ name: "", description: "" });
  const [wForm, setWForm] = useState({ name: "", start_at: "", end_at: "" });
  const [cForm, setCForm] = useState({ name: "" });

  // load projects on mount
  useEffect(() => { reloadProjects(); }, []);
  async function reloadProjects() {
    const j = await jget(`/api/mig/projects`);
    setProjects(j?.data?.items ?? []);
  }

  // when project changes, load waves
  useEffect(() => {
    setWaves([]);
    setWave(null);
    setContainers([]);
    if (!project?.id && !project) return;
    const pid = project?.id ?? project; // allow {id} or numeric id
    jget(`/api/mig/projects/${pid}/waves`).then(j => setWaves(j?.data?.items ?? []));
  }, [project]);

  // when wave changes, load containers
  useEffect(() => {
    setContainers([]);
    if (!wave?.id && !wave) return;
    const wid = wave?.id ?? wave;
    jget(`/api/mig/waves/${wid}/containers`).then(j => setContainers(j?.data?.items ?? []));
  }, [wave]);

  /** ------- Project actions ------- */
  async function createProject() {
    if (!pForm.name?.trim()) return;
    await jsend(`/api/mig/projects`, "POST", pForm);
    setPForm({ name: "", description: "" });
    await reloadProjects();
  }
  async function updateProject(p) {
    const payload = { name: p.name, description: p.description ?? "" };
    await jsend(`/api/mig/projects/${p.id}`, "PATCH", payload);
    await reloadProjects();
  }
  async function deleteProject(p) {
    await fetch(`/api/mig/projects/${p.id}`, { method: "DELETE" });
    if (project?.id === p.id) { setProject(null); setWaves([]); setContainers([]); }
    await reloadProjects();
  }

  /** ------- Wave actions ------- */
  async function createWave() {
    if (!project) return;
    const pid = project.id ?? project;
    if (!wForm.name?.trim()) return;
    await jsend(`/api/mig/projects/${pid}/waves`, "POST", wForm);
    setWForm({ name: "", start_at: "", end_at: "" });
    const j = await jget(`/api/mig/projects/${pid}/waves`);
    setWaves(j?.data?.items ?? []);
  }
  async function updateWave(w) {
    const pid = project.id ?? project;
    await jsend(`/api/mig/projects/${pid}/waves/${w.id}`, "PATCH", w);
    const j = await jget(`/api/mig/projects/${pid}/waves`);
    setWaves(j?.data?.items ?? []);
  }
  async function deleteWave(wv) {
    const pid = project.id ?? project;
    await fetch(`/api/mig/projects/${pid}/waves/${wv.id}`, { method: "DELETE" });
    if (wave?.id === wv.id) { setWave(null); setContainers([]); }
    const j = await jget(`/api/mig/projects/${pid}/waves`);
    setWaves(j?.data?.items ?? []);
  }

  /** ------- Container actions ------- */
  async function createContainer() {
    if (!wave) return;
    const wid = wave.id ?? wave;
    if (!cForm.name?.trim()) return;
    await jsend(`/api/mig/waves/${wid}/containers`, "POST", cForm);
    setCForm({ name: "" });
    const j = await jget(`/api/mig/waves/${wid}/containers`);
    setContainers(j?.data?.items ?? []);
  }
  async function updateContainer(c) {
    const wid = wave.id ?? wave;
    await jsend(`/api/mig/waves/${wid}/containers/${c.id}`, "PATCH", c);
    const j = await jget(`/api/mig/waves/${wid}/containers`);
    setContainers(j?.data?.items ?? []);
  }
  async function deleteContainer(c) {
    const wid = wave.id ?? wave;
    await fetch(`/api/mig/waves/${wid}/containers/${c.id}`, { method: "DELETE" });
    const j = await jget(`/api/mig/waves/${wid}/containers`);
    setContainers(j?.data?.items ?? []);
  }

  /** ------- Render ------- */
  return (
    <div className="mt-8">
      <h2 className="text-xl font-semibold mb-4">Top-down editor</h2>

      {/* 1) PROJECTS */}
      <Section
        title="1) Migration Projects"
        actions={
          <Row>
            <Input label="Name" value={pForm.name} onChange={e=>setPForm(v=>({...v, name:e.target.value}))} />
            <Input label="Description" value={pForm.description} onChange={e=>setPForm(v=>({...v, description:e.target.value}))} />
            <Button variant="primary" onClick={createProject}>+ Create</Button>
          </Row>
        }
      >
        <Row>
          {projects.length === 0 && <div className="text-sm text-gray-500">No projects yet.</div>}
          {projects.map(p => (
            <div key={p.id ?? p.name} className="px-3 py-2 rounded-2xl border shadow-sm">
              <div className="font-medium">{p.name ?? `Project ${p.id}`}</div>
              <div className="text-xs text-gray-500">{p.description ?? '—'}</div>
              <Row>
                <Button variant="ghost" onClick={()=> setProject(p)}>Select</Button>
                <Button variant="ghost" onClick={()=> updateProject({ ...p, name: prompt('New name', p.name) ?? p.name })}>Rename</Button>
                <Button variant="danger" onClick={()=> deleteProject(p)}>Delete</Button>
              </Row>
            </div>
          ))}
        </Row>
      </Section>

      {/* 2) WAVES */}
      <Section
        title={`2) Waves ${project ? `(Project ${project.id ?? ''})` : ''}`}
        actions={
          <Row>
            <Input label="Name" value={wForm.name} onChange={e=>setWForm(v=>({...v, name:e.target.value}))} />
            <Input label="Start" type="datetime-local" value={wForm.start_at} onChange={e=>setWForm(v=>({...v, start_at:e.target.value}))} />
            <Input label="End" type="datetime-local" value={wForm.end_at} onChange={e=>setWForm(v=>({...v, end_at:e.target.value}))} />
            <Button variant="primary" onClick={createWave} disabled={!project}>+ Create</Button>
          </Row>
        }
      >
        {!project && <div className="text-sm text-gray-500">Select a project first.</div>}
        {project && (
          <Row>
            {waves.length === 0 && <div className="text-sm text-gray-500">No waves yet for this project.</div>}
            {waves.map(w => (
              <div key={w.id ?? w.name} className="px-3 py-2 rounded-2xl border shadow-sm">
                <div className="font-medium">{w.name ?? `Wave ${w.id}`}</div>
                <div className="text-xs text-gray-500">
                  {w.start_at ?? '—'} → {w.end_at ?? '—'}
                </div>
                <Row>
                  <Button variant="ghost" onClick={()=> setWave(w)}>Select</Button>
                  <Button variant="ghost" onClick={()=> updateWave({ ...w, name: prompt('New name', w.name) ?? w.name })}>Rename</Button>
                  <Button variant="danger" onClick={()=> deleteWave(w)}>Delete</Button>
                </Row>
              </div>
            ))}
          </Row>
        )}
      </Section>

      {/* 3) CONTAINERS */}
      <Section
        title={`3) Containers ${wave ? `(Wave ${wave.id ?? ''})` : ''}`}
        actions={
          <Row>
            <Input label="Name" value={cForm.name} onChange={e=>setCForm(v=>({...v, name:e.target.value}))} />
            <Button variant="primary" onClick={createContainer} disabled={!wave}>+ Create</Button>
          </Row>
        }
      >
        {!wave && <div className="text-sm text-gray-500">Select a wave first.</div>}
        {wave && (
          <Row>
            {containers.length === 0 && <div className="text-sm text-gray-500">No containers yet.</div>}
            {containers.map(c => (
              <div key={c.id ?? c.name} className="px-3 py-2 rounded-2xl border shadow-sm">
                <div className="font-medium">{c.name ?? `Container ${c.id}`}</div>
                <Row>
                  <Button variant="ghost" onClick={()=> alert('Servers editor comes next 🎯')}>Servers</Button>
                  <Button variant="ghost" onClick={()=> updateContainer({ ...c, name: prompt('New name', c.name) ?? c.name })}>Rename</Button>
                  <Button variant="danger" onClick={()=> deleteContainer(c)}>Delete</Button>
                </Row>
              </div>
            ))}
          </Row>
        )}
      </Section>

      <div className="text-xs text-gray-500">Next: servers editor (per container), application filter, method selector, and slot assignment UI.</div>
    </div>
  );
}
