// assets/react/components/SqlStatementComposer.jsx
import React, { useEffect, useMemo, useState, useCallback } from 'react';
import ReactFlow, {
  Background, Controls, MiniMap,
  addEdge, useNodesState, useEdgesState,
  Handle, Position
} from 'reactflow';
import 'reactflow/dist/style.css';
import Editor from '@monaco-editor/react';

// --- Compact UI sizing ---
const NODE_WIDTH = 220;     // was 300
const HANDLE_SIZE = 10;     // was 12
const HANDLE_OFFSET = -8;   // was -10
const ROW_MIN_H = 22;       // was 26
const COLS_MAX_H = 200;     // was 260

/** Table node with visible drag handles per column (compact) */
function TableNode({ data }) {
  const { table, alias, columns, selectedCols, onToggleCol } = data;
  return (
    <div className="rounded-3 shadow-sm border bg-white" style={{ width: NODE_WIDTH }}>
      <div className="fw-bold px-2 py-1 border-bottom">
        {table}{alias !== table ? <span className="text-muted"> as {alias}</span> : null}
      </div>
      <div className="px-2" style={{ maxHeight: COLS_MAX_H, overflow: 'auto' }}>
        {columns.map((c) => (
          <div
            key={c.COLUMN_NAME}
            className="d-flex align-items-center gap-2 py-1 position-relative"
            style={{ minHeight: ROW_MIN_H }}
          >
            <Handle
              type="source"
              position={Position.Left}
              id={`src:${c.COLUMN_NAME}`}
              style={{ background: 'blue', width: HANDLE_SIZE, height: HANDLE_SIZE, left: HANDLE_OFFSET, borderRadius: '50%' }}
            />
            <input
              type="checkbox"
              className="form-check-input"
              checked={!!selectedCols[c.COLUMN_NAME]}
              onChange={() => onToggleCol(alias, c.COLUMN_NAME)}
            />
            <code className="small">{c.COLUMN_NAME}</code>
            <span className="text-muted small ms-auto">{c.DATA_TYPE}</span>
            <Handle
              type="target"
              position={Position.Right}
              id={`tgt:${c.COLUMN_NAME}`}
              style={{ background: 'red', width: HANDLE_SIZE, height: HANDLE_SIZE, right: HANDLE_OFFSET, borderRadius: '50%' }}
            />
          </div>
        ))}
      </div>
    </div>
  );
}
const nodeTypes = { tableNode: TableNode };

