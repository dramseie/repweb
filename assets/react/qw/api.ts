// assets/react/qw/api.ts

// --- Questionnaires / Items -------------------------------------------------
export async function getQuestionnaire(id: number) {
  const r = await fetch(`/api/qw/questionnaires/${id}`);
  if (!r.ok) throw new Error('load failed');
  return r.json();
}

export async function addItem(qid: number, p: any) {
  const r = await fetch(`/api/qw/questionnaires/${qid}/items`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(p),
  });
  if (!r.ok) throw new Error('add failed');
  return r.json();
}

export async function patchItem(id: number, p: any) {
  const r = await fetch(`/api/qw/items/${id}`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(p),
  });
  if (!r.ok) throw new Error('patch failed');
  return r.json();
}

export async function rebuild(qid: number) {
  await fetch(`/api/qw/rebuild-outline/${qid}`, { method: 'POST' });
}

// --- Fields -----------------------------------------------------------------

export async function listFields(itemId: number) {
  const r = await fetch(`/api/qw/items/${itemId}/fields`);
  if (!r.ok) throw new Error('list fields failed');
  return r.json();
}

export async function addField(itemId: number, p: any) {
  const r = await fetch(`/api/qw/items/${itemId}/fields`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(p),
  });
  if (!r.ok) throw new Error('add field failed');
  return r.json();
}

export async function deleteField(fieldId: number) {
  const r = await fetch(`/api/qw/fields/${fieldId}`, { method: 'DELETE' });
  if (!r.ok) throw new Error('delete field failed');
  return r.json();
}

/** NEW: update a field (partial) */
export async function patchField(fieldId: number, p: any) {
  const r = await fetch(`/api/qw/fields/${fieldId}`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(p),
  });
  if (!r.ok) throw new Error('patch field failed');
  return r.json();
}
