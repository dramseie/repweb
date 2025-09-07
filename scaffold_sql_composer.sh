#!/usr/bin/env bash
# Scaffold Graphical SQL Composer (Symfony + React)
# Usage: bash scaffold_sql_composer.sh
set -euo pipefail

root="${1:-.}"

echo "Scaffolding under: $root"

mkdir -p "$root/src/Controller" \
         "$root/src/Service" \
         "$root/src/Repository" \
         "$root/assets/react/components" \
         "$root/templates/sqlc"

# --- src/Controller/SqlComposerController.php ---
cat > "$root/src/Controller/SqlComposerController.php" <<'PHP'
<?php
namespace App\Controller;

use App\Service\SqlComposerService;
use App\Repository\ReportRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/sqlc')]
final class SqlComposerController extends AbstractController
{
    public function __construct(
        private Connection $db,
        private SqlComposerService $svc,
        private ReportRepository $reports,
    ) {}

    #[Route('/schema', methods: ['GET'])]
    public function schema(Request $r): JsonResponse
    {
        $schema = $r->query->get('schema', $this->db->fetchOne('SELECT DATABASE()'));
        $tables = $this->svc->listTablesAndViews($schema);
        return $this->json(['schema' => $schema, 'objects' => $tables]);
    }

    #[Route('/columns', methods: ['GET'])]
    public function columns(Request $r): JsonResponse
    {
        $schema = $r->query->get('schema', $this->db->fetchOne('SELECT DATABASE()'));
        $table  = $r->query->get('table');
        if (!$table) return $this->json(['error' => 'Missing table'], 400);
        return $this->json($this->svc->listColumns($schema, $table));
    }

