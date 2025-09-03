import React from 'react';

export default function AttributeEditor({ attrs, onChange }) {
  const change = (code, v) => onChange(code, v);

  return (
    <div>
      {attrs.map(a => {
        const id = `attr-${a.code}`;
        const val = a.value ?? '';
        switch (a.dataType) {
          case 'text':
            return (<div key={a.code} className="mb-2">
              <label htmlFor={id} className="form-label">{a.label}</label>
              <textarea id={id} className="form-control" rows={3}
                value={val} onChange={e=>change(a.code, e.target.value)} />
            </div>);
          case 'integer':
          case 'decimal':
            return (<div key={a.code} className="mb-2">
              <label htmlFor={id} className="form-label">{a.label}</label>
              <input id={id} type="number" className="form-control"
                value={val} onChange={e=>change(a.code, e.target.value)} />
            </div>);
          case 'boolean':
            return (<div key={a.code} className="form-check mb-2">
              <input id={id} className="form-check-input" type="checkbox"
                checked={!!val} onChange={e=>change(a.code, e.target.checked)} />
              <label htmlFor={id} className="form-check-label">{a.label}</label>
            </div>);
          case 'datetime':
            return (<div key={a.code} className="mb-2">
              <label htmlFor={id} className="form-label">{a.label}</label>
              <input id={id} type="datetime-local" className="form-control"
                value={val ? val.replace(' ', 'T').slice(0,16) : '' }
                onChange={e=>change(a.code, e.target.value ? e.target.value.replace('T',' ') : null)} />
            </div>);
          case 'json':
            return (<div key={a.code} className="mb-2">
              <label htmlFor={id} className="form-label">{a.label}</label>
              <textarea id={id} className="form-control" rows={3}
                value={typeof val === 'string' ? val : (val ? JSON.stringify(val, null, 2) : '')}
                onChange={e=>change(a.code, e.target.value)} />
            </div>);
          default: // string, ip, cidr, reference (free text for now)
            return (<div key={a.code} className="mb-2">
              <label htmlFor={id} className="form-label">{a.label}</label>
              <input id={id} className="form-control"
                value={val} onChange={e=>change(a.code, e.target.value)} />
            </div>);
        }
      })}
    </div>
  );
}
