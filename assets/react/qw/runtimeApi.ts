import type { RuntimePayload } from './types';

export interface SaveRuntimeRequest {
  answers: Array<{ itemId: number; fieldId: number | null; value: unknown }>;
  status: string;
}

function buildUrl(ciKey: string, questionnaireId?: number | null): string {
  const encoded = encodeURIComponent(ciKey);
  const base = `/api/discovery/questionnaires/ci/${encoded}`;
  if (questionnaireId != null) {
    return `${base}?questionnaire_id=${encodeURIComponent(String(questionnaireId))}`;
  }
  return base;
}

export async function loadRuntime(ciKey: string, questionnaireId?: number | null): Promise<RuntimePayload> {
  const response = await fetch(buildUrl(ciKey, questionnaireId));
  if (!response.ok) {
    const message = await response.text();
    throw new Error(message || 'Failed to load questionnaire');
  }
  return response.json();
}

export async function saveRuntime(
  ciKey: string,
  payload: SaveRuntimeRequest,
  questionnaireId?: number | null
): Promise<RuntimePayload> {
  const response = await fetch(buildUrl(ciKey, questionnaireId), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });
  if (!response.ok) {
    const message = await response.text();
    throw new Error(message || 'Failed to save questionnaire');
  }
  return response.json();
}

export async function loadResponseRuntime(responseId: number): Promise<RuntimePayload> {
  const response = await fetch(`/api/qw/responses/${responseId}`);
  if (!response.ok) {
    const message = await response.text();
    throw new Error(message || 'Failed to load response');
  }
  return response.json();
}

export async function saveResponseRuntime(responseId: number, payload: SaveRuntimeRequest): Promise<RuntimePayload> {
  const response = await fetch(`/api/qw/responses/${responseId}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });
  if (!response.ok) {
    const message = await response.text();
    throw new Error(message || 'Failed to save response');
  }
  return response.json();
}
