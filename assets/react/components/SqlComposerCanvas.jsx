import React, { useCallback, useEffect, useRef, useState } from 'react';
import ReactFlow, { Background, Controls, addEdge, MiniMap } from 'reactflow';
import 'reactflow/dist/style.css';
import SqlJoinEdge from './SqlJoinEdge';
import { buildSelectSQL } from './useSqlBuilder';

function fetchJSON(url, opts) { return fetch(url, opts).then(r => r.json()); }

const edgeTypes = { sqljoin: SqlJoinEdge };

export default function SqlComposerCanvas({ apiBase = '/api/sqlc', defaultSchema }) {
  const [schema, setSchema] = useState(defaultSchema || '');
  const [objects, setObjects] = useState([]); // {name, type}
  const [nodes, setNodes] = useState([]);
  const [edges, setEdges] = useState([]);
  const [selections, setSelections] = useState({}); // by nodeId
  const [sql, setSql] = useState('');
  const [rows, setRows] = useState([]);
  const idRef = useRef(1);

  // Load schema objects
  useEffect(() => {
    fetchJSON(`${apiBase}/schema`).then(d => {
      setSchema(d.schema);
      setObjects(d.objects || []);
    });
  }, [apiBase]);

  const addNode = useCallback(async (name) => {
    const id = String(idRef.current++);
    const cols = await fetchJSON(`${apiBase}/columns?table=${encodeURIComponent(name)}&schema=${encodeURIComponent(schema)}`);
    setNodes(nds => [...nds, {
      id,
      position: { x: 100 + 60 * nds.length, y: 80 + 40 * nds.length },
      data: { label: name, columns: cols, alias: name.slice(0, 3) },
      type: 'default'
    }]);
    setSelections(s => ({ ...s, [id]: { alias: name.slice(0, 3), columns: [] } }));
  }, [apiBase, schema]);

  const onConnect = useCallback((params) => {
    setEdges((eds) => addEdge({ ...params, type: 'sqljoin', data: { joinType: 'INNER', on: '' } }, eds));
  }, []);

  // Rebuild SQL on changes
  useEffect(() => {
    setSql(buildSelectSQL({ schema, nodes, edges, selections }));
  }, [schema, nodes, edges, selections]);

  // Selection helpers
  const toggleCol = (nodeId, name) => {
    setSelections((s) => {
      const cur = s[nodeId] || { alias: 't', columns: [] };
      const exists = cur.columns.find(c => c.name === name);
      const nextCols = exists ? cur.columns.filter(c => c.name !== name) : [...cur.columns, { name, as: '', fmt: '' }];
      return { ...s, [nodeId]: { ...cur, columns: nextCols } };
    });
  };

  const setAlias = (nodeId, alias) => setSelections((s) => ({ ...s, [nodeId]: { ...s[nodeId], alias } }));

  const setColProp = (nodeId, name, prop, value) => setSelections((s) => ({
    ...s,
    [nodeId]: { ...s[nodeId], columns: (s[nodeId].columns || []).map(c => c.name === name ? { ...c, [prop]: value } : c) }
  }));

  const doPreview = async () => {
    const resp = await fetchJSON(`${apiBase}/preview`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ sql, limit: 200 })
    });
    if (resp.error) { alert(resp.error); return; }
    setRows(resp.rows);
  };

  const doSave = async () => {
    const repshort = prompt('Short code for report?');
    if (!repshort) return;
    const reptitle = prompt('Title?') || repshort;
    const payload = {
      reptype: 'sql',
      repshort,
      reptitle,
      repdesc: '',
      repsql: sql,
      repparam: JSON.stringify({ selections, edges })
    };
    const resp = await fetchJSON(`${apiBase}/save`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    if (resp.ok) alert(`Saved as repid ${resp.repid}`); else alert(resp.error || 'Save failed');
  };

  return (
    <div className="container-fluid">
      <div className="row g-3">
        {/* Left: schema browser */}
        <div className="col-3">
          <div className="card h-100">
            <div className="card-header d-flex align-items-center justify-content-between">
              <strong>Schema</strong>
              <span className="badge text-bg-secondary">{schema}</span>
            </div>
            <div className="card-body p-2" style={{ overflowY:'auto', maxHeight:'70vh' }}>
              <input className="form-control form-control-sm mb-2" placeholder="Filter…" onChange={(e)=>{
                const q = e.target.value.toLowerCase();
                const items = document.querySelectorAll('[data-object]');
                items.forEach(el => el.style.display = el.dataset.object.includes(q) ? '' : 'none');
              }} />
              {objects.map(o => (
                <div key={o.name} data-object={o.name.toLowerCase()} className="d-flex align-items-center justify-content-between border rounded px-2 py-1 mb-1">
                  <span>{o.name} <small className="text-muted">{o.type === 'VIEW' ? 'view' : 'table'}</small></span>
                  <button className="btn btn-sm btn-outline-primary" onClick={()=>addNode(o.name)}>Add</button>
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* Center: canvas */}
        <div className="col-6">
          <div className="card">
            <div className="card-header d-flex gap-2">
              <button className="btn btn-sm btn-secondary" onClick={()=>{ 
                setNodes([]); setEdges([]); setSelections({}); setSql(''); setRows([]); 
              }}>New</button>
              <button className="btn btn-sm btn-primary" onClick={doPreview} disabled={!sql}>Preview</button>
              <button className="btn btn-sm btn-success" onClick={doSave} disabled={!sql}>Save</button>
            </div>
            <div className="card-body" style={{ height: '60vh' }}>
              <ReactFlow
                nodes={nodes}
                edges={edges}
                onNodesChange={setNodes}
                onEdgesChange={setEdges}
                onConnect={onConnect}
                edgeTypes={edgeTypes}
                fitView
              >
                <Background />
                <MiniMap />
                <Controls />
              </ReactFlow>
            </div>
          </div>
        </div>

        {/* Right: properties & SQL */}
        <div className="col-3">
          <div className="card mb-3">
            <div className="card-header"><strong>Selection</strong></div>
            <div className="card-body p-2" style={{ maxHeight:'30vh', overflowY:'auto' }}>
              {nodes.map(n => (
                <details key={n.id} className="mb-2" open>
                  <summary><strong>{n.data.label}</strong></summary>
                  <div className="input-group input-group-sm my-1">
                    <span className="input-group-text">Alias</span>
                    <input className="form-control" value={selections[n.id]?.alias || ''} onChange={e=>setAlias(n.id, e.target.value)} />
                  </div>
                  <div className="small text-muted">Columns</div>
                  {(n.data.columns || []).map(c => (
                    <div key={c.name} className="form-check form-check-sm">
                      <input
                        className="form-check-input"
                        type="checkbox"
                        id={`${n.id}-${c.name}`}
                        checked={!!(selections[n.id]?.columns?.find(x=>x.name===c.name))}
                        onChange={()=>toggleCol(n.id, c.name)}
                      />
                      <label className="form-check-label" htmlFor={`${n.id}-${c.name}`}>
                        {c.name} <small className="text-muted">({c.dtype})</small>
                      </label>
                      {selections[n.id]?.columns?.find(x=>x.name===c.name) && (
                        <div className="ms-4 my-1">
                          <input
                            className="form-control form-control-sm mb-1"
                            placeholder="Alias (AS)"
                            value={selections[n.id].columns.find(x=>x.name===c.name)?.as || ''}
                            onChange={e=>setColProp(n.id, c.name, 'as', e.target.value)}
                          />
                          <input
                            className="form-control form-control-sm"
                            placeholder="Format e.g. DATE_FORMAT({col}, '%Y-%m-%d')"
                            value={selections[n.id].columns.find(x=>x.name===c.name)?.fmt || ''}
                            onChange={e=>setColProp(n.id, c.name, 'fmt', e.target.value)}
                          />
                        </div>
                      )}
                    </div>
                  ))}
                </details>
              ))}
            </div>
          </div>

          <div className="card">
            <div className="card-header d-flex align-items-center justify-content-between">
              <strong>SQL</strong>
              <span className="badge text-bg-light">read-only</span>
            </div>
            <div className="card-body p-0">
              <textarea
                className="form-control border-0"
                style={{ fontFamily:'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace', fontSize:'12px', height:'22vh' }}
                value={sql}
                readOnly
              />
            </div>
          </div>
        </div>
      </div>

      {/* Preview */}
      <div className="card mt-3">
        <div className="card-header">Preview (first rows)</div>
        <div className="card-body p-0" style={{ overflowX:'auto', maxHeight:'35vh', overflowY:'auto' }}>
          {!rows?.length ? <div className="p-3 text-muted">No data</div> : (
            <table className="table table-sm table-striped mb-0">
              <thead>
                <tr>{Object.keys(rows[0]).map(h => <th key={h}>{h}</th>)}</tr>
              </thead>
              <tbody>
                {rows.map((r, i) => (
                  <tr key={i}>{Object.values(r).map((v, j) => <td key={j}>{fmt(v)}</td>)}</tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </div>
    </div>
  );
}

function fmt(v){ if(v===null||v===undefined) return ''; if(typeof v==='object') return JSON.stringify(v); return String(v); }
