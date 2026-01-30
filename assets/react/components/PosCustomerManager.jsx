// assets/react/components/PosCustomerManager.jsx
import React, { useEffect, useMemo, useState } from 'react';

const STATUS_OPTIONS = [
  { value: 'active', label: 'Actif' },
  { value: 'inactive', label: 'Inactif' },
  { value: 'banned', label: 'Banni' },
  { value: 'test', label: 'Test' },
];

const emptyCustomer = {
  id: null,
  first_name: '',
  last_name: '',
  phone: '',
  email: '',
  address: '',
  notes_public: '',
  notes_private: '',
  gdpr_ok: false,
  status: 'active',
};

const api = {
  async searchCustomers(term) {
    const qs = new URLSearchParams({ q: term ?? '' });
    const resp = await fetch(`/api/pos/customers?${qs.toString()}`);
    if (!resp.ok) throw new Error('search_fail');
    return resp.json();
  },
  async loadCustomer(id) {
    const resp = await fetch(`/api/pos/customers/${id}`);
    if (!resp.ok) throw new Error(resp.status === 404 ? 'not_found' : 'load_fail');
    return resp.json();
  },
  async createCustomer(payload) {
    const resp = await fetch('/api/pos/customers', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    return resp.json();
  },
  async updateCustomer(id, payload) {
    const resp = await fetch(`/api/pos/customers/${id}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    return resp.json();
  },
  async deleteCustomer(id) {
    const resp = await fetch(`/api/pos/customers/${id}`, { method: 'DELETE' });
    return resp.json();
  },
};

function formatDateTime(sqlTs) {
  if (!sqlTs) return '—';
  const d = new Date(sqlTs.replace(' ', 'T'));
  if (Number.isNaN(d.getTime())) return sqlTs;
  return `${d.toLocaleDateString()} ${d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
}

function centsToEuro(cents) {
  if (cents === null || cents === undefined) return '—';
  return (Number(cents) / 100).toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' });
}

function CustomerList({ items, selectedId, onSelect, loading }) {
  return (
    <div className="border rounded bg-white h-100 d-flex flex-column">
      <div className="p-2 border-bottom">
        <strong>Résultats</strong>
      </div>
      <div className="flex-grow-1 overflow-auto" style={{ minHeight: 0 }}>
        {!loading && !items.length && (
          <div className="text-muted small p-3">Aucun client.</div>
        )}
        {loading && (
          <div className="text-muted small p-3">Chargement…</div>
        )}
        <div className="list-group list-group-flush">
          {items.map((item) => (
            <button
              key={item.id}
              className={`list-group-item list-group-item-action d-flex justify-content-between align-items-center ${selectedId === item.id ? 'active' : ''}`}
              onClick={() => onSelect(item.id)}
            >
              <div>
                <div className="fw-semibold">{item.last_name} {item.first_name}</div>
                <div className="small text-muted">{item.phone || item.email || '—'}</div>
              </div>
              <span className="badge text-bg-light text-uppercase">{item.status}</span>
            </button>
          ))}
        </div>
      </div>
    </div>
  );
}

function CustomerForm({
  form,
  setForm,
  onSave,
  onDelete,
  saving,
  deleting,
  error,
  mode,
  canDelete,
}) {
  const updateField = (field, value) => {
    setForm((prev) => ({ ...prev, [field]: value }));
  };

  return (
    <div className="border rounded bg-white h-100 d-flex flex-column">
      <div className="p-3 border-bottom d-flex justify-content-between align-items-center">
        <div>
          <strong>{mode === 'create' ? 'Nouveau client' : `Client #${form.id}`}</strong>
          {mode !== 'create' && (
            <span className="ms-2 badge text-bg-secondary text-uppercase">{form.status}</span>
          )}
        </div>
        {mode !== 'create' && canDelete && (
          <button className="btn btn-sm btn-outline-danger" disabled={deleting || saving} onClick={onDelete}>
            {deleting ? 'Suppression…' : 'Supprimer'}
          </button>
        )}
      </div>
      <div className="p-3 overflow-auto" style={{ minHeight: 0 }}>
        {error && <div className="alert alert-danger py-2 small">{error}</div>}

        <div className="row g-3">
          <div className="col-md-6">
            <label className="form-label small">Prénom</label>
            <input className="form-control" value={form.first_name} onChange={(e) => updateField('first_name', e.target.value)} />
          </div>
          <div className="col-md-6">
            <label className="form-label small">Nom</label>
            <input className="form-control" value={form.last_name} onChange={(e) => updateField('last_name', e.target.value)} />
          </div>
          <div className="col-md-6">
            <label className="form-label small">Téléphone</label>
            <input className="form-control" value={form.phone ?? ''} onChange={(e) => updateField('phone', e.target.value)} />
          </div>
          <div className="col-md-6">
            <label className="form-label small">Email</label>
            <input className="form-control" value={form.email ?? ''} onChange={(e) => updateField('email', e.target.value)} />
          </div>
          <div className="col-12">
            <label className="form-label small">Adresse</label>
            <textarea className="form-control" rows={2} value={form.address ?? ''} onChange={(e) => updateField('address', e.target.value)} />
          </div>
          <div className="col-md-6">
            <label className="form-label small">Statut</label>
            <select className="form-select" value={form.status} onChange={(e) => updateField('status', e.target.value)}>
              {STATUS_OPTIONS.map((opt) => (
                <option key={opt.value} value={opt.value}>{opt.label}</option>
              ))}
            </select>
          </div>
          <div className="col-md-6 d-flex align-items-center">
            <div className="form-check mt-3">
              <input
                id="gdpr-ok"
                className="form-check-input"
                type="checkbox"
                checked={!!form.gdpr_ok}
                onChange={(e) => updateField('gdpr_ok', e.target.checked)}
              />
              <label className="form-check-label" htmlFor="gdpr-ok">GDPR OK</label>
            </div>
          </div>
          <div className="col-12">
            <label className="form-label small">Notes publiques</label>
            <textarea className="form-control" rows={2} value={form.notes_public ?? ''} onChange={(e) => updateField('notes_public', e.target.value)} />
          </div>
          <div className="col-12">
            <label className="form-label small">Notes privées</label>
            <textarea className="form-control" rows={2} value={form.notes_private ?? ''} onChange={(e) => updateField('notes_private', e.target.value)} />
          </div>
        </div>
      </div>
      <div className="border-top p-3 d-flex justify-content-end gap-2">
        <button className="btn btn-primary" onClick={onSave} disabled={saving}>
          {saving ? 'Enregistrement…' : mode === 'create' ? 'Créer' : 'Enregistrer'}
        </button>
      </div>
    </div>
  );
}

function InteractionSection({ detail }) {
  const appointments = detail?.appointments ?? [];
  const orders = detail?.orders ?? [];

  return (
    <div className="mt-3">
      <div className="row g-3">
        <div className="col-xl-6">
          <div className="card h-100">
            <div className="card-header py-2"><strong>Rendez-vous</strong></div>
            <div className="card-body p-0">
              <div className="table-responsive" style={{ maxHeight: 240 }}>
                <table className="table table-sm mb-0">
                  <thead className="table-light">
                    <tr>
                      <th>Date</th>
                      <th>Statut</th>
                      <th>Durée</th>
                    </tr>
                  </thead>
                  <tbody>
                    {appointments.length === 0 && (
                      <tr>
                        <td colSpan={3} className="text-center text-muted py-3">Aucun rendez-vous</td>
                      </tr>
                    )}
                    {appointments.map((appt) => (
                      <tr key={appt.id}>
                        <td>{formatDateTime(appt.start_at)}</td>
                        <td className="text-uppercase">{appt.status}</td>
                        <td>{appt.planned_minutes ? `${appt.planned_minutes} min` : '—'}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
        <div className="col-xl-6">
          <div className="card h-100">
            <div className="card-header py-2"><strong>Préstations</strong></div>
            <div className="card-body p-0">
              <div className="table-responsive" style={{ maxHeight: 240 }}>
                <table className="table table-sm mb-0">
                  <thead className="table-light">
                    <tr>
                      <th>Date</th>
                      <th>Montant</th>
                      <th>Pourboire</th>
                    </tr>
                  </thead>
                  <tbody>
                    {orders.length === 0 && (
                      <tr>
                        <td colSpan={3} className="text-center text-muted py-3">Aucune prestation</td>
                      </tr>
                    )}
                    {orders.map((order) => (
                      <tr key={order.id}>
                        <td>{formatDateTime(order.created_at)}</td>
                        <td>{centsToEuro(order.total_cents)}</td>
                        <td>{centsToEuro(order.tip_cents)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

export default function PosCustomerManager() {
  const [query, setQuery] = useState('');
  const [results, setResults] = useState([]);
  const [loadingResults, setLoadingResults] = useState(false);

  const [selectedId, setSelectedId] = useState(null);
  const [detail, setDetail] = useState(null);
  const [loadingDetail, setLoadingDetail] = useState(false);

  const [form, setForm] = useState(emptyCustomer);
  const [mode, setMode] = useState('view'); // 'view' | 'create'
  const [saving, setSaving] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [error, setError] = useState(null);

  const loadResults = async (term) => {
    setLoadingResults(true);
    setError(null);
    try {
      const data = await api.searchCustomers(term ?? '');
      setResults((data.items ?? []).map((item) => ({ ...item, status: item.status || 'active' })));
    } catch (err) {
      console.error('search error', err);
      setError('Erreur lors de la recherche des clients.');
    } finally {
      setLoadingResults(false);
    }
  };

  useEffect(() => {
    loadResults('');
  }, []);

  const handleSelect = async (id) => {
    setMode('view');
    setError(null);
    setSelectedId(id);
    setDetail(null);
    setLoadingDetail(true);
    try {
      const data = await api.loadCustomer(id);
      setDetail(data);
      setForm({
        id: data.customer.id,
        first_name: data.customer.first_name ?? '',
        last_name: data.customer.last_name ?? '',
        phone: data.customer.phone ?? '',
        email: data.customer.email ?? '',
        address: data.customer.address ?? '',
        notes_public: data.customer.notes_public ?? '',
        notes_private: data.customer.notes_private ?? '',
        gdpr_ok: !!data.customer.gdpr_ok,
        status: data.customer.status ?? 'active',
      });
    } catch (err) {
      console.error('load customer error', err);
      setError('Impossible de charger ce client.');
    } finally {
      setLoadingDetail(false);
    }
  };

  const startCreate = () => {
    setMode('create');
    setSelectedId(null);
    setDetail(null);
    setForm(emptyCustomer);
    setError(null);
  };

  const handleSave = async () => {
    setError(null);
    const payload = {
      first_name: form.first_name.trim(),
      last_name: form.last_name.trim(),
      phone: form.phone?.trim() || null,
      email: form.email?.trim() || null,
      address: form.address?.trim() || null,
      notes_public: form.notes_public?.trim() || null,
      notes_private: form.notes_private?.trim() || null,
      gdpr_ok: form.gdpr_ok ? 1 : 0,
      status: form.status,
    };

    if (!payload.first_name || !payload.last_name) {
      setError('Nom et prénom sont obligatoires.');
      return;
    }

    setSaving(true);
    try {
      if (mode === 'create') {
        const res = await api.createCustomer(payload);
        if (!res?.ok) {
          setError(res?.error || 'Erreur lors de la création.');
          return;
        }
        await loadResults(query);
        await handleSelect(res.id);
      } else if (form.id) {
        const res = await api.updateCustomer(form.id, payload);
        if (res?.error) {
          setError(res.error);
          return;
        }
        await handleSelect(form.id);
        await loadResults(query);
      }
    } catch (err) {
      console.error('save error', err);
      setError('Échec de la sauvegarde du client.');
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async () => {
    if (!form.id) return;
    const confirmDelete = window.confirm('Supprimer ce client ?');
    if (!confirmDelete) return;

    setDeleting(true);
    setError(null);
    try {
      const res = await api.deleteCustomer(form.id);
      if (!res?.ok) {
        setError(res?.error || 'Suppression impossible.');
        return;
      }
      await loadResults(query);
      setSelectedId(null);
      setDetail(null);
      setForm(emptyCustomer);
    } catch (err) {
      console.error('delete error', err);
      setError('Échec de la suppression du client.');
    } finally {
      setDeleting(false);
    }
  };

  const canDelete = useMemo(() => {
    if (!detail) return false;
    const appts = detail.appointments ?? [];
    const orders = detail.orders ?? [];
    return appts.length === 0 && orders.length === 0;
  }, [detail]);

  return (
    <div className="row g-3">
      <div className="col-xl-3">
        <div className="mb-2 d-flex gap-2">
          <input
            className="form-control"
            placeholder="Recherche (nom, téléphone, email, id)"
            value={query}
            onChange={(e) => {
              const value = e.target.value;
              setQuery(value);
              loadResults(value);
            }}
          />
          <button className="btn btn-outline-secondary" onClick={() => loadResults(query)}>🔄</button>
        </div>
        <button className="btn btn-sm btn-outline-primary mb-2" onClick={startCreate}>
          + Nouveau client
        </button>
        <CustomerList items={results} selectedId={selectedId} onSelect={handleSelect} loading={loadingResults} />
      </div>
      <div className="col-xl-9">
        {mode === 'create' ? (
          <CustomerForm
            form={form}
            setForm={setForm}
            onSave={handleSave}
            onDelete={handleDelete}
            saving={saving}
            deleting={deleting}
            error={error}
            mode="create"
            canDelete={false}
          />
        ) : selectedId ? (
          <>
            <CustomerForm
              form={form}
              setForm={setForm}
              onSave={handleSave}
              onDelete={handleDelete}
              saving={saving}
              deleting={deleting}
              error={error}
              mode="edit"
              canDelete={canDelete}
            />
            {!loadingDetail && detail && <InteractionSection detail={detail} />}
            {loadingDetail && <div className="alert alert-info mt-3">Chargement des interactions…</div>}
          </>
        ) : (
          <div className="border rounded bg-white p-4 h-100 d-flex align-items-center justify-content-center text-muted">
            Sélectionnez un client à gauche ou créez-en un nouveau.
          </div>
        )}
      </div>
    </div>
  );
}
