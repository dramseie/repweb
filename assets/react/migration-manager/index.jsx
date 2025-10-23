import React, { useEffect, useState } from "react";
import { createRoot } from "react-dom/client";
import CapacityCalendar from "./Calendar";
import TopDownEditor from "./TopDownEditor";

const enabled = (window?.__repweb?.flags?.MIGRATION_MANAGER_ENABLED ?? false);

/* fetch helpers */
async function jget(u){const r=await fetch(u,{headers:{Accept:"application/json"}});return r.json();}
async function jsend(u,m,b){const r=await fetch(u,{method:m,headers:{"Content-Type":"application/json",Accept:"application/json"},body:JSON.stringify(b??{})});return r.json();}

/* slim primitives */
function Gate({children}){ return enabled? children : <div className="p-4 text-sm text-gray-600">Migration Manager is disabled.</div>; }
function PageHeader({title,right}) {
  return (
    <div className="mb-3">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
        <div className="flex items-center gap-2">{right}</div>
      </div>
      <div className="text-xs text-gray-500">Where data comes together</div>
    </div>
  );
}
function SubTabs({tabs,current,onTab}) {
  return (
    <div className="border-b mb-3">
      <nav className="flex gap-6">
        {tabs.map(t=>(
          <button key={t.key}
            onClick={()=>onTab(t.key)}
            className={`px-0.5 pb-2 -mb-[1px] border-b-2 ${
              current===t.key ? "border-black text-black font-medium"
                              : "border-transparent text-gray-500 hover:text-black"
            }`}>
            {t.label}
          </button>
        ))}
      </nav>
    </div>
  );
}
function Panel({title,children,actions}) {
  return (
    <div className="bg-white border rounded-md mb-4">
      <div className="flex items-center justify-between px-3 py-2 border-b">
        <div className="text-sm font-medium">{title}</div>
        {actions && <div className="flex items-center gap-2">{actions}</div>}
      </div>
      <div className="p-3">{children}</div>
    </div>
  );
}
function Kpi({label,value}) {
  return (
    <div className="bg-white border rounded-md p-3">
      <div className="text-[11px] uppercase text-gray-500">{label}</div>
      <div className="text-2xl font-semibold">{value ?? "—"}</div>
    </div>
  );
}
function Select({label,...props}) {
  return (
    <label className="text-sm inline-flex items-center gap-2">
      {label && <span className="text-gray-600">{label}</span>}
      <select className="px-2 py-1 border rounded-md bg-white" {...props} />
    </label>
  );
}
function Input({label,...props}) {
  return (
    <label className="text-sm inline-flex items-center gap-2">
      {label && <span className="text-gray-600">{label}</span>}
      <input className="px-2 py-1 border rounded-md bg-white" {...props} />
    </label>
  );
}
function Button({variant="default",className="",...props}) {
  const base="px-3 py-1.5 text-sm rounded-md border";
  const cls={
    default:`${base} hover:bg-gray-50`,
    primary:`${base} bg-black text-white border-black hover:opacity-90`,
    danger:`${base} border-red-600 text-red-600 hover:bg-red-50`,
  }[variant];
  return <button className={`${cls} ${className}`} {...props} />;
}

/* data hooks */
function useList(url){
  const [data,setData]=useState([]); const [loading,setLoading]=useState(false);
  const reload=async()=>{ if(!url){setData([]);return;} setLoading(true); const j=await jget(url); setData(j?.data?.items??[]); setLoading(false); };
  useEffect(()=>{reload();},[url]); return {data,loading,reload};
}

/* widgets */
function ProjectPicker({projectId,onChange}){
  const [items,setItems]=useState([]);
  useEffect(()=>{ jget('/api/mig/projects').then(j=>setItems(j?.data?.items??[])); },[]);
  return (
    <Select label="Project" value={projectId ?? ""} onChange={e=>onChange(e.target.value||null)}>
      <option value="">select</option>
      {items.map(p=><option key={p.id} value={p.id}>{p.name}</option>)}
    </Select>
  );
}

/* tabs */
function ProgressTab() {
  const [kpi,setKpi]=useState(null);
  useEffect(()=>{ jget('/api/mig/reports/kpis').then(j=>setKpi(j?.data??null)).catch(()=>setKpi(null)); },[]);
  const fmt = x => typeof x==="number" ? (x<=1? `${Math.round(x*100)}%` : `${x}`) : "—";
  return (
    <>
      <div className="grid gap-3 md:grid-cols-3">
        <Kpi label="Completion rate" value={fmt(kpi?.completion_rate?.value)} />
        <Kpi label="Rollback rate"   value={fmt(kpi?.rollback_rate?.value)} />
        <Kpi label="Data confidence" value={fmt(kpi?.data_confidence?.value)} />
      </div>
      <Panel title="Recent activity">
        <div className="text-sm text-gray-500">Coming soon: timelines & events.</div>
      </Panel>
    </>
  );
}

