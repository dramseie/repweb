export async function fetchJSON(url, options = {}) {
  const res = await fetch(url, {
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'fetch' },
    credentials: 'same-origin',
    ...options,
  });
  if (!res.ok) {
    let msg = await res.text();
    try { const j = JSON.parse(msg); msg = j.error || msg; } catch {}
    throw new Error(msg || ('HTTP ' + res.status));
  }
  return res.status === 204 ? null : res.json();
}

export const Api = {
  getAllTiles: () => fetchJSON('/api/tiles'),
  getMyTiles:  () => fetchJSON('/api/user-tiles'),
  addUserTile: (tileId) => fetchJSON('/api/user-tiles', { method: 'POST', body: JSON.stringify({ tileId }) }),
  removeUserTile: (userTileId) => fetchJSON(`/api/user-tiles/${userTileId}`, { method: 'DELETE' }),
  reorderUserTiles: (order) => fetchJSON('/api/user-tiles/reorder', { method: 'PATCH', body: JSON.stringify({ order }) }),
  updateLayout: (id, layout) => fetchJSON(`/api/user-tiles/${id}/layout`, { method: 'PATCH', body: JSON.stringify({ layout }) }),
};
