// assets/frw/components/fields/FieldDate.jsx
import React from 'react';
export default function FieldDate({ field, value, onChange }){
  return (
    <label className="block">
      <div className="text-sm mb-1">{field.label || field.key}</div>
      <input type="date" className="border rounded p-2 w-full" value={value||''} onChange={e=>onChange(e.target.value)} />
    </label>
  );
}