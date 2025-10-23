// assets/react/qw/QuestionnaireBuilder.tsx
import React, { useEffect, useMemo, useState } from 'react';
import { DndContext, PointerSensor, useSensor, useSensors, DragEndEvent } from '@dnd-kit/core';
import { getQuestionnaire, addItem, patchItem, addField, listFields, patchField } from './api';
import type { QuestionnaireDTO, ItemDTO, Id, UiType } from './types';

/* ---------- helpers ---------- */
function toTree(items: ItemDTO[]) {
  const map = new Map<Id, any>();
  items.forEach(i => map.set(i.id, { ...i, children: [] }));
  const roots: any[] = [];
  items.forEach(i => {
    const n = map.get(i.id);
    if (i.parentId == null) roots.push(n);
    else map.get(i.parentId)?.children.push(n);
  });
  const sortRec = (nodes: any[]) => {
    nodes.sort((a, b) => a.sort - b.sort);
    nodes.forEach((n: any) => sortRec(n.children));
  };
  sortRec(roots);
  return roots;
}

type FieldRowModel = {
  id: number;
  ui_type?: string;   // backend may send snake_case
  uiType?: string;    // future compatibility
  placeholder?: string | null;
  default_value?: any;
  min_value?: number | null;
  max_value?: number | null;
  step_value?: number | null;
  options_json?: any; // where we store "label" non-destructively
};

/* ---------- node card (on canvas) ---------- */
function NodeCard({
  n,
  onSelect,
  onMove,
  onSettings,
}: {
  n: any;
  onSelect: (id: Id) => void;
  onMove: (id: number, dir: 'up' | 'down' | 'in' | 'out') => void;
  onSettings: (id: number) => void;
}) {
  return (
    <div className="qw-card" onClick={() => onSelect(n.id)}>
      <div className="qw-card-hd">
        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
          <span style={{ color: '#9ca3af', fontSize: 12, width: 40 }}>{n.outline || ''}</span>
          <span style={{ fontWeight: 600 }}>{n.title}</span>
          <span className="qw-chip">{n.type}</span>
        </div>
        <div className="qw-toolbar" style={{ display: 'flex', gap: 6 }}>
          <div>
            <button className="qw-btn" onClick={e => { e.stopPropagation(); onMove(n.id, 'up'); }}>↑</button>
            <button className="qw-btn" onClick={e => { e.stopPropagation(); onMove(n.id, 'down'); }}>↓</button>
            <button className="qw-btn" onClick={e => { e.stopPropagation(); onMove(n.id, 'in'); }}>→</button>
            <button className="qw-btn" onClick={e => { e.stopPropagation(); onMove(n.id, 'out'); }}>←</button>
          </div>
          <button className="qw-btn" onClick={e => { e.stopPropagation(); onSettings(n.id); }}>Settings</button>
        </div>
      </div>
      {n.help && <div className="qw-card-help" dangerouslySetInnerHTML={{ __html: n.help }} />}
    </div>
  );
}

/* ---------- slide-over inspector ---------- */
function InspectorSlideOver({
  open,
  item,
  onClose,
  onSave,
}: {
  open: boolean;
  item: ItemDTO | null;
  onClose: () => void;
  onSave: (upd: Partial<ItemDTO>) => void;
}) {
  const [title, setTitle] = useState(item?.title ?? '');
  const [required, setRequired] = useState<boolean>(!!item?.required);
  const [help, setHelp] = useState(item?.help ?? '');

  useEffect(() => {
    setTitle(item?.title ?? '');
    setRequired(!!item?.required);
    setHelp(item?.help ?? '');
  }, [item?.id]);

  return (
    <div
      style={{
        position: 'fixed',
        inset: '0 0 0 auto',
        width: 420,
        transform: open ? 'translateX(0)' : 'translateX(100%)',
        transition: 'transform .2s ease',
        background: '#fff',
        borderLeft: '1px solid #e5e7eb',
        boxShadow: '-4px 0 24px rgba(0,0,0,.08)',
        zIndex: 1040,
      }}
      aria-hidden={!open}
    >
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '10px 12px', borderBottom: '1px solid #eee' }}>
        <div style={{ fontWeight: 600 }}>Settings</div>
        <button className="qw-btn" onClick={onClose}>Close</button>
      </div>
      {!item ? (
        <div style={{ padding: 12, color: '#6b7280', fontSize: 14 }}>No item selected.</div>
      ) : (
        <div style={{ padding: 12 }}>
          <div style={{ color: '#6b7280', fontSize: 12 }}>Outline</div>
          <div style={{ marginBottom: 10 }}>{item.outline || '—'}</div>

          <div style={{ marginBottom: 10 }}>
            <div style={{ color: '#6b7280', fontSize: 12 }}>Title</div>
            <input value={title} onChange={e => setTitle(e.target.value)} className="form-control" />
          </div>

          <div style={{ marginBottom: 10 }}>
            <div style={{ color: '#6b7280', fontSize: 12 }}>Help (HTML allowed)</div>
            <textarea value={help} onChange={e => setHelp(e.target.value)} className="form-control" rows={6} />
          </div>

          <label style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 14 }}>
            <input type="checkbox" checked={required} onChange={e => setRequired(e.target.checked)} />
            Required
          </label>

          <div style={{ paddingTop: 12 }}>
            <button className="qw-btn" onClick={() => onSave({ title, help, required })}>Save</button>
          </div>
        </div>
      )}
    </div>
  );
}

