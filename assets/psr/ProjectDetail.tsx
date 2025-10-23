import React from 'react';
import { PsrApi, ProjectDetailDTO, ProjectRow, TaskNode } from './api';
import { ProgressBar } from './widgets';
import { RAG, WEATHER_ICON } from './constants';

export default function ProjectDetail({projectId, readOnly=false}:{projectId:string; readOnly?:boolean}) {
  const [data,setData] = React.useState<ProjectDetailDTO|null>(null);
  const [snapOpen,setSnapOpen] = React.useState(false);
  const [label,setLabel] = React.useState('');
  const [note,setNote] = React.useState('');

  React.useEffect(()=>{ PsrApi.getProject(projectId).then(setData).catch(console.error); },[projectId]);
  if (!data) return <div className="p-4">Loading…</div>;

  const setProject = (patch:Partial<ProjectRow>)=>{
    PsrApi.upsertProject(data.id, patch).then(updated => setData({...data, ...updated})).catch(console.error);
  };

  return (
    <div className="p-4 space-y-4">
      {/* Header */}
      <div className="flex items-center gap-4">
        <h1 className="text-xl font-semibold">{data.name}</h1>

        {!readOnly ? (
          <select value={data.weatherTrend} onChange={e=>setProject({weatherTrend:Number(e.target.value) as any})} className="border rounded px-2 py-1">
            {[1,2,3,4,5].map(v=><option key={v} value={v}>{WEATHER_ICON[v]} {['','Stormy','Rainy','Cloudy','Clear','Sunny'][v]}</option>)}
          </select>
        ) : (
          <span className="px-2 py-1 rounded bg-gray-100">
            {WEATHER_ICON[data.weatherTrend]} {['','Stormy','Rainy','Cloudy','Clear','Sunny'][data.weatherTrend]}
          </span>
        )}

        {!readOnly ? (
          <select value={data.ragOverall} onChange={e=>setProject({ragOverall:Number(e.target.value) as any})} className="border rounded px-2 py-1">
            <option value={RAG.gray}>Gray</option>
            <option value={RAG.green}>Green</option>
            <option value={RAG.amber}>Amber</option>
            <option value={RAG.red}>Red</option>
          </select>
        ) : (
          <span className="px-2 py-0.5 rounded text-white"
            style={{backgroundColor:['#9ca3af','#16a34a','#f59e0b','#dc2626'][data.ragOverall]}}>
            {['Gray','Green','Amber','Red'][data.ragOverall]}
          </span>
        )}

        <div className="flex items-center gap-2 w-64">
          {!readOnly ? (
            <>
              <input type="range" min={0} max={100} value={data.progressPct}
                     onChange={e=>setProject({progressPct:Number(e.target.value)})} className="w-40" />
              <div className="w-24"><ProgressBar value={data.progressPct} /></div>
              <span className="w-10 text-right">{data.progressPct}%</span>
            </>
          ) : (
            <>
              <div className="w-24"><ProgressBar value={data.progressPct} /></div>
              <span className="w-10 text-right">{data.progressPct}%</span>
            </>
          )}
        </div>

        {!readOnly && (
          <button className="ml-auto px-3 py-1.5 rounded bg-black text-white" onClick={()=>setSnapOpen(true)}>Publish Snapshot</button>
        )}
      </div>

      {/* Description */}
      {!readOnly ? (
        <textarea className="w-full border rounded p-2" rows={3}
          defaultValue={data.description||''}
          onBlur={e=>setProject({description:e.target.value})}
          placeholder="Project description…" />
      ) : (
        <div className="w-full border rounded p-3 bg-white max-w-[40rem] whitespace-pre-wrap">
          {data.description || <span className="text-gray-400 italic">No description</span>}
        </div>
      )}

      {/* Tasks */}
      <TaskTree projectId={data.id} nodes={data.tasks} onChanged={()=>PsrApi.getProject(projectId).then(setData)} readOnly={readOnly} />

      {/* Snapshot modal */}
      {!readOnly && snapOpen && (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center">
          <div className="bg-white rounded-xl p-4 w-[520px] space-y-3">
            <h2 className="text-lg font-semibold">Publish Snapshot</h2>
            <input className="w-full border rounded px-2 py-1" placeholder="Label (e.g. 2025-W42)"
                   value={label} onChange={e=>setLabel(e.target.value)} />
            <textarea className="w-full border rounded p-2" rows={3}
                      placeholder="Note (optional)" value={note} onChange={e=>setNote(e.target.value)} />
            <div className="flex gap-2 justify-end">
              <button className="px-3 py-1 rounded border" onClick={()=>setSnapOpen(false)}>Cancel</button>
              <button className="px-3 py-1.5 rounded bg-black text-white"
                onClick={()=>{
                  PsrApi.takeSnapshot({label:label||undefined,note:note||undefined})
                    .then(()=>{ setSnapOpen(false); setLabel(''); setNote(''); })
                    .catch(console.error);
                }}>Publish</button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

function TaskTree({projectId,nodes,onChanged,readOnly=false}:{projectId:string; nodes:TaskNode[]; onChanged:()=>void; readOnly?:boolean}) {
  const [expanded,setExpanded] = React.useState<Record<string,boolean>>({});
  const toggle=(id:string)=>setExpanded(e=>({...e,[id]:!e[id]}));

  return (
    <div className="border rounded">
      <table className="w-full text-sm">
        <thead><tr className="text-left border-b">
          <th className="py-2 pl-2">Task</th><th>WBS</th><th>RAG</th><th className="w-40">Progress</th><th>Start</th><th>Due</th><th></th>
        </tr></thead>
        <tbody>
          {nodes.map(n=>(
            <TaskRow key={n.id} node={n} depth={0} expanded={expanded} toggle={toggle} onChanged={onChanged} readOnly={readOnly} />
          ))}
        </tbody>
      </table>
      {!readOnly && (
        <div className="p-2">
          <button className="px-2 py-1 rounded border"
            onClick={()=>PsrApi.createTask({projectId,name:'New Task',rag:1,progressPct:0}).then(onChanged)}>+ Add Task</button>
        </div>
      )}
    </div>
  );
}

function TaskRow({node,depth,expanded,toggle,onChanged,readOnly=false}:{node:TaskNode; depth:number; expanded:Record<string,boolean>; toggle:(id:string)=>void; onChanged:()=>void; readOnly?:boolean}) {
  const hasKids = (node.children?.length||0)>0;
  const pad = {paddingLeft: `${8 + depth*16}px`};
  const update = (patch:Partial<TaskNode>)=> PsrApi.updateTask(node.id, patch).then(onChanged);
  const del = ()=> { if (confirm('Delete task and its subtasks?')) PsrApi.deleteTask(node.id).then(onChanged); };

  return (
    <>
      <tr className="border-b">
        <td className="py-1" style={pad}>
          {hasKids ? <button className="mr-1 text-xs underline" onClick={()=>toggle(node.id)}>{expanded[node.id]?'▾':'▸'}</button> : null}
          {!readOnly ? (
            <input className="border rounded px-1 py-0.5 w-[28rem]" defaultValue={node.name}
                   onBlur={e=>update({name:e.target.value})} />
          ) : (<span>{node.name}</span>)}
        </td>
        <td>
          {!readOnly ? (
            <input className="border rounded px-1 py-0.5 w-24" defaultValue={node.wbsCode||''}
                   onBlur={e=>update({wbsCode:e.target.value||null})} />
          ) : (<span>{node.wbsCode||''}</span>)}
        </td>
        <td>
          {!readOnly ? (
            <select defaultValue={node.rag} className="border rounded px-1 py-0.5"
                    onChange={e=>update({rag:Number(e.target.value) as any})}>
              <option value={0}>Gray</option><option value={1}>Green</option>
              <option value={2}>Amber</option><option value={3}>Red</option>
            </select>
          ) : (
            <span className="px-2 py-0.5 rounded text-white"
              style={{backgroundColor:['#9ca3af','#16a34a','#f59e0b','#dc2626'][node.rag]}}>
              {['Gray','Green','Amber','Red'][node.rag]}
            </span>
          )}
        </td>
        <td className="w-40">
          <div className="flex items-center gap-2">
            {!readOnly && (
              <input type="range" min={0} max={100} defaultValue={node.progressPct}
                     onChange={e=>update({progressPct:Number(e.target.value)})} />
            )}
            <span className="w-8 text-right">{node.progressPct}%</span>
          </div>
        </td>
        <td>
          {!readOnly ? (
            <input type="date" className="border rounded px-1 py-0.5"
                   defaultValue={node.startDate||''}
                   onBlur={e=>update({startDate:e.target.value||null})} />
          ) : (<span>{node.startDate||''}</span>)}
        </td>
        <td>
          {!readOnly ? (
            <input type="date" className="border rounded px-1 py-0.5"
                   defaultValue={node.dueDate||''}
                   onBlur={e=>update({dueDate:e.target.value||null})} />
          ) : (<span>{node.dueDate||''}</span>)}
        </td>
        <td className="text-right pr-2">
          {!readOnly && <button className="text-red-600 underline" onClick={del}>Delete</button>}
        </td>
      </tr>
      {hasKids && expanded[node.id] && node.children!.map(c=>(
        <TaskRow key={c.id} node={c} depth={depth+1} expanded={expanded} toggle={toggle} onChanged={onChanged} readOnly={readOnly} />
      ))}
    </>
  );
}
