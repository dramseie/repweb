import React from 'react';
import { PsrApi, VersionRow, CompareDTO } from './api';
import { RagBadge, WeatherGlyph, ProgressBar } from './widgets';

export default function VersionCompare() {
  const [versions,setVersions] = React.useState<VersionRow[]>([]);
  const [a,setA] = React.useState<number|undefined>();
  const [b,setB] = React.useState<number|undefined>();
  const [cmp,setCmp] = React.useState<CompareDTO|null>(null);

  React.useEffect(()=>{ PsrApi.versions().then(setVersions); },[]);
  React.useEffect(()=>{ if (a && b) PsrApi.compare(a,b).then(setCmp).catch(console.error); },[a,b]);

  const exportCSV = ()=>{
    if (!cmp) return;
    const rows:string[] = [];
    rows.push('Scope,Project/Task,WBS,RAG A,RAG B,Prog A,Prog B,Weather A,Weather B');
    cmp.projects.forEach(p=> rows.push(['Project',p.name,'',p.rag_a,p.rag_b,p.prog_a,p.prog_b,p.wthr_a,p.wthr_b].join(',')));
    cmp.tasks.forEach(t=> rows.push(['Task',t.name, t.wbs_code??'', t.rag_a, t.rag_b, t.prog_a, t.prog_b, '', ''].join(',')));
    const blob = new Blob([rows.join('\n')],{type:'text/csv;charset=utf-8;'});
    const url = URL.createObjectURL(blob); const a = document.createElement('a'); a.href=url; a.download='psr-compare.csv'; a.click(); URL.revokeObjectURL(url);
  };

  return (
    <div className="p-4 space-y-4">
      <h1 className="text-xl font-semibold">Version Compare</h1>
      <div className="flex gap-2 items-center">
        <VersionSelect label="Version A" versions={versions} value={a} onChange={setA} />
        <VersionSelect label="Version B" versions={versions} value={b} onChange={setB} />
        <button className="ml-auto px-3 py-1.5 rounded border" onClick={exportCSV} disabled={!cmp}>Export CSV</button>
      </div>
      {!cmp ? <div className="text-gray-500">Choose two versions to compare.</div> : (
        <>
          <section className="space-y-2">
            <h2 className="font-semibold">Projects Δ</h2>
            <table className="w-full text-sm">
              <thead><tr className="text-left border-b">
                <th className="py-2">Project</th><th>Weather A→B</th><th>RAG A→B</th><th className="w-72">Progress A→B</th>
              </tr></thead>
              <tbody>
                {cmp.projects.map(p=>{
                  const d = p.prog_b - p.prog_a; const rd = p.rag_b - p.rag_a;
                  return (
                    <tr key={p.project_id} className="border-b">
                      <td className="py-1">{p.name}</td>
                      <td><WeatherGlyph v={p.wthr_a as any}/> → <WeatherGlyph v={p.wthr_b as any}/></td>
                      <td className={rd>0?'text-red-600':(rd<0?'text-green-600':'')}>
                        <RagBadge v={p.rag_a as any}/> → <RagBadge v={p.rag_b as any}/>
                      </td>
                      <td className="w-72">
                        <div className="flex items-center gap-2">
                          <div className="w-24"><ProgressBar value={p.prog_a} /></div>→<div className="w-24"><ProgressBar value={p.prog_b} /></div>
                          <span className={d>0?'text-green-700':(d<0?'text-red-700':'')}>{d>0?'▲':d<0?'▼':'—'} {Math.abs(d)}%</span>
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </section>

          <section className="space-y-2">
            <h2 className="font-semibold">Tasks Δ</h2>
            <table className="w-full text-sm">
              <thead><tr className="text-left border-b">
                <th className="py-2">Task</th><th>WBS</th><th>RAG A→B</th><th className="w-72">Progress A→B</th>
              </tr></thead>
              <tbody>
                {cmp.tasks.map(t=>{
                  const d = t.prog_b - t.prog_a; const rd = t.rag_b - t.rag_a;
                  return (
                    <tr key={t.task_id} className="border-b">
                      <td className="py-1">{t.name}</td>
                      <td>{t.wbs_code||''}</td>
                      <td className={rd>0?'text-red-600':(rd<0?'text-green-600':'')}>
                        <RagBadge v={t.rag_a as any}/> → <RagBadge v={t.rag_b as any}/>
                      </td>
                      <td className="w-72">
                        <div className="flex items-center gap-2">
                          <div className="w-24"><ProgressBar value={t.prog_a} /></div>→<div className="w-24"><ProgressBar value={t.prog_b} /></div>
                          <span className={d>0?'text-green-700':(d<0?'text-red-700':'')}>{d>0?'▲':d<0?'▼':'—'} {Math.abs(d)}%</span>
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </section>
        </>
      )}
    </div>
  );
}

function VersionSelect({label,versions,value,onChange}:{label:string;versions:VersionRow[];value?:number;onChange:(n:number)=>void}) {
  return (
    <label className="flex items-center gap-2">
      <span className="text-sm text-gray-600">{label}</span>
      <select className="border rounded px-2 py-1" value={value ?? ''} onChange={e=>onChange(Number(e.target.value))}>
        <option value="" disabled>Select…</option>
        {versions.map(v=><option key={v.id} value={v.id}>{v.label} — {new Date(v.createdAt).toLocaleString()}</option>)}
      </select>
    </label>
  );
}