/* ---------- field preview + inline editor ---------- */
function oneLine(label: string, node: React.ReactNode) {
  return (
    <div style={{ display: 'grid', gridTemplateColumns: '120px 1fr', gap: 8, alignItems: 'center' }}>
      <div style={{ fontSize: 12, color: '#6b7280' }}>{label}</div>
      <div>{node}</div>
    </div>
  );
}

function FieldPreview({
  f, onChange, disabled,
}: {
  f: FieldRowModel;
  disabled?: boolean;
  onChange: (patch: Partial<FieldRowModel>) => void;
}) {
  const ui = (f.ui_type ?? f.uiType ?? '').toLowerCase();

  const label = f.options_json?.label ?? '';
  const ph = f.placeholder ?? '';
  const defVal = f.default_value ?? '';
  const min = f.min_value ?? '';
  const max = f.max_value ?? '';
  const step = f.step_value ?? '';

  // simple renderer (disabled preview)
  const control = (() => {
    switch (ui) {
      case 'textarea':
        return <textarea disabled placeholder={ph || ' '} className="form-control" rows={3} defaultValue={defVal} />;
      case 'select':
      case 'multiselect': {
        const opts = Array.isArray(f.options_json?.options)
          ? f.options_json.options
          : [];
        return (
          <select disabled multiple={ui === 'multiselect'} className="form-control">
            {opts.length === 0 ? <option>(no options)</option> : opts.map((o: any, i: number) => (
              <option key={i} value={o.value ?? o}>{o.label ?? String(o)}</option>
            ))}
          </select>
        );
      }
      case 'radio':
        return <input disabled type="radio" className="form-check-input" />;
      case 'checkbox':
        return <input disabled type="checkbox" className="form-check-input" />;
      case 'slider':
        return <input disabled type="range" min={min as any} max={max as any} step={step as any} className="form-range" />;
      case 'color':
        return <input disabled type="color" className="form-control form-control-color" defaultValue={defVal || '#000000'} />;
      case 'date':
        return <input disabled type="date" className="form-control" defaultValue={defVal} />;
      case 'time':
        return <input disabled type="time" className="form-control" defaultValue={defVal} />;
      case 'integer':
        return <input disabled type="number" className="form-control" placeholder={ph} defaultValue={defVal} />;
      case 'image':
      case 'file':
        return <input disabled type="file" className="form-control" />;
      case 'voice':
      case 'video':
        return <input disabled type="file" className="form-control" />;
      case 'toggle':
        return <input disabled type="checkbox" role="switch" className="form-check-input" />;
      default: // 'input', 'autocomplete', 'wysiwyg', 'chainselect', 'daterange', etc — preview as text
        return <input disabled type="text" className="form-control" placeholder={ph} defaultValue={defVal} />;
    }
  })();

  // editor
  return (
    <div style={{ border: '1px dashed #e5e7eb', borderRadius: 8, padding: 8, background: '#fafafa' }}>
      <div style={{ marginBottom: 6, fontWeight: 500, fontSize: 12, color: '#374151' }}>{ui || 'field'}</div>
      {oneLine('Label', (
        <input
          disabled={disabled}
          className="form-control"
          value={label}
          onChange={e => onChange({ options_json: { ...(f.options_json ?? {}), label: e.target.value } })}
        />
      ))}
      {oneLine('Placeholder', (
        <input
          disabled={disabled}
          className="form-control"
          value={ph}
          onChange={e => onChange({ placeholder: e.target.value })}
        />
      ))}
      {oneLine('Default', (
        <input
          disabled={disabled}
          className="form-control"
          value={defVal}
          onChange={e => onChange({ default_value: e.target.value })}
        />
      ))}
      {['slider', 'integer'].includes(ui) && (
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 8, marginTop: 6 }}>
          <input disabled={disabled} className="form-control" placeholder="min" value={min as any}
                 onChange={e => onChange({ min_value: e.target.value === '' ? null : Number(e.target.value) })} />
          <input disabled={disabled} className="form-control" placeholder="max" value={max as any}
                 onChange={e => onChange({ max_value: e.target.value === '' ? null : Number(e.target.value) })} />
          <input disabled={disabled} className="form-control" placeholder="step" value={step as any}
                 onChange={e => onChange({ step_value: e.target.value === '' ? null : Number(e.target.value) })} />
        </div>
      )}
      <div style={{ marginTop: 8 }}>{label && <label style={{ display: 'block', fontSize: 12, color: '#6b7280' }}>{label}</label>}{control}</div>
    </div>
  );
}