    #[Route('/preview', methods: ['POST'])]
    public function preview(Request $r): JsonResponse
    {
        $body  = json_decode($r->getContent(), true) ?? [];
        $sql   = (string)($body['sql'] ?? '');
        $limit = (int)($body['limit'] ?? 200);

        try {
            $rows = $this->svc->safePreview($sql, $limit);
            return $this->json(['rows' => $rows]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/save', methods: ['POST'])]
    public function save(Request $r): JsonResponse
    {
        $data = json_decode($r->getContent(), true) ?? [];
        $now  = (new \DateTimeImmutable('now'));

        $repid = $this->reports->insert([
            'reptype'  => $data['reptype']  ?? 'sql',
            'repshort' => $data['repshort'] ?? null,
            'reptitle' => $data['reptitle'] ?? null,
            'repdesc'  => $data['repdesc']  ?? null,
            'repsql'   => $data['repsql']   ?? null,
            'repparam' => $data['repparam'] ?? null,
            'repowner' => $this->getUser()?->getUserIdentifier() ?? 'anonymous',
            'repts'    => $now->format('Y-m-d H:i:s'),
        ]);

        return $this->json(['ok' => true, 'repid' => $repid]);
    }
}
PHP

# --- src/Service/SqlComposerService.php ---
cat > "$root/src/Service/SqlComposerService.php" <<'PHP'
<?php
namespace App\Service;

use Doctrine\DBAL\Connection;

final class SqlComposerService
{
    public function __construct(private Connection $db) {}

    /** Return tables & views for a schema */
    public function listTablesAndViews(string $schema): array
    {
        $sql = "SELECT TABLE_NAME name, TABLE_TYPE type
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = ?
                ORDER BY name";
        return $this->db->fetchAllAssociative($sql, [$schema]);
    }

    /** Return columns for a table */
    public function listColumns(string $schema, string $table): array
    {
        $sql = "SELECT COLUMN_NAME name, DATA_TYPE dtype, COLUMN_TYPE ctype, IS_NULLABLE is_nullable
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
                ORDER BY ORDINAL_POSITION";
        return $this->db->fetchAllAssociative($sql, [$schema, $table]);
    }

    /** Safe preview: block non-SELECT, add LIMIT, run */
    public function safePreview(string $sql, int $limit = 200): array
    {
        $trim = ltrim($sql);
        if (!preg_match('/^SELECT/i', $trim)) {
            throw new \RuntimeException('Only SELECT statements are allowed for preview.');
        }
        // Avoid multiple statements
        if (str_contains($sql, ';')) {
            throw new \RuntimeException('Multiple SQL statements are not allowed.');
        }
        // Append LIMIT if missing (rough detection)
        if (!preg_match('/\bLIMIT\b/i', $sql)) {
            $sql = rtrim($sql, "; \t\n\r") . "\nLIMIT " . max(1, $limit);
        }
        return $this->db->fetchAllAssociative($sql);
    }
}
PHP

# --- src/Repository/ReportRepository.php ---
cat > "$root/src/Repository/ReportRepository.php" <<'PHP'
<?php
namespace App\Repository;

use Doctrine\DBAL\Connection;

final class ReportRepository
{
    public function __construct(private Connection $db) {}

    public function insert(array $fields): int
    {
        $this->db->insert('report', $fields);
        return (int)$this->db->lastInsertId();
    }
}
PHP

# --- assets/react/components/useSqlBuilder.js ---
cat > "$root/assets/react/components/useSqlBuilder.js" <<'JS'
/**
 * Build SQL from current graph state
 * selections: { [nodeId]: { alias: 't1', columns: [{name, as, fmt}], where?: string } }
 */
export function buildSelectSQL({ schema, nodes, edges, selections }) {
  if (!nodes.length) return '';

  // Base FROM = first node
  const base = nodes[0];
  const baseSel = selections[base.id] || { alias: base.data.alias || base.data.label, columns: [] };
  const baseAlias = baseSel.alias || safeAlias(base.data.label);
  const from = `\nFROM \`${schema}\`.\`${base.data.label}\` ${baseAlias}`;

  // SELECT columns
  const parts = [];
  for (const n of nodes) {
    const sel = selections[n.id];
    if (!sel || !sel.columns || sel.columns.length === 0) continue;
    const a = sel.alias || safeAlias(n.data.label);
    for (const c of sel.columns) {
      const expr = c.fmt ? c.fmt.replaceAll('{col}', `\`${a}\`.\`${c.name}\``) : `\`${a}\`.\`${c.name}\``;
      parts.push(`${expr}${c.as ? ` AS \`${c.as}\`` : ''}`);
    }
  }
  const select = parts.length ? parts.join(',\n  ') : ' * ';

  // JOINs from edges
  const joinClauses = edges.map((e) => {
    const jt = e.data?.joinType || 'INNER';
    const l = nodeById(nodes, e.source);
    const r = nodeById(nodes, e.target);
    const ls = selections[l.id] || {}; const rs = selections[r.id] || {};
    const la = ls.alias || safeAlias(l.data.label);
    const ra = rs.alias || safeAlias(r.data.label);
    const on = e.data?.on || `${la}.id = ${ra}.${guessFk(l, r)}`;
    const kw = jt === 'LEFT' ? 'LEFT JOIN' : jt === 'RIGHT' ? 'RIGHT JOIN' : 'INNER JOIN';
    return `${kw} \`${schema}\`.\`${r.data.label}\` ${ra} ON ${on}`;
  });

  return `SELECT\n  ${select}${from}${joinClauses.length ? '\n' + joinClauses.join('\n') : ''}`;
}

function nodeById(nodes, id) { return nodes.find((n) => n.id === id); }
function safeAlias(name) { return name.replace(/[^A-Za-z0-9_]/g, '_').slice(0, 16) || 't'; }
function guessFk(_l, _r) { return 'id'; } // TODO: improve by reading KEY_COLUMN_USAGE
JS

# --- assets/react/components/SqlJoinEdge.jsx ---
cat > "$root/assets/react/components/SqlJoinEdge.jsx" <<'JSX'
import React from 'react';
import { BaseEdge, getBezierPath } from 'reactflow';

export default function SqlJoinEdge({ id, sourceX, sourceY, targetX, targetY, markerEnd, data }) {
  const [path] = getBezierPath({ sourceX, sourceY, targetX, targetY });
  return (
    <>
      <BaseEdge id={id} path={path} markerEnd={markerEnd} />
      <foreignObject x={(sourceX + targetX) / 2 - 50} y={(sourceY + targetY) / 2 - 18} width={100} height={36}>
        <div
          className="bg-white border rounded px-1 py-0.5 text-xs text-center shadow"
          style={{ userSelect: 'none', cursor: 'pointer' }}
          onClick={() => {
            // Cycle join type on click (INNER -> LEFT -> RIGHT -> …)
            const order = ['INNER', 'LEFT', 'RIGHT'];
            const i = order.indexOf(data?.joinType || 'INNER');
            // mutate label; parent state updates on next render
            data.joinType = order[(i + 1) % order.length];
          }}
          title="Click to change join type"
        >
          {data?.joinType || 'INNER'}
        </div>
      </foreignObject>
    </>
  );
}
JSX

# --- assets/react/components/SqlComposerCanvas.jsx ---
cat > "$root/assets/react/components/SqlComposerCanvas.jsx" <<'JSX'
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
JSX

# --- templates/sqlc/index.html.twig ---
cat > "$root/templates/sqlc/index.html.twig" <<'TWIG'
{% extends 'base.html.twig' %}
{% block body %}
  <div id="sql-composer-root" data-api-base="/api/sqlc"></div>
  <script type="module">
    import React from 'react';
    import { createRoot } from 'react-dom/client';
    import SqlComposerCanvas from '/assets/react/components/SqlComposerCanvas.jsx';
    const el = document.getElementById('sql-composer-root');
    const apiBase = el.dataset.apiBase;
    createRoot(el).render(React.createElement(SqlComposerCanvas, { apiBase }));
  </script>
{% endblock %}
TWIG

echo "✅ Files created."
echo "Next steps:"
echo "  1) npm i reactflow"
echo "  2) npm run dev"
echo "  3) Clear Symfony cache if needed: php bin/console cache:clear"
