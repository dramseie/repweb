// assets/react/eav/api-meta.js
const j = async (url, opts = {}) => {
  const r = await fetch(url, {
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin',
    ...opts
  });
  if (!r.ok) {
    let msg = r.status + ' ' + r.statusText;
    try { const body = await r.json(); if (body?.error) msg = body.error; } catch {}
    throw new Error(msg);
  }
  return r.status === 204 ? null : r.json();
};

export default {
  tenants: {
    list: () => j('/api/eav-meta/tenants'),
    create: (p) => j('/api/eav-meta/tenants', { method: 'POST', body: JSON.stringify(p) }),
    update: (id, p) => j(`/api/eav-meta/tenants/${id}`, { method: 'PATCH', body: JSON.stringify(p) }),
    remove: (id) => j(`/api/eav-meta/tenants/${id}`, { method: 'DELETE' }),
  },
  types: {
    list: (tenantId) => tenantId ? j(`/api/eav-meta/types?tenant_id=${tenantId}`) : Promise.resolve([]),
    create: (p) => j('/api/eav-meta/types', { method: 'POST', body: JSON.stringify(p) }),
    update: (id, p) => j(`/api/eav-meta/types/${id}`, { method: 'PATCH', body: JSON.stringify(p) }),
    remove: (id) => j(`/api/eav-meta/types/${id}`, { method: 'DELETE' }),
  },
  attributes: {
    list: (tenantId) => tenantId ? j(`/api/eav-meta/attributes?tenant_id=${tenantId}`) : Promise.resolve([]),
    create: (p) => j('/api/eav-meta/attributes', { method: 'POST', body: JSON.stringify(p) }),
    update: (id, p) => j(`/api/eav-meta/attributes/${id}`, { method: 'PATCH', body: JSON.stringify(p) }),
    remove: (id) => j(`/api/eav-meta/attributes/${id}`, { method: 'DELETE' }),
  },
  maps: {
    listByType: (typeId) => j(`/api/eav-meta/type-attributes?type_id=${typeId}`),
    create: (p) => j('/api/eav-meta/type-attributes', { method: 'POST', body: JSON.stringify(p) }),
    remove: (typeId, attrId) => j(`/api/eav-meta/type-attributes/${typeId}/${attrId}`, { method: 'DELETE' }),
  }
};
