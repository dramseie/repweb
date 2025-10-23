// assets/frw/components/fields/FieldSelect.jsx
import React, { useEffect, useState } from 'react';
import api from '../../lib/api';
export default function FieldSelect({ field, value, onChange }){
  const [opts, setOpts] = useState(field.options || []);
  useEffect(()=>{ if (field.source?.startsWith('lookup:')) { api.lookup(field.source.split(':')[1]).then(setOpts); } }, [field.source]);
  return (
    <label className="block">
      <div className="text-sm mb-1">{field.label || field.key}</div>
      <select className="border rounded p-2 w-full" value={value||''} onChange={e=>onChange(e.target.value)}>
        <option value="">-- choose --</option>
        {opts.map(o=> <option key={o.value||o} value={o.value||o}>{o.label||o}</option>)}
      </select>
    </label>
  );
}