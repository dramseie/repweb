export async function apiRequest(url, options = {}) {
  const res = await fetch(url, {
    headers: { 'Content-Type': 'application/json', ...(options.headers || {}) },
    credentials: 'same-origin',
    ...options,
  });
  if (!res.ok) {
    const contentType = res.headers.get('Content-Type') || '';
    let payload = { error: res.statusText, status: res.status };
    if (contentType.includes('application/json')) {
      try { payload = await res.json(); } catch {}
    } else {
      payload.message = await res.text();
    }
    const err = new Error(payload.error || payload.message || res.statusText);
    err.response = payload;
    err.status = res.status;
    throw err;
  }
  if (res.status === 204) return null;
  const ct = res.headers.get('Content-Type') || '';
  if (ct.includes('application/json')) {
    return res.json();
  }
  return res.text();
}

export const apiGet = (url) => apiRequest(url, { method: 'GET' });
export const apiPost = (url, body) => apiRequest(url, { method: 'POST', body: JSON.stringify(body ?? {}) });
export const apiPatch = (url, body) => apiRequest(url, { method: 'PATCH', body: JSON.stringify(body ?? {}) });
export const apiDelete = (url) => apiRequest(url, { method: 'DELETE' });
