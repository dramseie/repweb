import React, { useCallback, useEffect, useRef, useState } from "react";
import ReactFlow, {
  Background,
  BackgroundVariant,
  Controls,
  MiniMap,
  addEdge,
  useEdgesState,
  useNodesState,
  MarkerType,
  Panel,
} from "reactflow";
import "reactflow/dist/style.css";

// ---- simple styles / colors
const kindColors = {
  trigger: "#d1fae5",
  http: "#dbeafe",
  sql: "#fde68a",
  transform: "#ede9fe",
  branch: "#cffafe",
  delay: "#f1f5f9",
  output: "#ffe4e6",
};

function FlowNode({ data }) {
  const bg = kindColors[data.kind] || "#fff";
  return (
    <div style={{
      background: bg, border: "1px solid #d0d7de", borderRadius: 12,
      padding: 12, boxShadow: "0 1px 1px rgba(0,0,0,.04)", minWidth: 180
    }}>
      <div style={{display:"flex",gap:8,alignItems:"center"}}>
        <span style={{fontSize:11,padding:"2px 8px",borderRadius:12,border:"1px solid #d0d7de",background:"#fff"}}>{data.kind}</span>
        <span style={{fontWeight:600,overflow:"hidden",textOverflow:"ellipsis"}}>{data.name || "(unnamed)"}</span>
      </div>
      {data.kind === "http" && (
        <div style={{marginTop:6,fontSize:12,opacity:.8}}>
          {(data.config.method || "GET")} → {(data.config.url || "/api")}
        </div>
      )}
      {data.kind === "sql" && (
        <div style={{marginTop:6,fontSize:12,opacity:.8,whiteSpace:"nowrap",overflow:"hidden",textOverflow:"ellipsis"}}>
          {(data.config.sql || "SELECT 1").slice(0, 80)}
        </div>
      )}
      {data.kind === "transform" && (
        <div style={{marginTop:6,fontSize:12,opacity:.8}}>{data.config.lang || "jq"}</div>
      )}
      {data.kind === "delay" && (
        <div style={{marginTop:6,fontSize:12,opacity:.8}}>{data.config.ms ?? 1000} ms</div>
      )}
    </div>
  );
}

const nodeTypes = { default: FlowNode };

const PALETTE = [
  { kind: "trigger", title: "Trigger", hint: "Cron / webhook / manual" },
  { kind: "http", title: "HTTP", hint: "GET/POST REST call" },
  { kind: "sql", title: "SQL", hint: "Query / mutate MariaDB" },
  { kind: "transform", title: "Transform", hint: "jq / JS / template" },
  { kind: "branch", title: "Branch", hint: "if/else / switch" },
  { kind: "delay", title: "Delay", hint: "sleep / backoff" },
  { kind: "output", title: "Output", hint: "Emit / notify" },
];

const LS_KEY = "repweb.flowstudio.merged";

function useLocalStorage(key, initial) {
  const [state, setState] = useState(() => {
    try { const raw = localStorage.getItem(key); return raw ? JSON.parse(raw) : initial; } catch { return initial; }
  });
  useEffect(() => { try { localStorage.setItem(key, JSON.stringify(state)); } catch {} }, [key, state]);
  return [state, setState];
}
function nextName(kind, existing) {
  const base = kind[0].toUpperCase() + kind.slice(1);
  const n = existing.filter(n => n.data.kind === kind).length + 1;
  return `${base} ${n}`;
}

