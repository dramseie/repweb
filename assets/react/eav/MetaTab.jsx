// assets/react/eav/MetaTab.jsx
import React, { useEffect, useMemo, useRef, useState } from 'react';
import { HotTable } from '@handsontable/react';
import Handsontable from 'handsontable';
import 'handsontable/dist/handsontable.full.min.css';
import '../../styles/eav-meta.css';
import api from './api-meta';
import { typeCols, attrCols } from './hot-columns';

export default function MetaTab() {
  // Tenants data + selection
  const [tenants, setTenants] = useState([]);
  const [selTenantId, setSelTenantId] = useState(0);
  const [tenantForm, setTenantForm] = useState({ id: null, name: '', code: '' });

  // Types & Attributes for selected tenant
  const [types, setTypes] = useState([]);
  const [attrs, setAttrs] = useState([]);

  // Pivot state
  const [mapsByType, setMapsByType] = useState({}); // { [typeId]: Set<attribute_id> }
  const [highlightTypeId, setHighlightTypeId] = useState(null);

  // Grid refs
  const refTypes = useRef();
  const refAttrs = useRef();

  // ---------- Loaders ----------
  const loadTenants = async () => {
    const rows = await api.tenants.list();
    setTenants(rows);
    // Keep selection if still present
    if (!rows.find(t => t.id === selTenantId)) {
      setSelTenantId(rows[0]?.id ?? 0);
    }
  };

  const loadTypes = async (tenantId) => {
    const list = await api.types.list(tenantId);
    setTypes(list);
  };

  const loadAttrs = async (tenantId) => {
    const list = await api.attributes.list(tenantId);
    setAttrs(list);
  };

  const loadMapsAllTypes = async (tenantId, typeList) => {
    const out = {};
    for (const t of typeList) {
      const rows = await api.maps.listByType(t.id);
      out[t.id] = new Set(rows.map(r => r.attribute_id));
    }
    setMapsByType(out);
  };

  // ---------- Effects ----------
  useEffect(() => { loadTenants(); }, []);
  useEffect(() => {
    if (!selTenantId) return;
    const t = tenants.find(x => x.id === selTenantId);
    setTenantForm(t ? { id: t.id, name: t.name, code: t.code } : { id: null, name: '', code: '' });
    (async () => {
      await loadTypes(selTenantId);
      await loadAttrs(selTenantId);
    })();
  }, [selTenantId, tenants]);

  useEffect(() => {
    if (!selTenantId) { setMapsByType({}); return; }
    (async () => { await loadMapsAllTypes(selTenantId, types); })();
  }, [selTenantId, types]);

  // ---------- Tenant UI ----------
  const onTenantSelect = (e) => {
    const v = e.target.value;
    if (v === '_new') {
      setTenantForm({ id: null, name: '', code: '' });
    } else {
      setSelTenantId(Number(v));
    }
  };

  const saveTenant = async () => {
    const { id, name, code } = tenantForm;
    if (!code || !/^[a-z0-9_]{2,64}$/.test(code)) return alert('Code: [a-z0-9_]{2,64}');
    if (!name) return alert('Name required');

    if (id) {
      await api.tenants.update(id, { name, code });
    } else {
      const res = await api.tenants.create({ name, code });
      setSelTenantId(res.id);
    }
    await loadTenants();
  };

  const deleteTenant = async () => {
    const { id } = tenantForm;
    if (!id) return;
    if (!confirm('Delete tenant?')) return;
    await api.tenants.remove(id);
    await loadTenants();
  };

  // ---------- Types grid handlers ----------
  const afterChangeTypes = async (changes, source) => {
    if (!changes || source === 'loadData') return;
    for (const [row, prop, oldVal, newVal] of changes) {
      if (oldVal === newVal) continue;
      const rec = types[row];
      if (!rec) continue;
      await api.types.update(rec.id, { [prop]: newVal });
    }
  };

  const addType = async () => {
    if (!selTenantId) return alert('Select a tenant first.');
    const code = prompt('New type code (e.g. vm):');
    if (!code) return;
    const name = prompt('Name (e.g. Virtual Machine):') || code;
    const icon = prompt('Icon (e.g. fa-solid fa-computer):') || '';
    await api.types.create({ tenant_id: selTenantId, code, name, icon });
    await loadTypes(selTenantId);
  };

  const deleteSelectedType = async () => {
    const ht = refTypes.current?.hotInstance; if (!ht) return;
    const sel = ht.getSelectedLast(); if (!sel) return;
    const [r1,,r2] = sel; const row = Math.min(r1,r2);
    const rec = types[row]; if (!rec) return;
    if (!confirm(`Delete type "${rec.code}"?`)) return;
    await api.types.remove(rec.id);
    await loadTypes(selTenantId);
  };

  // render button in types grid
  useEffect(() => {
    Handsontable.renderers.registerRenderer('html', (instance, td, row, col, prop, value) => {
      td.innerHTML = `<button class="btn btn-sm btn-outline-primary">Attributes</button>`;
      td.style.textAlign = 'center';
    });
  }, []);

  const typesAfterOnCellMouseDown = (event, coords) => {
    const colProp = typeCols[coords.col]?.data;
    if (colProp === '__actions') {
      const rec = types[coords.row];
      setHighlightTypeId(rec?.id || null);
      // Scroll pivot into view
      document.getElementById('pivot-box')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  };

  // ---------- Attributes grid handlers ----------
  const afterChangeAttrs = async (changes, source) => {
    if (!changes || source === 'loadData') return;
    for (const [row, prop, oldVal, newVal] of changes) {
      if (oldVal === newVal) continue;
      const rec = attrs[row]; if (!rec) continue;
      await api.attributes.update(rec.id, { [prop]: newVal });
    }
  };

  const addAttr = async () => {
    if (!selTenantId) return alert('Select a tenant first.');
    const code = prompt('New attribute code (e.g. cpu_count):');
    if (!code) return;
    const label = prompt('Label (e.g. CPU Count):') || code;
    const data_type = prompt('Data type (string|text|integer|decimal|boolean|datetime|json|reference|ip|cidr):','string') || 'string';
    await api.attributes.create({ tenant_id: selTenantId, code, label, data_type });
    await loadAttrs(selTenantId);
  };

  const deleteSelectedAttr = async () => {
    const ht = refAttrs.current?.hotInstance; if (!ht) return;
    const sel = ht.getSelectedLast(); if (!sel) return;
    const [r1,,r2] = sel; const row = Math.min(r1,r2);
    const rec = attrs[row]; if (!rec) return;
    if (!confirm(`Delete attribute "${rec.code}"?`)) return;
    await api.attributes.remove(rec.id);
    await loadAttrs(selTenantId);
  };

  // ---------- Pivot (Attributes × Types) ----------
  const typeColsInPivot = useMemo(() => types.map(t => ({ key: t.id, code: t.code })), [types]);

  const pivotRows = useMemo(() => {
    return attrs.map(a => {
      const row = { attribute_id: a.id, code: a.code, label: a.label, data_type: a.data_type };
      for (const t of types) {
        const mapped = mapsByType[t.id]?.has(a.id) || false;
        row[`type_${t.id}`] = mapped;
      }
      return row;
    });
  }, [attrs, types, mapsByType]);

  const toggleMap = async (typeId, attrId, toChecked) => {
    if (!selTenantId) return;
    if (toChecked) {
      await api.maps.create({ type_id: typeId, attribute_id: attrId }); // tenant_id inferred server-side
    } else {
      await api.maps.remove(typeId, attrId);
    }
    // refresh only that type’s map
    const newSet = new Set((await api.maps.listByType(typeId)).map(r => r.attribute_id));
    setMapsByType(prev => ({ ...prev, [typeId]: newSet }));
  };

  // custom renderer for pivot checkboxes (and highlight a chosen type column)
  useEffect(() => {
    Handsontable.renderers.registerRenderer('pivotCheck', (instance, td, row, col, prop, value) => {
      const typeId = Number(String(prop).replace('type_', ''));
      td.innerHTML = `<input type="checkbox" ${value ? 'checked' : ''} />`;
      td.style.textAlign = 'center';
      if (highlightTypeId && typeId === highlightTypeId) {
        td.style.outline = '2px solid #0d6efd';
      }
    });
  }, [highlightTypeId]);

  const pivotColumns = useMemo(() => {
    const base = [
      { data: 'attribute_id', readOnly: true, width: 60 },
      { data: 'code', readOnly: true },
      { data: 'label', readOnly: true },
      { data: 'data_type', readOnly: true },
    ];
    const dyn = typeColsInPivot.map(t => ({
      data: `type_${t.key}`,
      renderer: 'pivotCheck',
      width: 90
    }));
    return [...base, ...dyn];
  }, [typeColsInPivot]);

  const afterOnCellMouseDownPivot = async (event, coords) => {
    const prop = pivotColumns[coords.col]?.data;
    if (!prop || !String(prop).startsWith('type_')) return;
    const typeId = Number(String(prop).replace('type_', ''));
    const attr = pivotRows[coords.row];
    const current = !!attr[prop];
    // Optimistic toggle in UI
    attr[prop] = !current;
    // Persist
    await toggleMap(typeId, attr.attribute_id, !current);
  };

  // ---------- UI ----------
  return (
    <div className="eav-meta">
      {/* Tenants selector + editor */}
      <section className="card">
        <div className="card-body">
          <div className="row g-3 align-items-end">
            <div className="col-md-4">
              <label className="form-label">Tenant</label>
              <select className="form-select" value={tenantForm.id ?? selTenantId} onChange={onTenantSelect}>
                {tenants.map(t => <option key={t.id} value={t.id}>{t.name} ({t.code})</option>)}
                <option value="_new">+ _new</option>
              </select>
            </div>
            <div className="col-md-3">
              <label className="form-label">Name</label>
              <input className="form-control" value={tenantForm.name}
                     onChange={e=>setTenantForm(f=>({...f, name: e.target.value}))} />
            </div>
            <div className="col-md-3">
              <label className="form-label">Code</label>
              <input className="form-control" value={tenantForm.code}
                     onChange={e=>setTenantForm(f=>({...f, code: e.target.value}))} />
            </div>
            <div className="col-md-2 d-flex gap-2">
              <button className="btn btn-primary w-100" onClick={saveTenant}>{tenantForm.id ? 'Save' : 'Create'}</button>
              {tenantForm.id && <button className="btn btn-outline-danger w-100" onClick={deleteTenant}>Delete</button>}
            </div>
          </div>
        </div>
      </section>

      <div className="meta-grid">
        {/* Types list */}
        <section>
          <header>
            <h3>Entity Types</h3>
            <div className="actions">
              <button className="btn btn-sm btn-primary" onClick={addType}>+ New</button>
              <button className="btn btn-sm btn-outline-danger" onClick={deleteSelectedType}>Delete</button>
            </div>
          </header>
          <HotTable
            ref={refTypes}
            data={types}
            colHeaders
            columns={typeCols}
            licenseKey="non-commercial-and-evaluation"
            stretchH="all"
            height={280}
            afterChange={afterChangeTypes}
            afterOnCellMouseDown={typesAfterOnCellMouseDown}
          />
        </section>

        {/* Attributes list */}
        <section>
          <header>
            <h3>Attributes</h3>
            <div className="actions">
              <button className="btn btn-sm btn-primary" onClick={addAttr}>+ New</button>
              <button className="btn btn-sm btn-outline-danger" onClick={deleteSelectedAttr}>Delete</button>
            </div>
          </header>
          <HotTable
            ref={refAttrs}
            data={attrs}
            colHeaders
            columns={attrCols}
            licenseKey="non-commercial-and-evaluation"
            stretchH="all"
            height={320}
            afterChange={afterChangeAttrs}
          />
        </section>

        {/* Pivot */}
        <section id="pivot-box">
          <header>
            <h3>Attribute ↔ Type mapping (checkbox = linked)</h3>
          </header>
          <HotTable
            data={pivotRows}
            colHeaders={[
              'attribute_id','code','label','data_type',
              ...typeColsInPivot.map(t => t.code)
            ]}
            columns={pivotColumns}
            licenseKey="non-commercial-and-evaluation"
            stretchH="all"
            height={Math.min(480, 100 + pivotRows.length * 28)}
            afterOnCellMouseDown={afterOnCellMouseDownPivot}
          />
        </section>
      </div>
    </div>
  );
}
