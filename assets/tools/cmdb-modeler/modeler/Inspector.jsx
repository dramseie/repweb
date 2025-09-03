import React, { useEffect, useState } from 'react';
import AttributeEditor from './AttributeEditor.jsx';
import * as api from '../api';

export default function Inspector({ sel, onUpdate, onDelete }) {
  const [name, setName] = useState(sel?.data?.label || '');
  const [attrDefs, setAttrDefs] = useState([]);
  const [attrPatch, setAttrPatch] = useState({});

  useEffect(()=> {
    setName(sel?.data?.label || '');
    setAttrDefs([]); setAttrPatch({});
    if (sel?.id) api.getNodeAttributes(sel.id).then(d => setAttrDefs(d.attrs || []));
  }, [sel]);

  const onAttrChange = (code, value) => setAttrPatch(p => ({ ...p, [code]: value }));

  if (!sel) return <div className="p-3 text-muted">No selection</div>;
  return (
    <div className="p-2">
      <div className="fw-bold mb-2">Inspector</div>
      <div className="small text-muted mb-2">CI: {sel.id}</div>

      <label className="form-label">Name</label>
      <input className="form-control mb-3" value={name} onChange={e=>setName(e.target.value)} />

      <AttributeEditor attrs={attrDefs} onChange={onAttrChange} />

      <div className="d-flex gap-2 mt-3">
        <button className="btn btn-primary"
          onClick={()=>onUpdate({ name, attrs: attrPatch })}>Save</button>
        <button className="btn btn-outline-danger" onClick={onDelete}>Delete</button>
      </div>
    </div>
  );
}