function NodeEditor({ selected, onApply }) {
  if (!selected) {
    return (
      <div style={{border:"1px solid #d0d7de",borderRadius:12,padding:12}}>
        <div style={{fontWeight:600,marginBottom:6}}>Properties</div>
        <div style={{fontSize:13,opacity:.7}}>Select a node to edit its settings.</div>
      </div>
    );
  }
  const { data } = selected;
  return (
    <div style={{border:"1px solid #d0d7de",borderRadius:12,padding:12,display:"grid",gap:10}}>
      <div style={{fontWeight:600}}>{data.name}</div>
      <div>
        <label style={{fontSize:13,display:"block"}}>Name</label>
        <input defaultValue={data.name} onChange={(e)=>onApply({ name: e.target.value })}
               style={{width:"100%",border:"1px solid #d0d7de",borderRadius:10,padding:"8px 10px"}} />
      </div>

      {data.kind === "trigger" && (
        <>
          <label style={{fontSize:13,display:"block"}}>Mode</label>
          <select defaultValue={data.config.mode || "manual"}
                  onChange={(e)=>onApply({ config:{...data.config, mode:e.target.value} })}
                  style={{width:"100%",border:"1px solid #d0d7de",borderRadius:10,padding:"8px 10px"}}>
            <option value="manual">Manual</option>
            <option value="cron">Cron</option>
            <option value="webhook">Webhook</option>
          </select>
          {data.config.mode === "cron" && (
            <>
              <label style={{fontSize:13,display:"block"}}>Cron</label>
              <input placeholder="*/5 * * * *" defaultValue={data.config.cron}
                     onChange={(e)=>onApply({ config:{...data.config, cron:e.target.value} })}
                     style={{width:"100%",border:"1px solid #d0d7de",borderRadius:10,padding:"8px 10px"}} />
            </>
          )}
        </>
      )}

      {data.kind === "http" && (
        <>
          <label style={{fontSize:13,display:"block"}}>Method</label>
          <select defaultValue={data.config.method || "GET"}
                  onChange={(e)=>onApply({ config:{...data.config, method:e.target.value} })}
                  style={{width:"100%",border:"1px solid #d0d7de",borderRadius:10,padding:"8px 10px"}}>
            <option>GET</option><option>POST</option><option>PUT</option><option>DELETE</option>
          </select>
          <label style={{fontSize:13,display:"block",marginTop:6}}>URL</label>
          <input placeholder="https://example/api" defaultValue={data.config.url}
                 onChange={(e)=>onApply({ config:{...data.config, url:e.target.value} })}
                 style={{width:"100%",border:"1px solid #d0d7de",borderRadius:10,padding:"8px 10px"}} />
          <label style={{fontSize:13,display:"block",marginTop:6}}>Headers (JSON)</label>
          <textarea rows={3} placeholder='{"Authorization":"Bearer ..."}' defaultValue={data.config.headers}
                    onChange={(e)=>onApply({ config:{...data.config, headers:e.target.value} })}
                    style={{width:"100%",border:"1px solid #d0d7de",borderRadius:10,padding:"8px 10px"}} />
          <label style={{fontSize:13,display:"block",marginTop:6}}>Body (JSON)</label>
          <textarea rows={4} placeholder='{"key":"value"}' defaultValue={data.config.body}
                    onChange={(e)=>onApply({ config:{...data.config, body:e.target.value} })}
                    style={{width:"100%",border:"1px solid #d0d7de",borderRadius:10,padding:"8px 10px"}} />
        </>
      )}

      {data.kind === "sql" && (
        <>
          <label style={{fontSize:13,display:"block"}}>SQL</label>
          <textarea rows={6} placeholder="SELECT * FROM ext_eav.values LIMIT 10" defaultValue={data.config.sql}
                    onChange={(e)=>onApply({ config:{...data.config, sql:e.target.value} })}
                    style={{width:"100%",border:"1px solid #d0d7de",borderRadius:10,padding:"8px 10px"}} />
        </>
      )}

      {data.kind === "transform" && (
        <>
          <label style={{fontSize:13,display:"block"}}>Language</label>
          <select defaultValue={data.config.lang || "jq"}
                  onChange={(e)=>onApply({ config:{...data.config, lang:e.target.value} })}
                  style={{width:"100%",border:"1px solid #d0d7de",borderRadius:10,padding:"8px 10px"}}>
            <option value="jq">jq</option>
            <option value="js">JavaScript</option>
            <option value="liquid">Liquid</option>
          </select>
          <label style={{fontSize:13,display:"block"}}>Code</label>
          <textarea rows={6} placeholder=".items[] | {id, name}" defaultValue={data.config.code}
                    onChange={(e)=>onApply({ config:{...data.config, code:e.target.value} })}
                    style={{width:"100%",border:"1px solid #d0d7de",borderRadius:10,padding:"8px 10px"}} />
        </>
      )}

      {data.kind === "branch" && (
        <>
          <label style={{fontSize:13,display:"block"}}>Condition (JS expression)</label>
          <input placeholder="payload.status === 200" defaultValue={data.config.condition}
                 onChange={(e)=>onApply({ config:{...data.config, condition:e.target.value} })}
                 style={{width:"100%",border:"1px solid #d0d7de",borderRadius:10,padding:"8px 10px"}} />
        </>
      )}

      {data.kind === "delay" && (
        <>
          <label style={{fontSize:13,display:"block"}}>Milliseconds</label>
          <input type="number" min={0} step={100} defaultValue={data.config.ms ?? 1000}
                 onChange={(e)=>onApply({ config:{...data.config, ms:Number(e.target.value)} })}
                 style={{width:"100%",border:"1px solid #d0d7de",borderRadius:10,padding:"8px 10px"}} />
        </>
      )}

      {data.kind === "output" && (
        <>
          <label style={{fontSize:13,display:"block"}}>Channel</label>
          <select defaultValue={data.config.channel || "log"}
                  onChange={(e)=>onApply({ config:{...data.config, channel:e.target.value} })}
                  style={{width:"100%",border:"1px solid #d0d7de",borderRadius:10,padding:"8px 10px"}}>
            <option value="log">Log</option>
            <option value="event">EventBus</option>
            <option value="webhook">Webhook</option>
            <option value="db">Database</option>
          </select>
        </>
      )}
    </div>
  );
}

