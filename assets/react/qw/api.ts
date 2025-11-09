// assets/react/qw/api.ts

// --- Questionnaires / Items -------------------------------------------------
export async function getQuestionnaire(id: number) {
  const r = await fetch(`/api/qw/questionnaires/${id}`);
  if (!r.ok) throw new Error('load failed');
  return r.json();
}

export async function patchQuestionnaire(id: number, payload: any) {
  const r = await fetch(`/api/qw/questionnaires/${id}`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });
  if (!r.ok) throw new Error('patch questionnaire failed');
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

// --- CIs -------------------------------------------------------------------

export async function listCis(tenantId?: number, query?: string) {
  const params = new URLSearchParams();
  if (tenantId != null) params.set('tenant_id', String(tenantId));
  if (query) params.set('q', query);
  const suffix = params.toString() ? `?${params.toString()}` : '';
  const r = await fetch(`/api/qw/cis${suffix}`);
  if (!r.ok) throw new Error('list CIs failed');
  return r.json();
}

export async function listQuestionnaires(params?: { tenantId?: number; ciId?: number; query?: string }) {
  const search = new URLSearchParams();
  if (params?.tenantId != null) search.set('tenant_id', String(params.tenantId));
  if (params?.ciId != null) search.set('ci_id', String(params.ciId));
  if (params?.query) search.set('q', params.query);
  const suffix = search.toString() ? `?${search.toString()}` : '';
  const r = await fetch(`/api/qw/questionnaires${suffix}`);
  if (!r.ok) throw new Error('list questionnaires failed');
  return r.json();
}

export async function listResponses(questionnaireId: number) {
  const r = await fetch(`/api/qw/questionnaires/${questionnaireId}/responses`);
  if (!r.ok) throw new Error('list responses failed');
  return r.json();
}

export async function createResponse(questionnaireId: number, payload?: { cloneFrom?: number | null }) {
  const r = await fetch(`/api/qw/questionnaires/${questionnaireId}/responses`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload ?? {}),
  });
  if (!r.ok) throw new Error('create response failed');
  return r.json();
}