const METHODS=["LiftShift","Reinstall","P2V","V2V","vMotion","Decomm"];
function ContainerTab({projectId}) {
  const [waveId,setWaveId]=useState(null);
  const [containerId,setContainerId]=useState(null);
  const [filterApp,setFilterApp]=useState("");
  const [serverForm,setServerForm]=useState({hostname:"",application:"",method:"LiftShift"});
  const waves=useList(projectId? `/api/mig/projects/${projectId}/waves`:null);
  const containers=useList(waveId? `/api/mig/waves/${waveId}/containers`:null);
  const serversUrl=containerId? `/api/mig/containers/${containerId}/servers`+(filterApp?`?app=${encodeURIComponent(filterApp)}`:""):null;
  const servers=useList(serversUrl);
  useEffect(()=>{ setWaveId(null); setContainerId(null); },[projectId]);
  useEffect(()=>{ setContainerId(null); },[waveId]);

  async function addServer(){
    if(!containerId || !serverForm.hostname.trim()) return;
    await jsend(`/api/mig/containers/${containerId}/servers`,"POST",serverForm);
    setServerForm(s=>({...s,hostname:"",application:""})); servers.reload();
  }

  return (
    <>
      <Panel title="Scope">
        <div className="flex flex-wrap gap-3">
          <Select label="Wave" value={waveId??""} onChange={e=>setWaveId(e.target.value||null)}>
            <option value="">select</option>
            {waves.data.map(w=><option key={w.id} value={w.id}>{w.name}</option>)}
          </Select>
          <Select label="Container" value={containerId??""} onChange={e=>setContainerId(e.target.value||null)} disabled={!waveId}>
            <option value="">select</option>
            {containers.data.map(c=><option key={c.id} value={c.id}>{c.name}</option>)}
          </Select>
          <Input label="Filter app" value={filterApp} onChange={e=>setFilterApp(e.target.value)} />
          <Button onClick={()=>servers.reload()} disabled={!containerId}>Refresh</Button>
        </div>
      </Panel>

      <Panel title="Add server">
        <div className="flex flex-wrap items-end gap-2">
          <Input label="Hostname" value={serverForm.hostname} onChange={e=>setServerForm(v=>({...v,hostname:e.target.value}))}/>
          <Input label="Application" value={serverForm.application} onChange={e=>setServerForm(v=>({...v,application:e.target.value}))}/>
          <Select label="Method" value={serverForm.method} onChange={e=>setServerForm(v=>({...v,method:e.target.value}))}>
            {METHODS.map(m=><option key={m} value={m}>{m}</option>)}
          </Select>
          <Button variant="primary" onClick={addServer} disabled={!containerId}>+ Add</Button>
        </div>
      </Panel>

      <Panel title="Servers">
        {!containerId ? (
          <div className="text-sm text-gray-500">Select a container.</div>
        ) : (
          <div className="overflow-auto">
            <table className="min-w-full text-sm">
              <thead>
                <tr className="text-left text-gray-500">
                  <th className="py-1 pr-4">Hostname</th>
                  <th className="py-1 pr-4">Application</th>
                  <th className="py-1 pr-4">Method</th>
                  <th className="py-1 pr-4"></th>
                </tr>
              </thead>
              <tbody>
                {servers.data.map(s=>(
                  <tr key={s.id} className="border-t">
                    <td className="py-1 pr-4">{s.hostname}</td>
                    <td className="py-1 pr-4">{s.application ?? "—"}</td>
                    <td className="py-1 pr-4"><span className="inline-block text-[11px] px-2 py-0.5 rounded-full border bg-gray-50">{s.method}</span></td>
                    <td className="py-1 pr-4 text-right">{/* future actions */}</td>
                  </tr>
                ))}
                {servers.data.length===0 && <tr><td colSpan="4" className="py-2 text-gray-500">No servers yet.</td></tr>}
              </tbody>
            </table>
          </div>
        )}
      </Panel>
    </>
  );
}

function CalendarTab(){ return <Panel title="Capacity calendar"><CapacityCalendar /></Panel>; }

function ProgramDashboard(){
  const [projectId,setProjectId]=useState(null);
  const [tab,setTab]=useState("progress");
  return (
    <Gate>
      {/* full-width container: no max-width wrapper */}
      <div className="w-full">
        <PageHeader title="Migration Manager" right={<ProjectPicker projectId={projectId} onChange={setProjectId} />} />
        <SubTabs
          tabs={[
            {key:"progress",label:"Progress"},
            {key:"calendar",label:"Calendar"},
            {key:"container",label:"Container"},
            {key:"editor",label:"Editor"},
          ]}
          current={tab}
          onTab={setTab}
        />
        {tab==="progress"  && <ProgressTab />}
        {tab==="calendar"  && <CalendarTab />}
        {tab==="container" && <ContainerTab projectId={projectId} />}
        {tab==="editor"    && <Panel title="Top-down editor"><TopDownEditor /></Panel>}
      </div>
    </Gate>
  );
}

const el = document.getElementById("migration-manager-root");
if (el) createRoot(el).render(<ProgramDashboard />);
