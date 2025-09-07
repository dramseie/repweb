import { useEffect, useMemo, useState } from 'react';

async function fetchJson(url) {
  const r = await fetch(url, { credentials: 'same-origin', cache: 'no-store' });
  if (!r.ok) throw new Error(`${r.status} ${r.statusText}`);
  return r.json();
}

// Simple matcher: status + contains OR JSON path equality (dot path)
function evalExpectation(resp, bodyText, bodyJson, expect = {}) {
  if (expect.status && expect.status !== resp.status) return false;
  if (expect.contains && !bodyText.includes(expect.contains)) return false;
  if (expect.json && bodyJson) {
    const path = (expect.json.path || '').replace(/^\$\./, '');
    const val = path.split('.').reduce((o, k) => (o ? o[k] : undefined), bodyJson);
    if (Object.prototype.hasOwnProperty.call(expect.json, 'equals')) {
      return val === expect.json.equals;
    }
  }
  return true;
}

export function useEndpointChecks({ endpointsUrl = '/endpoints.json', run = false } = {}) {
  const [catalog, setCatalog] = useState(null);
  const [results, setResults] = useState([]);
  const [running, setRunning] = useState(false);

  useEffect(() => {
    fetchJson(endpointsUrl).then(setCatalog).catch(console.error);
  }, [endpointsUrl]);

  useEffect(() => {
    if (!run || !catalog) return;
    let cancel = false;
    (async () => {
      setRunning(true);
      const out = [];
      for (const ep of (catalog.endpoints || [])) {
        const started = performance.now();
        try {
          const resp = await fetch(ep.url, { method: ep.method || 'GET', credentials: 'same-origin' });
          const ct = resp.headers.get('content-type') || '';
          const text = await resp.text();
          const json = ct.includes('application/json') ? JSON.parse(text || 'null') : null;
          const pass = evalExpectation(resp, text, json, ep.expect || {});
          out.push({ name: ep.name, url: ep.url, status: resp.status, pass, latencyMs: Math.round(performance.now() - started) });
        } catch (e) {
          out.push({ name: ep.name, url: ep.url, status: 0, pass: false, error: String(e), latencyMs: Math.round(performance.now() - started) });
        }
      }
      if (!cancel) setResults(out);
      setRunning(false);
    })();
    return () => { cancel = true; };
  }, [run, catalog]);

  const stats = useMemo(() => {
    const total = results.length;
    const pass = results.filter(r => r.pass).length;
    return { total, pass, fail: total - pass, pct: total ? Math.round((pass / total) * 100) : 0 };
  }, [results]);

  return { catalog, results, stats, running };
}
