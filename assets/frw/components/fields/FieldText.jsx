// assets/frw/components/fields/FieldText.jsx
import React from 'react';
export default function FieldText({ field, value, onChange }){
  return (
    <label className="block">
      <div className="text-sm mb-1">{field.label || field.key}</div>
      <input className="border rounded p-2 w-full" value={value||''} onChange={e=>onChange(e.target.value)} placeholder={field.placeholder||''} />
    </label>
  );
}