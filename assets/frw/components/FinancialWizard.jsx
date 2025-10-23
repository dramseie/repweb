import React, { useEffect, useMemo, useState } from 'react';
import Stepper from './Stepper.jsx';
import StepPanel from './StepPanel.jsx';
import api from '../lib/api';

export default function FinancialWizard({ templateCode }) {
  const [tpl, setTpl] = useState(null);
  const [run, setRun] = useState(null);
  const [cursor, setCursor] = useState(0);
  const [loading, setLoading] = useState(true);
  const [err, setErr] = useState(null);

  const runIdFromUrl = useMemo(() => {
    try {
      const params = new URLSearchParams(window.location.search);
      const v = params.get('runId');
      return v ? Number(v) : null;
    } catch {
      return null;
    }
  }, []);

  useEffect(() => {
    let alive = true;
    (async () => {
      setLoading(true);
      setErr(null);
      try {
        const t = await api.getTemplate(templateCode);
        if (!alive) return;
        setTpl(t);

        const r = runIdFromUrl
          ? await api.getRun(runIdFromUrl)
          : await api.createRun(templateCode);
        if (!alive) return;
        setRun(r);
        setCursor(0);
      } catch (e) {
        if (!alive) return;
        setErr(normalizeError(e));
      } finally {
        if (alive) setLoading(false);
      }
    })();
    return () => { alive = false; };
  }, [templateCode, runIdFromUrl]);

  // Robust schema extraction
  const schema = useMemo(() => {
    const s = tpl?.schema ?? null;
    if (s && typeof s === 'object') return s.schema || s.data || s;
    if (s && typeof s === 'string') {
      try {
        const parsed = JSON.parse(s);
        if (parsed && typeof parsed === 'object') return parsed.schema || parsed.data || parsed;
      } catch {}
    }
    if (tpl?.schema_json) {
      try {
        const parsed = JSON.parse(tpl.schema_json);
        if (parsed && typeof parsed === 'object') return parsed.schema || parsed.data || parsed;
      } catch {}
    }
    return null;
  }, [tpl]);

  const steps = useMemo(() => {
    if (!schema) return [];
    if (Array.isArray(schema.steps)) return schema.steps;
    if (Array.isArray(schema.Steps)) return schema.Steps;
    if (Array.isArray(schema[0]?.steps)) return schema[0].steps;
    return [];
  }, [schema]);

  useEffect(() => {
    if (!steps.length) return;
    setCursor(c => Math.min(Math.max(0, c), steps.length - 1));
  }, [steps.length]);

  if (loading) return <div style={{padding:16}}>Loading…</div>;

  if (err) {
    return (
      <div style={{padding:16}}>
        <h2 style={{fontSize:18, fontWeight:600}}>Couldn’t load the wizard</h2>
        <p style={{opacity:.8, fontSize:14}}>{err.message}</p>
        {err.hint && (
          <pre style={{marginTop:8, padding:8, background:'#f3f4f6', borderRadius:6, fontSize:12, whiteSpace:'pre-wrap'}}>
            {err.hint}
          </pre>
        )}
        <button
          style={btnSecondary}
          onClick={() => window.location.reload()}
        >
          Retry
        </button>
      </div>
    );
  }

  if (!tpl) return <div style={{padding:16}}>Template not found: <code>{templateCode}</code></div>;

  if (!steps.length) {
    return (
      <div style={{padding:16}}>
        <h2 style={{fontSize:18, fontWeight:600}}>Template has no steps</h2>
        <p style={{opacity:.8, fontSize:14}}>
          The template <code>{tpl.code || templateCode}</code> was loaded but contains no <code>schema.steps</code>.
        </p>
      </div>
    );
  }

  if (!run) return <div style={{padding:16}}>Creating a new run…</div>;

  return (
    // Plain CSS grid: left = 260px, right = fluid
    <div style={{
      display:'grid',
      gridTemplateColumns:'260px 1fr',
      gap:'24px',
      padding:'16px',
      alignItems:'start'
    }}>
      <aside style={{width:260}}>
        <Stepper
          steps={steps}
          cursor={cursor}
          onJump={(i) => setCursor(clamp(i, 0, steps.length - 1))}
          run={run}
        />
      </aside>

      <main>
        <StepPanel
          step={steps[cursor]}
          answers={run.answers || {}}
          onChange={async (answers) => {
            try {
              const updated = await api.patchRun(run.id, { answers });
              setRun(updated);
            } catch (e) {
              setErr(normalizeError(e));
            }
          }}
          onPrice={async () => {
            try {
              const priced = await api.priceRun(run.id);
              setRun(priced);
            } catch (e) {
              setErr(normalizeError(e));
            }
          }}
          onPrev={() => setCursor(c => clamp(c - 1, 0, steps.length - 1))}
          onNext={() => setCursor(c => clamp(c + 1, 0, steps.length - 1))}
          onSubmit={async () => {
            try {
              const submitted = await api.submitRun(run.id);
              setRun(submitted);
            } catch (e) {
              setErr(normalizeError(e));
            }
          }}
        />
      </main>
    </div>
  );
}

// simple shared style
const btnSecondary = {
  marginTop: 12,
  padding: '8px 12px',
  background: '#e5e7eb',
  border: '1px solid #d1d5db',
  borderRadius: 6,
};

function clamp(n, min, max) { return Math.max(min, Math.min(max, n)); }

function normalizeError(e) {
  try {
    if (e && typeof e === 'object') {
      if ('status' in e && 'url' in e) {
        return {
          message: `HTTP ${e.status} while calling ${new URL(e.url).pathname}`,
          hint: `Check routes, controller prefixes, and that the template exists in frw_template.\nURL: ${e.url}\nStatus: ${e.status}`
        };
      }
      if (e.message) return { message: e.message };
    }
  } catch {}
  return { message: 'Unexpected error occurred.' };
}