export default function SqlStatementComposer({ apiBase = '/api/sqlc' }) {
  const [schema, setSchema] = useState(null);
  const [activeSchema, setActiveSchema] = useState('');
  const [allSchemas, setAllSchemas] = useState([]);

  const [nodes, setNodes, onNodesChange] = useNodesState([]);
  const [edges, setEdges, onEdgesChange] = useEdgesState([]);
  const [selectedCols, setSelectedCols] = useState({}); // { alias: { col: true } }
  const [sql, setSql] = useState('');
  const [previewRows, setPreviewRows] = useState([]);
  const [err, setErr] = useState('');
  const [search, setSearch] = useState('');

  // Context menu state (single source of truth)
  const [menu, setMenu] = useState({ show: false, x: 0, y: 0, kind: null, target: null });
  const closeMenu = useCallback(
    () => setMenu({ show: false, x: 0, y: 0, kind: null, target: null }),
    []
  );

  // --- helpers
  const fetchSchema = useCallback(async (schemaName) => {
    setErr('');
    const q = schemaName ? `?schema=${encodeURIComponent(schemaName)}` : '';
    const r = await fetch(`${apiBase}/schema${q}`);
    const j = await r.json();
    if (!r.ok) throw new Error(j.error || 'Failed loading schema');
    setSchema(j);
    setActiveSchema(j.schema || schemaName || '');
  }, [apiBase]);

  const resetGraph = useCallback(() => {
    setNodes([]); setEdges([]); setSelectedCols({}); setSql(''); setPreviewRows([]);
  }, [setNodes, setEdges, setSelectedCols]);

  // Load schemas (if available) then load active schema
  useEffect(() => {
    (async () => {
      try {
        const r = await fetch(`${apiBase}/schemata`);
        if (r.ok) {
          const j = await r.json();
          const names = (j.schemata || []).map(s => s.SCHEMA_NAME || s.schema_name || s);
          setAllSchemas(names);
        }
      } catch { /* optional */ }
      try {
        await fetchSchema('');
      } catch (e) {
        setErr(String(e.message || e));
      }
    })();
  }, [apiBase, fetchSchema]);

  const allTables = useMemo(
    () => (schema?.tables?.map(t => t.table_name) ?? []),
    [schema]
  );

  const columnsByTable = useMemo(() => {
    const map = new Map();
    (schema?.columns ?? []).forEach(c => {
      if (!map.has(c.TABLE_NAME)) map.set(c.TABLE_NAME, []);
      map.get(c.TABLE_NAME).push(c);
    });
    return map;
  }, [schema]);

  /** Add a table node with an auto-unique alias (table, table_1, …) */
  const onDropTable = useCallback((table) => {
    const existing = nodes.filter(n => n.data?.table === table).length;
    const alias = existing === 0 ? table : `${table}_${existing}`;
    const id = `t:${alias}`;
    if (nodes.find(n => n.id === id)) return;

    const x = 60 + nodes.length * 40;
    const y = 60 + nodes.length * 20;

    setNodes(nds => nds.concat({
      id,
      type: 'tableNode',
      position: { x, y },
      data: {
        table,
        alias,
        columns: columnsByTable.get(table) || [],
        selectedCols: selectedCols[alias] || {},
        onToggleCol: (a, c) => setSelectedCols(s => ({
          ...s,
          [a]: { ...(s[a] || {}), [c]: !(s[a]?.[c]) }
        })),
      },
    }));
  }, [nodes, setNodes, columnsByTable, selectedCols]);

  // keep node.data.selectedCols in sync with global selectedCols
  useEffect(() => {
    setNodes(nds => nds.map(n => ({
      ...n,
      data: { ...n.data, selectedCols: selectedCols[n.data.alias] || {} }
    })));
  }, [selectedCols, setNodes]);

  // nodeId -> node
  const nodeIndex = useMemo(() => {
    const idx = {};
    nodes.forEach(n => { idx[n.id] = n; });
    return idx;
  }, [nodes]);

  // Connect columns (store physical tables + aliases)
  const onConnect = useCallback((params) => {
    const parseCol = (s) => s?.split(':')[1];
    const srcCol = parseCol(params.sourceHandle);
    const tgtCol = parseCol(params.targetHandle);

    const srcNode = nodeIndex[params.source];
    const tgtNode = nodeIndex[params.target];
    if (!srcNode || !tgtNode) return;

    const srcTable = srcNode.data.table;
    const srcAlias = srcNode.data.alias;
    const tgtTable = tgtNode.data.table;
    const tgtAlias = tgtNode.data.alias;

    const id = `${srcAlias}.${srcCol}=${tgtAlias}.${tgtCol}`;
    setEdges((eds) =>
      addEdge(
        { ...params, id, label: 'INNER',
          data: { joinType: 'INNER', srcTable, srcAlias, srcCol, tgtTable, tgtAlias, tgtCol } },
        eds
      )
    );
  }, [setEdges, nodeIndex]);

  const updateEdgeJoin = useCallback((edgeId, jt) => {
    setEdges(eds => eds.map(e =>
      e.id === edgeId ? ({ ...e, label: jt, data: { ...e.data, joinType: jt } }) : e
    ));
  }, [setEdges]);

  // ---- Right-click menus
  const onEdgeContextMenu = useCallback((e, edge) => {
    e.preventDefault();
    setMenu({ show: true, x: e.clientX, y: e.clientY, kind: 'edge', target: edge });
  }, []);

  const onNodeContextMenu = useCallback((e, node) => {
    e.preventDefault();
    setMenu({ show: true, x: e.clientX, y: e.clientY, kind: 'node', target: node });
  }, []);

  const onPaneClick = useCallback(() => closeMenu(), [closeMenu]);

  const deleteEdge = useCallback((id) => {
    setEdges(eds => eds.filter(e => e.id !== id));
    closeMenu();
  }, [setEdges, closeMenu]);

  const deleteNode = useCallback((id) => {
    const node = nodes.find(n => n.id === id);
    const alias = node?.data?.alias;

    setNodes(nds => nds.filter(n => n.id !== id));
    setEdges(eds => eds.filter(e => e.source !== id && e.target !== id));

    if (alias) {
      setSelectedCols(s => {
        const next = { ...s };
        delete next[alias];
        return next;
      });
    }
    closeMenu();
  }, [nodes, setNodes, setEdges, setSelectedCols, closeMenu]);

  // --- Generate SQL (aliases + ANDed field joins, normalized direction)
  useEffect(() => {
    if (!nodes.length) { setSql(''); return; }

    const fromNode = nodes[0];
    const fromTable = fromNode.data.table;
    const fromAlias = fromNode.data.alias;

    const aliasToTable = {};
    nodes.forEach(n => { aliasToTable[n.data.alias] = n.data.table; });

    // SELECT
    const sel = [];
    Object.entries(selectedCols).forEach(([alias, cols]) => {
      Object.entries(cols).forEach(([c, on]) => { if (on) sel.push(`\`${alias}\`.\`${c}\``); });
    });
    if (sel.length === 0) sel.push(`\`${fromAlias}\`.*`);

    // FROM
    const fromClause =
      fromAlias !== fromTable ? `FROM \`${fromTable}\` AS \`${fromAlias}\`` : `FROM \`${fromTable}\``;

    // GROUP JOINS
    const grouped = {};
    edges.forEach(e => {
      const jt = e.data?.joinType || 'INNER';
      let sA = e.data.srcAlias, sC = e.data.srcCol;
      let tA = e.data.tgtAlias, tC = e.data.tgtCol;

      if (tA === fromAlias) { [sA, tA] = [tA, sA]; [sC, tC] = [tC, sC]; }

      const key = `${jt}:${tA}`;
      if (!grouped[key]) grouped[key] = { jt, tgtAlias: tA, conds: [] };
      grouped[key].conds.push(`\`${sA}\`.\`${sC}\` = \`${tA}\`.\`${tC}\``);
    });

    const joinLines = Object.values(grouped).map(g => {
      const tgtTable = aliasToTable[g.tgtAlias] || g.tgtAlias;
      const right = g.tgtAlias !== tgtTable ? `\`${tgtTable}\` AS \`${g.tgtAlias}\`` : `\`${tgtTable}\``;
      return `${g.jt} JOIN ${right} ON ${g.conds.join(' AND ')}`;
    });

    setSql([`SELECT`, `  ${sel.join(',\n  ')}`, fromClause, ...joinLines].join('\n'));
  }, [nodes, edges, selectedCols]);

  const doPreview = async () => {
    setErr('');
    try {
      const r = await fetch(`${apiBase}/preview`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ sql, limit: 100 }),
      });
      const j = await r.json();
      if (!r.ok) throw new Error(j.error || 'Preview failed');
      setPreviewRows(j.rows || []);
    } catch (e) { setErr(String(e.message || e)); }
  };

  // --- Sidebar with schema selector + tables at top
  const Sidebar = () => (
    <div className="border-end d-flex flex-column" style={{ width: 320 }}>
      <div className="p-2 border-bottom bg-white">
        <div className="mb-2">
          <label className="form-label mb-1 small fw-bold">Schema</label>
          <div className="input-group input-group-sm">
            <span className="input-group-text"><i className="bi bi-database" /></span>
            <select
              className="form-select"
              value={activeSchema}
              onChange={async (e) => {
                const next = e.target.value;
                resetGraph();
                try { await fetchSchema(next); } catch (err) { setErr(String(err.message || err)); }
              }}
            >
              {[...(allSchemas.length ? allSchemas : [activeSchema || (schema?.schema || '')])]
                .filter(Boolean)
                .map(s => <option key={s} value={s}>{s}</option>)}
            </select>
          </div>
        </div>

        <div className="d-flex align-items-center">
          <strong className="me-2">Tables</strong>
          <div className="flex-grow-1 input-group input-group-sm">
            <span className="input-group-text"><i className="bi bi-search" /></span>
            <input className="form-control" placeholder="Filter tables…" value={search}
                   onChange={e => setSearch(e.target.value)} />
          </div>
        </div>
      </div>

      <div className="p-2 flex-grow-1" style={{ overflow: 'auto' }}>
        {allTables
          .filter(t => t.toLowerCase().includes(search.toLowerCase()))
          .map(t => (
            <div key={t} className="d-flex justify-content-between align-items-center py-1">
              <span>{t}</span>
              <button className="btn btn-sm btn-outline-primary" onClick={() => onDropTable(t)}>Add</button>
            </div>
          ))}
      </div>
    </div>
  );

  return (
    <div className="d-flex" style={{ height: '80vh', minHeight: 540 }}>
      <Sidebar />

      <div className="flex-grow-1 d-flex flex-column">
        {/* Canvas smaller on initial load (40%) */}
        <div style={{ height: '40%' }}>
          <ReactFlow
            nodes={nodes}
            edges={edges}
            onNodesChange={onNodesChange}
            onEdgesChange={onEdgesChange}
            onConnect={onConnect}
            nodeTypes={nodeTypes}
            fitView
            fitViewOptions={{ padding: 0.6 }}
            onPaneClick={onPaneClick}
            onEdgeContextMenu={onEdgeContextMenu}
            onNodeContextMenu={onNodeContextMenu}
          >
            <Background />
            <MiniMap pannable zoomable />
            <Controls />
          </ReactFlow>
        </div>

        {/* Bottom: 60% — SQL & Preview split 50/50 */}
        <div className="border-top p-2" style={{ height: '60%', minHeight: 320 }}>
          <div className="h-100" style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
            {/* SQL */}
            <div className="d-flex flex-column min-w-0">
              <div className="d-flex justify-content-between align-items-center mb-2">
                <strong>SQL</strong>
                <div className="btn-group btn-group-sm">
                  <button className="btn btn-primary" onClick={doPreview} disabled={!sql}>Preview</button>
                  <button className="btn btn-outline-secondary"
                          onClick={() => navigator.clipboard.writeText(sql)} disabled={!sql}>
                    Copy SQL
                  </button>
                </div>
              </div>
              <pre className="small border rounded p-2 bg-light mb-2"
                   style={{ whiteSpace: 'pre-wrap', maxHeight: 72, overflow: 'auto' }}>
{sql || '— SQL will appear here once you add a table —'}
              </pre>
              <div className="flex-grow-1 min-vh-0">
                <Editor height="100%" language="sql" value={sql}
                        onChange={(v) => setSql(v ?? '')}
                        options={{ automaticLayout: true, minimap: { enabled: false }, wordWrap: 'on' }} />
              </div>
            </div>

            {/* Preview */}
            <div className="d-flex flex-column min-w-0">
              <strong className="mb-2">Preview</strong>
              {err && <div className="alert alert-danger mb-2">{err}</div>}
              <div className="flex-grow-1 border rounded bg-white" style={{ overflow: 'auto', minHeight: 0 }}>
                {previewRows.length > 0 ? (
                  <table className="table table-sm table-striped mb-0">
                    <thead>
                      <tr>{Object.keys(previewRows[0]).map(h => <th key={h}>{h}</th>)}</tr>
                    </thead>
                    <tbody>
                      {previewRows.map((r, i) => (
                        <tr key={i}>
                          {Object.values(r).map((v, j) => (<td key={j}>{String(v)}</td>))}
                        </tr>
                      ))}
                    </tbody>
                  </table>
                ) : (
                  <div className="text-muted small p-2">Preview results will appear here.</div>
                )}
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Context menu */}
      {menu.show && (
        <div
          className="card shadow position-fixed"
          style={{ top: menu.y, left: menu.x, zIndex: 2147483647, width: 180 }}
          onMouseLeave={closeMenu}
        >
          {menu.kind === 'edge' ? (
            <ul className="list-group list-group-flush">
              <li className="list-group-item small fw-bold">Relation</li>
              {['INNER','LEFT','RIGHT','FULL'].map(t => (
                <li
                  key={t}
                  className="list-group-item list-group-item-action"
                  role="button"
                  onClick={() => { updateEdgeJoin(menu.target.id, t); closeMenu(); }}
                >
                  Set {t}
                </li>
              ))}
              <li
                className="list-group-item list-group-item-action text-danger"
                role="button"
                onClick={() => deleteEdge(menu.target.id)}
              >
                Delete relation
              </li>
            </ul>
          ) : (
            <ul className="list-group list-group-flush">
              <li className="list-group-item small fw-bold">Table</li>
              <li
                className="list-group-item list-group-item-action text-danger"
                role="button"
                onClick={() => deleteNode(menu.target.id)}
              >
                Remove table
              </li>
            </ul>
          )}
        </div>
      )}
    </div>
  );
}
