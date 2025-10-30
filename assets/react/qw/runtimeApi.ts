import type { RuntimePayload } from './types';

function buildUrl(ciKey: string): string {
  const encoded = encodeURIComponent(ciKey);
  return `/api/discovery/questionnaires/ci/${encoded}`;
}

export async function loadRuntime(ciKey: string): Promise<RuntimePayload> {
  const response = await fetch(buildUrl(ciKey));
  if (!response.ok) {
    const message = await response.text();
    throw new Error(message || 'Failed to load questionnaire');
  }
  return response.json();
}

export async function saveRuntime(
  ciKey: string,
  payload: { answers: Array<{ itemId: number; fieldId: number | null; value: unknown }>; status: string }
): Promise<RuntimePayload> {
  const response = await fetch(buildUrl(ciKey), {
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
