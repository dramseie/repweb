import React, { useState } from 'react';
import { useEndpointChecks } from '../hooks/useEndpointChecks';

export default function EndpointHealthPanel() {
  const [run, setRun] = useState(false);
  const { results, stats, running } = useEndpointChecks({ endpointsUrl: '/endpoints.json', run });

  return (
    <div className="card shadow-sm mb-3">
      <div className="card-body">
        <div className="d-flex align-items-center justify-content-between">
          <h5 className="card-title mb-0">Endpoint Health</h5>
          <div>
            <button className="btn btn-sm btn-outline-primary me-2" disabled={running} onClick={() => setRun(true)}>
              {running ? 'Running…' : 'Run tests'}
            </button>
            <span className="badge bg-success me-1">OK {stats.pass}</span>
            <span className="badge bg-danger me-1">Fail {stats.fail}</span>
            <span className="badge bg-secondary">Total {stats.total} ({stats.pct}%)</span>
          </div>
        </div>

        <div className="table-responsive mt-3">
          <table className="table table-sm align-middle">
            <thead><tr><th>Name</th><th>Status</th><th>Pass</th><th>Latency</th><th>URL</th></tr></thead>
            <tbody>
              {(results || []).map(r => (
                <tr key={r.name} className={r.pass ? '' : 'table-danger'}>
                  <td>{r.name}</td>
                  <td>{r.status || '—'}</td>
                  <td>{r.pass ? '✅' : '❌'}</td>
                  <td>{typeof r.latencyMs === 'number' ? `${r.latencyMs} ms` : '—'}</td>
                  <td><code>{r.url}</code></td>
                </tr>
              ))}
              {(!results || results.length === 0) && (
                <tr><td colSpan={5} className="text-muted">Click “Run tests” to start.</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
