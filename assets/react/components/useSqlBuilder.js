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
