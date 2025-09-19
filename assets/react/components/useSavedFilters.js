// assets/react/components/useSavedFilters.js
import { useEffect, useState } from 'react';

export function useSavedFilters(tableKey) {
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(false);
  const base = '/api/dt-filters';

  async function reload() {
    setLoading(true);
    try {
      const r = await fetch(`${base}?table_key=${encodeURIComponent(tableKey)}`);
      const j = await r.json();
      setItems(Array.isArray(j) ? j : []);
    } finally { setLoading(false); }
  }

  async function save({ tableKey, name, isPublic, detailsJson, stateJson }) {
    const r = await fetch(base, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        table_key: tableKey,
        name,
        is_public: !!isPublic,
        details_json: detailsJson,
        state_json: stateJson ?? null,
      }),
    });
    if (!r.ok) throw new Error(`Save failed (${r.status})`);
    await reload();
  }

  async function remove(id) {
    const r = await fetch(`${base}/${id}`, { method: 'DELETE' });
    if (!r.ok) throw new Error(`Delete failed (${r.status})`);
    await reload();
  }

  useEffect(() => { reload(); }, [tableKey]);
  return { items, loading, reload, save, remove };
}
