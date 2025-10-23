// assets/frw/components/fields/FieldNumber.jsx
import React from 'react';
export default function FieldNumber({ field, value, onChange }){
  return (
    <label className="block">
      <div className="text-sm mb-1">{field.label || field.key}</div>
      <input type="number" className="border rounded p-2 w-full" value={value ?? ''} min={field.min} max={field.max} onChange={e=>onChange(e.target.value===''?null:Number(e.target.value))} />
    </label>
  );
}