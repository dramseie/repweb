// assets/frw/components/fields/FieldMultiSelect.jsx
import React, { useEffect, useState } from 'react';
import api from '../../lib/api';
export default function FieldMultiSelect({ field, value=[], onChange }){
  const [opts, setOpts] = useState(field.options || []);
  useEffect(()=>{ if (field.source?.startsWith('lookup:')) { api.lookup(field.source.split(':')[1]).then(setOpts); } }, [field.source]);
  function toggle(val){ const set = new Set(value||[]); set.has(val)? set.delete(val): set.add(val); onChange([...set]); }
  return (
    <fieldset className="block">
      <div className="text-sm mb-1">{field.label || field.key}</div>
      <div className="flex flex-wrap gap-2">
        {opts.map(o=> (
          <label key={o.value||o} className={`px-2 py-1 border rounded cursor-pointer ${ (value||[]).includes(o.value||o) ? 'bg-gray-200' : '' }`}>
            <input type="checkbox" className="mr-1" checked={(value||[]).includes(o.value||o)} onChange={()=>toggle(o.value||o)} />
            {o.label||o}
          </label>
        ))}
      </div>
    </fieldset>
  );
}