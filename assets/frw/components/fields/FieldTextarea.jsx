// assets/frw/components/fields/FieldTextarea.jsx
import React from 'react';
export default function FieldTextarea({ field, value, onChange }){
  return (
    <label className="block">
      <div className="text-sm mb-1">{field.label || field.key}</div>
      <textarea className="border rounded p-2 w-full" value={value||''} onChange={e=>onChange(e.target.value)} rows={field.rows||4} placeholder={field.placeholder||''} />
    </label>
  );
}