export default function FlowStudio() {
  const [nodes, setNodes, onNodesChange] = useNodesState([]);
  const [edges, setEdges, onEdgesChange] = useEdgesState([]);
  const [selected, setSelected] = useState(null);
  const [name, setName] = useLocalStorage("repweb.flowstudio.name", "New Flow");
  const [layoutDir, setLayoutDir] = useLocalStorage("repweb.flowstudio.dir", "LR");
  const [persist, setPersist] = useLocalStorage(LS_KEY, { nodes: [], edges: [] });
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if ((persist.nodes || []).length || (persist.edges || []).length) {
      setNodes(persist.nodes || []);
      setEdges(persist.edges || []);
    }
  }, []);
  useEffect(() => { setPersist({ nodes, edges }); }, [nodes, edges, setPersist]);

  const onConnect = useCallback((connection) => {
    setEdges((eds) => addEdge({ ...connection, type: "smoothstep", markerEnd: { type: MarkerType.ArrowClosed } }, eds));
  }, [setEdges]);

  const onNodeClick = useCallback((_, node) => setSelected(node), []);
  const onPaneClick = useCallback(() => setSelected(null), []);

  const flowRef = useRef(null);
  const onDrop = useCallback((event) => {
    event.preventDefault();
    const bounds = flowRef.current?.getBoundingClientRect() || { left: 0, top: 0 };
    const payload = JSON.parse(event.dataTransfer.getData("application/x-repweb-node"));
    const pos = { x: event.clientX - bounds.left, y: event.clientY - bounds.top };
    setNodes((ns) => ns.concat({
      id: crypto.randomUUID(),
      type: "default",
      position: pos,
      data: { kind: payload.kind, name: nextName(payload.kind, ns), config: {} },
    }));
  }, [setNodes]);
  const onDragOver = useCallback((event) => { event.preventDefault(); event.dataTransfer.dropEffect = "copy"; }, []);
  const applyPatch = useCallback((patch) => {
    if (!selected) return;
    setNodes((ns) => ns.map((n) => n.id === selected.id ? { ...n, data: { ...n.data, ...patch, config: { ...n.data.config, ...(patch.config || {}) } } } : n));
  }, [selected, setNodes]);
  const deleteSelected = useCallback(() => {
    if (!selected) return;
    setEdges((es) => es.filter((e) => e.source !== selected.id && e.target !== selected.id));
    setNodes((ns) => ns.filter((n) => n.id !== selected.id));
    setSelected(null);
  }, [selected, setEdges, setNodes]);

  const exportJson = () => {
    const blob = new Blob([JSON.stringify({ name, nodes, edges }, null, 2)], { type: "application/json" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url; a.download = `${name.replace(/\s+/g, "-")}.flow.json`; a.click();
    URL.revokeObjectURL(url);
  };
  const importJson = (file) => {
    const reader = new FileReader();
    reader.onload = () => {
      try {
        const obj = JSON.parse(String(reader.result));
        setNodes(obj.nodes || []); setEdges(obj.edges || []); if (obj.name) setName(obj.name);
      } catch { alert("Invalid flow JSON"); }
    };
    reader.readAsText(file);
  };

  const saveFlow = async () => {
    setSaving(true);
    try {
      const res = await fetch('/api/flows', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, nodes, edges })
      });
      if (!res.ok) throw new Error(`Save failed (${res.status})`);
      const { id } = await res.json();
      alert(`Saved! Flow ID: ${id}`);
    } catch (e) {
      alert(e.message || 'Save error');
    } finally {
      setSaving(false);
    }
  };

  const autoLayout = async () => {
    try {
      const dagre = await import("dagre");
      const g = new dagre.graphlib.Graph();
      g.setDefaultEdgeLabel(() => ({}));
      g.setGraph({ rankdir: layoutDir, nodesep: 50, ranksep: 80 });
      nodes.forEach((n) => g.setNode(n.id, { width: 220, height: 80 }));
      edges.forEach((e) => g.setEdge(e.source, e.target));
      dagre.layout(g);
      setNodes(nodes.map((n) => {
        const p = g.node(n.id);
        return { ...n, position: { x: p.x - 110, y: p.y - 40 } };
      }));
    } catch (e) { console.warn("Dagre not available", e); }
  };

  const runMock = () => {
    const byId = new Map(nodes.map((n) => [n.id, n]));
    const out = new Map();
    edges.forEach((e) => { out.set(e.source, (out.get(e.source) || []).concat(e.target)); });
    const triggers = nodes.filter((n) => n.data.kind === "trigger");
    const trace = [];
    const visit = (id, depth = 0) => {
      const n = byId.get(id); if (!n) return;
      trace.push(`${" ".repeat(depth * 2)}• ${n.data.kind.toUpperCase()} — ${n.data.name}`);
      (out.get(id) || []).forEach((t) => visit(t, depth + 1));
    };
    triggers.forEach((t) => visit(t.id));
    alert(trace.join("\n") || "No trigger nodes found.");
  };

  return (
    <div style={{
      display: "grid",
      gridTemplateColumns: "280px 1fr 340px",
      gap: 16,
      height: "100%" // parent (host div) provides viewport height
    }}>
      {/* Left panel */}
      <div style={{display:"flex",flexDirection:"column",gap:12,minWidth:260,overflow:"auto"}}>
        <div style={{border:"1px solid #d0d7de",borderRadius:12}}>
          <div style={{padding:12,fontWeight:600}}>Nodes</div>
          <div style={{padding:12,display:"grid",gap:8}}>
            {PALETTE.map((p) => (
              <div key={p.kind}
                   style={{cursor:"grab",border:"1px solid #d0d7de",borderRadius:12,padding:"8px 10px"}}
                   draggable
                   onDragStart={(e)=>e.dataTransfer.setData("application/x-repweb-node", JSON.stringify({ kind:p.kind }))}>
                <div style={{fontWeight:500}}>{p.title}</div>
                <div style={{fontSize:12,opacity:.7}}>{p.hint}</div>
              </div>
            ))}
          </div>
        </div>

        <div style={{border:"1px solid #d0d7de",borderRadius:12}}>
          <div style={{padding:12,fontWeight:600}}>Templates</div>
          <div style={{padding:12}}>
            <button style={{width:"100%",border:"1px solid #d0d7de",borderRadius:12,padding:"8px 10px"}}
                    onClick={()=>{
              const id = crypto.randomUUID();
              setNodes([
                { id, type:"default", position:{ x:80,y:120 }, data:{ kind:"trigger", name:"Every 5 min", config:{ mode:"cron", cron:"*/5 * * * *" } } },
                { id:id+"a", type:"default", position:{ x:360,y:120 }, data:{ kind:"http", name:"Fetch JSON", config:{ method:"GET", url:"/api/ping" } } },
                { id:id+"b", type:"default", position:{ x:640,y:120 }, data:{ kind:"transform", name:"Pick items", config:{ lang:"jq", code:".items[]" } } },
                { id:id+"c", type:"default", position:{ x:920,y:120 }, data:{ kind:"output", name:"Log", config:{ channel:"log" } } },
              ]);
              setEdges([
                { id:"e1", source:id, target:id+"a", type:"smoothstep", markerEnd:{ type: MarkerType.ArrowClosed } },
                { id:"e2", source:id+"a", target:id+"b", type:"smoothstep", markerEnd:{ type: MarkerType.ArrowClosed } },
                { id:"e3", source:id+"b", target:id+"c", type:"smoothstep", markerEnd:{ type: MarkerType.ArrowClosed } },
              ]);
            }}>Cron → HTTP → Transform → Output</button>
          </div>
        </div>
      </div>

      {/* Canvas */}
      <div style={{position:"relative",border:"1px solid #d0d7de",borderRadius:12,overflow:"hidden",height:"100%"}}>
        {/* top toolbar */}
        <div style={{position:"absolute",left:0,right:0,top:0,zIndex:10,padding:8,
                     display:"flex",alignItems:"center",justifyContent:"space-between",
                     gap:8,background:"rgba(255,255,255,.7)",backdropFilter:"blur(4px)"}}>
          <div style={{display:"flex",gap:8,alignItems:"center"}}>
            <input value={name} onChange={(e)=>setName(e.target.value)}
                   style={{width:260,border:"1px solid #d0d7de",borderRadius:10,padding:"8px 10px"}} />
            <select value={layoutDir} onChange={(e)=>setLayoutDir(e.target.value)}
                    style={{border:"1px solid #d0d7de",borderRadius:10,padding:"8px 10px"}}>
              <option value="LR">Left → Right</option>
              <option value="TB">Top → Bottom</option>
            </select>
            <button onClick={autoLayout} style={{border:"1px solid #d0d7de",borderRadius:10,padding:"8px 10px"}}>Layout</button>
          </div>
          <div style={{display:"flex",gap:8}}>
            <button onClick={runMock} style={{border:"1px solid #d0d7de",borderRadius:10,padding:"8px 10px"}}>Run</button>

            <button onClick={saveFlow}
                    disabled={saving}
                    style={{border:"1px solid #2da44e",borderRadius:10,padding:"8px 10px",
                            background: saving ? "#94d3a2" : "#2da44e", color:"#fff", opacity: saving ? .8 : 1}}>
              {saving ? "Saving…" : "Save"}
            </button>

            <button onClick={exportJson} style={{border:"1px solid #d0d7de",borderRadius:10,padding:"8px 10px"}}>Export</button>
            <label style={{display:"inline-flex",gap:8,alignItems:"center",border:"1px solid #d0d7de",borderRadius:10,padding:"8px 10px",cursor:"pointer"}}>
              Import
              <input type="file" accept=".json" style={{display:"none"}} onChange={(e)=>e.target.files && importJson(e.target.files[0])} />
            </label>
          </div>
        </div>

        {/* reactflow host must have explicit size */}
        <div ref={flowRef} onDrop={onDrop} onDragOver={onDragOver}
             style={{width:"100%",height:"100%"}}>
          <ReactFlow
            nodeTypes={nodeTypes}
            nodes={nodes}
            edges={edges}
            onNodesChange={onNodesChange}
            onEdgesChange={onEdgesChange}
            onConnect={onConnect}
            onNodeClick={onNodeClick}
            onPaneClick={onPaneClick}
            fitView
            style={{width:"100%",height:"100%"}}
          >
            <MiniMap pannable zoomable />
            <Controls />
            <Background variant={BackgroundVariant.Dots} gap={16} size={1} />
            <Panel position="bottom-left" style={{margin:8,fontSize:12,background:"rgba(255,255,255,.8)",borderRadius:8,padding:"2px 6px"}}>
              Edges: drag from node body
            </Panel>
          </ReactFlow>
        </div>
      </div>

      {/* Right panel */}
      <div style={{display:"flex",flexDirection:"column",gap:12,minWidth:320,overflow:"auto"}}>
        <NodeEditor selected={selected} onApply={applyPatch} />
        <div style={{border:"1px solid #d0d7de",borderRadius:12,padding:12}}>
          <div style={{fontWeight:600,marginBottom:4}}>Backend wiring</div>
          <div style={{fontSize:13}}>
            POST JSON to <code>/api/flows</code>. Persist to <code>flows</code> and run via Symfony Messenger.
          </div>
        </div>
        {selected && (
          <button onClick={deleteSelected}
                  style={{width:"100%",border:"1px solid #e55353",borderRadius:12,padding:"8px 10px",background:"#fff0f0"}}>
            Delete selected
          </button>
        )}
      </div>
    </div>
  );
}
