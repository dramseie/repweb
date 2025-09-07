import React, { useEffect, useState } from 'react';

export default function GeoCiFormModal({ show, initial = {}, onClose, onSubmit, submitLabel = 'Save' }) {
  const [form, setForm] = useState(initial);
  useEffect(() => setForm(initial), [initial]);

  if (!show) return null;
  const set = (k) => (e) => setForm({ ...form, [k]: e.target.value });

  return (
    <div className="modal d-block" tabIndex="-1" role="dialog" style={{ background: 'rgba(0,0,0,0.25)' }}>
      <div className="modal-dialog modal-lg">
        <div className="modal-content">
          <div className="modal-header">
            <h5 className="modal-title">{submitLabel.includes('Create') ? 'Create CI' : 'Edit CI'}</h5>
            <button type="button" className="btn-close" onClick={onClose} />
          </div>
          <div className="modal-body">
            <div className="row g-3">
              {'ci' in form && (
                <div className="col-md-4">
                  <label className="form-label">CI</label>
                  <input className="form-control" value={form.ci || ''} onChange={set('ci')} />
                </div>
              )}
              <div className="col-md-4">
                <label className="form-label">Name</label>
                <input className="form-control" value={form.name || ''} onChange={set('name')} />
              </div>
              <div className="col-md-4">
                <label className="form-label">Status</label>
                <input className="form-control" value={form.status || 'active'} onChange={set('status')} />
              </div>
              <div className="col-md-6">
                <label className="form-label">Address</label>
                <input className="form-control" value={form.address || ''} onChange={set('address')} />
              </div>
              <div className="col-md-3">
                <label className="form-label">City</label>
                <input className="form-control" value={form.city || ''} onChange={set('city')} />
              </div>
              <div className="col-md-3">
                <label className="form-label">Country</label>
                <input className="form-control" value={form.country || ''} onChange={set('country')} />
              </div>
              <div className="col-md-3">
                <label className="form-label">Latitude</label>
                <input className="form-control" value={form.lat || ''} onChange={set('lat')} />
              </div>
              <div className="col-md-3">
                <label className="form-label">Longitude</label>
                <input className="form-control" value={form.long || form.lng || ''} onChange={set('long')} />
              </div>
              <div className="col-md-4">
                <label className="form-label">Icon (Fa class)</label>
                <input className="form-control" value={form.icon || ''} onChange={set('icon')} />
              </div>
              <div className="col-12">
                <label className="form-label">Description</label>
                <textarea className="form-control" rows={3} value={form.desc || ''} onChange={set('desc')} />
              </div>
            </div>
          </div>
          <div className="modal-footer">
            <button className="btn btn-secondary" onClick={onClose}>Cancel</button>
            <button className="btn btn-primary" onClick={() => onSubmit(form)}>{submitLabel}</button>
          </div>
        </div>
      </div>
    </div>
  );
}
