import React, { useState } from 'react';

export default function Toolbar({ onSave, onAutoLayout, onReload, types, onFilterType, onSearch }){
  const [q,setQ] = useState('');
  return (
    <div className="cmdb-toolbar">
      <button className="btn btn-primary" onClick={onSave}>Save layout (Ctrl+S)</button>
      <button className="btn btn-outline-secondary" onClick={onAutoLayout}>Auto-layout</button>
      <button className="btn btn-outline-secondary" onClick={onReload}>Reload</button>
      <select className="form-select w-auto" defaultValue="" onChange={e=>onFilterType(e.target.value)}>
        <option value="">All types</option>
        {types.map(t => <option key={t.code} value={t.code}>{t.label}</option>)}
      </select>
      <div style={{marginLeft:'auto', display:'flex', gap:'.5rem'}}>
        <input className="form-control" placeholder="Search CI or name" value={q} onChange={e=>setQ(e.target.value)} />
        <button className="btn btn-outline-secondary" onClick={()=>onSearch(q)}>Search</button>
      </div>
    </div>
  );
}
