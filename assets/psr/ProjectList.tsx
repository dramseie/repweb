import React from 'react';
import { PsrApi, ProjectRow } from './api';
import { RagBadge, WeatherGlyph, ProgressBar } from './widgets';

export default function ProjectList() {
  const [rows,setRows] = React.useState<ProjectRow[]>([]);
  const [q,setQ] = React.useState('');
  React.useEffect(()=>{ PsrApi.listProjects().then(setRows).catch(console.error); },[]);
  const filtered = rows.filter(r => [r.name, r.description||'', String(r.progressPct)].join(' ').toLowerCase().includes(q.toLowerCase()));
  return (
    <div className="p-4 space-y-3">
      <div className="flex items-center gap-2">
        <input className="border rounded px-2 py-1" placeholder="Search…" value={q} onChange={e=>setQ(e.target.value)} />
        <a href="/psr/compare" className="ml-auto underline">Version Compare</a>
        <a href="/psr/new" className="underline">+ New Project</a>
      </div>
      <table className="w-full text-sm">
        <thead><tr className="text-left border-b">
          <th className="py-2">Project</th><th>Weather</th><th>RAG</th><th className="w-64">Progress</th><th>Updated</th><th></th>
        </tr></thead>
        <tbody>
          {filtered.map(r=>(
            <tr key={r.id} className="border-b hover:bg-gray-50">
              <td className="py-2">{r.name}</td>
              <td><WeatherGlyph v={r.weatherTrend as any} /></td>
              <td><RagBadge v={r.ragOverall as any} /></td>
              <td className="w-64"><ProgressBar value={r.progressPct} /></td>
              <td>{new Date(r.updatedAt).toLocaleString()}</td>
              <td><a className="underline" href={`/psr/projects/${r.id}`}>Open</a></td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