/* row containing previews of all fields for a question, with inline Edit/Saved state */
function FieldsRow({ itemId, nonce }: { itemId: number; nonce: number }) {
  const [rows, setRows] = useState<FieldRowModel[]>([]);
  const [openId, setOpenId] = useState<number | null>(null);
  const [savingId, setSavingId] = useState<number | null>(null);

  useEffect(() => {
    let alive = true;
    (async () => {
      const data = await listFields(itemId);
      if (!alive) return;
      setRows(Array.isArray(data) ? data : []);
      setOpenId(null);
    })();
    return () => { alive = false; };
  }, [itemId, nonce]);

  if (rows.length === 0) return null;

  return (
    <div style={{ margin: '6px 0 0 56px', display: 'grid', gap: 10 }}>
      {rows.map(r => {
        const label = r.options_json?.label ?? '';
        const title = (r.ui_type ?? r.uiType ?? 'field').toLowerCase();
        const isOpen = openId === r.id;

        return (
          <div key={r.id} className="rounded-2xl" style={{ background: '#fff', border: '1px solid #eee', padding: 8 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 12 }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                <span className="qw-chip">{title}</span>
                {label ? <span style={{ fontSize: 12, color: '#6b7280' }}>{label}</span> : <span style={{ fontSize: 12, color: '#9ca3af' }}>(no label)</span>}
              </div>
              <div style={{ display: 'flex', gap: 8 }}>
                <button className="qw-btn" onClick={() => setOpenId(isOpen ? null : r.id)}>{isOpen ? 'Close' : 'Edit'}</button>
              </div>
            </div>

            {isOpen && (
              <div style={{ marginTop: 8 }}>
                <FieldPreview
                  f={r}
                  disabled={savingId === r.id}
                  onChange={(patch) => {
                    // optimistic local update
                    setRows(prev => prev.map(x => x.id === r.id ? ({ ...x, ...patch, options_json: patch.options_json ?? x.options_json }) : x));
                  }}
                />
                <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: 8, gap: 8 }}>
                  <button className="qw-btn" disabled={savingId === r.id} onClick={() => setOpenId(null)}>Cancel</button>
                  <button
                    className="qw-btn"
                    disabled={savingId === r.id}
                    onClick={async () => {
                      setSavingId(r.id);
                      // find updated row
                      const current = rows.find(x => x.id === r.id);
                      try {
                        await patchField(r.id, {
                          // persist only the editable props
                          placeholder: current?.placeholder ?? null,
                          default_value: current?.default_value ?? null,
                          min_value: current?.min_value ?? null,
                          max_value: current?.max_value ?? null,
                          step_value: current?.step_value ?? null,
                          options_json: {
                            ...(current?.options_json ?? {}),
                            label: current?.options_json?.label ?? '',
                          },
                        });
                        setOpenId(null);
                      } finally {
                        setSavingId(null);
                      }
                    }}
                  >
                    {savingId === r.id ? 'Saving…' : 'Save'}
                  </button>
                </div>
              </div>
            )}
          </div>
        );
      })}
    </div>
  );
}

