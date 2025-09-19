// assets/react/components/RestApiExplorer.jsx
import React, { useEffect, useMemo, useState } from 'react';
import ReactJsonView from '@uiw/react-json-view';
import { JSONPath } from 'jsonpath-plus';

/** Tiny helpers */
const kvToObj = (text) => {
  const out = {};
  (text || '').split('\n').forEach(line => {
    const m = line.match(/^\s*([^:]+)\s*:\s*(.+)\s*$/);
    if (m) out[m[1].trim()] = m[2].trim();
  });
  return out;
};

const QueryEditor = ({ query, setQuery }) => {
  const [k, setK] = useState('');
  const [v, setV] = useState('');
  return (
    <div className="card p-3 mb-3">
      <div className="d-flex gap-2">
        <input className="form-control" placeholder="key"
               value={k} onChange={e => setK(e.target.value)} />
        <input className="form-control" placeholder="value"
               value={v} onChange={e => setV(e.target.value)} />
        <button className="btn btn-secondary"
                onClick={() => { if (k) { setQuery({ ...query, [k]: v }); setK(''); setV(''); }}}>
          Add
        </button>
      </div>
      {Object.keys(query).length > 0 && (
        <div className="mt-2 small">
          {Object.entries(query).map(([kk, vv]) => (
            <span key={kk} className="badge text-bg-light me-2">
              {kk}={String(vv)}{' '}
              <a role="button" className="ms-1"
                 onClick={() => { const c={...query}; delete c[kk]; setQuery(c); }}>×</a>
            </span>
          ))}
        </div>
      )}
    </div>
  );
};

export default function RestApiExplorer() {
  const mount = document.getElementById('rest-explorer-root');
  const connectors = useMemo(() => JSON.parse(mount?.dataset?.connectors || '[]'), []);
  const endpoints  = useMemo(() => JSON.parse(mount?.dataset?.endpoints  || '[]'), []);

  const [connectorId, setConnectorId] = useState(connectors[0]?.id || '');
  const [path, setPath] = useState(endpoints[0]?.path || '/');
  const [method, setMethod] = useState(endpoints[0]?.method || 'GET');
  const [query, setQuery] = useState(endpoints[0]?.sampleQuery || {});
  const [headersText, setHeadersText] = useState('');
  const [body, setBody] = useState(endpoints[0]?.sampleBody || '');
  const [loading, setLoading] = useState(false);
  const [resp, setResp] = useState(null);

  const [jsonPath, setJsonPath] = useState('$.');
  const [jsonPathResult, setJsonPathResult] = useState(null);

  const doCall = async () => {
    setLoading(true);
    setResp(null);
    try {
      let bodyPayload = null;
      if (body && ['POST','PUT','PATCH','DELETE'].includes(method)) {
        try { bodyPayload = JSON.parse(body); } catch { bodyPayload = body; }
      }
      const r = await fetch('/api/resttool/fetch', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          connectorId: Number(connectorId),
          path,
          method,
          query,
          headers: kvToObj(headersText),
          body: bodyPayload,
        }),
      });
      const js = await r.json();
      setResp(js);
    } catch (e) {
      setResp({ error: String(e) });
    } finally {
      setLoading(false);
    }
  };

  const evaluateJsonPath = () => {
    try {
      if (!resp?.json) { setJsonPathResult('No JSON payload'); return; }
      const res = JSONPath({ path: jsonPath, json: resp.json });
      setJsonPathResult(res);
    } catch (e) {
      setJsonPathResult(String(e));
    }
  };

  useEffect(() => {
    if (resp?.json && jsonPath && jsonPath !== '$.') evaluateJsonPath();
  }, [resp]); // eslint-disable-line react-hooks/exhaustive-deps

  return (
    <div className="container my-3">
      <h2 className="mb-3">REST API Explorer</h2>

      <div className="row g-3">
        <div className="col-lg-4">
          <div className="card p-3">
            <label className="form-label">Connector</label>
            <select className="form-select mb-2" value={connectorId}
                    onChange={e => setConnectorId(e.target.value)}>
              {connectors.map(c => (
                <option key={c.id} value={c.id}>{c.name} — {c.baseUrl}</option>
              ))}
            </select>

            <div className="d-flex gap-2">
              <select className="form-select" style={{maxWidth:140}}
                      value={method} onChange={e => setMethod(e.target.value)}>
                {['GET','POST','PUT','PATCH','DELETE'].map(m => <option key={m}>{m}</option>)}
              </select>
              <input className="form-control" placeholder="/path"
                     value={path} onChange={e => setPath(e.target.value)} />
            </div>

            <div className="mt-3">
              <label className="form-label">Query parameters</label>
              <QueryEditor query={query} setQuery={setQuery} />
            </div>

            <div className="mt-2">
              <label className="form-label">Extra Headers (one per line: <code>Key: Value</code>)</label>
              <textarea className="form-control" rows={4}
                        placeholder={"Authorization: Bearer xxx\nAccept: application/json"}
                        value={headersText} onChange={e => setHeadersText(e.target.value)} />
            </div>

            {['POST','PUT','PATCH','DELETE'].includes(method) && (
              <div className="mt-2">
                <label className="form-label">Body (JSON or raw)</label>
                <textarea className="form-control" rows={6}
                          placeholder='{"name":"value"}'
                          value={body} onChange={e => setBody(e.target.value)} />
              </div>
            )}

            <div className="mt-3 d-flex gap-2">
              <button className="btn btn-primary" onClick={doCall} disabled={loading}>
                {loading ? 'Calling…' : 'Call'}
              </button>
              <button className="btn btn-outline-secondary" onClick={() => setResp(null)} disabled={loading}>
                Clear
              </button>
            </div>
          </div>
        </div>

        <div className="col-lg-8">
          <div className="card p-3 mb-3">
            <div className="d-flex justify-content-between align-items-center">
              <h5 className="mb-0">Response</h5>
              {resp?.status && <span className="badge text-bg-light">HTTP {resp.status}</span>}
            </div>
            <div className="mt-2">
              {resp?.error && <div className="alert alert-danger">{resp.error} — {resp.message || ''}</div>}

              {resp?.json ? (
                <ReactJsonView value={resp.json} />
              ) : resp?.text ? (
                <pre className="bg-light p-2 overflow-auto" style={{maxHeight: '60vh'}}>{resp.text}</pre>
              ) : (
                <div className="text-muted">No response yet.</div>
              )}
            </div>
          </div>

          <div className="card p-3">
            <h5>JSONPath Explorer</h5>
            <div className="input-group mb-2">
              <span className="input-group-text">$</span>
              <input className="form-control" placeholder="e.g. $.data.items[*].id"
                     value={jsonPath} onChange={e => setJsonPath(e.target.value)} />
              <button className="btn btn-secondary" onClick={evaluateJsonPath}>Run</button>
            </div>
            <div>
              {jsonPathResult !== null && (
                <ReactJsonView value={jsonPathResult} />
              )}
              <div className="small text-muted mt-2">
                Tip: Double-click values in the tree to copy. Save useful paths as endpoint bookmarks later.
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  );
}