/* ---------- main ---------- */
export default function QuestionnaireBuilder({ qid }: { qid: number }) {
  const sensors = useSensors(useSensor(PointerSensor));
  const [q, setQ] = useState<QuestionnaireDTO | null>(null);
  const [selectedId, setSelectedId] = useState<Id | null>(null);
  const [inspectorOpen, setInspectorOpen] = useState(false);
  const [busy, setBusy] = useState(false);
  const [fieldsNonce, setFieldsNonce] = useState(0); // bump to re-fetch fields after drop

  useEffect(() => { getQuestionnaire(qid).then(setQ); }, [qid]);

  const tree = useMemo(() => (q ? toTree(q.items) : []), [q]);

  async function refresh() { setQ(await getQuestionnaire(qid)); }

  async function handleAdd(parentId: Id | null, type: 'header' | 'question') {
    if (!q) return;
    setBusy(true);
    await addItem(q.id, { parentId, type, title: type === 'header' ? 'Header' : 'Question', sort: 999 });
    await refresh();
    setBusy(false);
  }

  async function handleMove(id: number, dir: 'up' | 'down' | 'in' | 'out') {
    if (!q) return;
    const it = q.items.find(i => i.id === id);
    if (!it) return;

    const siblings = q.items
      .filter(i => (i.parentId ?? null) === (it.parentId ?? null))
      .sort((a, b) => a.sort - b.sort);
    const idx = siblings.findIndex(s => s.id === id);

    if (dir === 'up' && idx > 0) await patchItem(id, { sort: siblings[idx - 1].sort });
    if (dir === 'down' && idx < siblings.length - 1) await patchItem(id, { sort: siblings[idx + 1].sort });
    if (dir === 'in' && idx > 0) {
      const newParent = siblings[idx - 1];
      await patchItem(id, { parentId: newParent.id, sort: 999 });
    }
    if (dir === 'out' && it.parentId != null) {
      const parent = q.items.find(n => n.id === it.parentId)!;
      await patchItem(id, { parentId: parent.parentId ?? null, sort: parent.sort + 1 });
    }
    await refresh();
  }

  function onSettings(id: number) {
    setSelectedId(id);
    setInspectorOpen(true);
  }

  function makeDropHandlers(n: any, depth: number) {
    return {
      onDragOver: (ev: React.DragEvent<HTMLDivElement>) => {
        ev.preventDefault();
        try { ev.dataTransfer.dropEffect = 'copy'; } catch {}
      },
      onDrop: async (ev: React.DragEvent<HTMLDivElement>) => {
        ev.preventDefault();

        const el = ev.dataTransfer.getData('application/qw-element');
        if (el === 'header' || el === 'question') {
          await handleAdd(n.id, el as any);
          return;
        }

        const fld = ev.dataTransfer.getData('application/qw-field');
        if (fld && n.type === 'question') {
          try {
            setBusy(true);
            await addField(Number(n.id), { ui_type: fld });
            setFieldsNonce(x => x + 1); // refresh the field previews
          } finally {
            setBusy(false);
          }
        }
      },
      style: { marginLeft: depth * 16 },
    };
  }

  function render(nodes: any[], depth = 0) {
    return nodes.map((n: any) => (
      <div key={n.id} {...makeDropHandlers(n, depth)}>
        <NodeCard n={n} onSelect={() => setSelectedId(n.id)} onMove={handleMove} onSettings={onSettings} />
        {n.type === 'question' && <FieldsRow itemId={Number(n.id)} nonce={fieldsNonce} />}
        {n.children?.length > 0 && render(n.children, depth + 1)}
      </div>
    ));
  }

  function onDragEnd(_e: DragEndEvent) {}

  const elements = [
    { key: 'header', label: 'Header' },
    { key: 'question', label: 'Question' },
  ];
  const fields: UiType[] = [
    'input', 'textarea', 'wysiwyg', 'select', 'multiselect', 'radio', 'checkbox',
    'slider', 'color', 'date', 'time', 'daterange', 'integer', 'autocomplete',
    'chainselect', 'image', 'file', 'voice', 'video', 'toggle',
  ];

  const selected = selectedId && q ? q.items.find(i => i.id === selectedId) ?? null : null;

  return (
    <div className="qw-shell">
      {/* LEFT pane */}
      <aside className="qw-left qw-sticky">
        <h2 className="qw-h1" style={{ fontSize: 18 }}>Elements</h2>
        <div className="qw-group" style={{ marginBottom: 12 }}>
          {elements.map(e => (
            <div
              key={e.key}
              className="qw-pill"
              draggable
              onDragStart={ev => ev.dataTransfer.setData('application/qw-element', e.key)}
            >
              {e.label}
            </div>
          ))}
        </div>

        <h2 className="qw-h1" style={{ fontSize: 18 }}>Fields</h2>
        <div className="qw-group">
          {fields.map(f => (
            <div
              key={f}
              className="qw-pill"
              draggable
              onDragStart={ev => ev.dataTransfer.setData('application/qw-field', f)}
            >
              {f}
            </div>
          ))}
        </div>
      </aside>

      {/* RIGHT pane */}
      <main className="qw-right">
        <div style={{ marginBottom: 12 }}>
          <h1 className="qw-h1">{q?.title ?? 'Questionnaire'}</h1>
        </div>

        <DndContext sensors={sensors} onDragEnd={onDragEnd}>
          {q && render(toTree(q.items))}
        </DndContext>

        {busy && <div style={{ marginTop: 8, fontSize: 12, color: '#6b7280' }}>Saving…</div>}
      </main>

      {/* Slide-over inspector */}
      <InspectorSlideOver
        open={inspectorOpen}
        item={selected ?? null}
        onClose={() => setInspectorOpen(false)}
        onSave={async (upd: Partial<ItemDTO>) => {
          if (!selected) return;
          await patchItem(Number(selected.id), upd);
          await refresh();
          setInspectorOpen(false);
        }}
      />
    </div>
  );
}
