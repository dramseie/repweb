import React, { useEffect, useMemo, useState } from "react";

/* --------------- helpers (time + query sync) --------------- */

function nowRange(hours = 6) {
  const to = Date.now();
  const from = to - hours * 3600 * 1000;
  return { from, to };
}

function readUrlState() {
  const sp = new URLSearchParams(window.location.search);
  const uid     = sp.get("uid") || "";
  const panelId = sp.get("pid") || "dashboard";
  const hours   = Number(sp.get("h") || 6);
  const height  = Number(sp.get("ht") || 900);
  const kiosk   = sp.get("kiosk") === "1" || sp.has("kiosk");

  // collect var-* params (can repeat) — ignore var-bucket
  const vars = {};
  for (const [k, v] of sp.entries()) {
    if (k.startsWith("var-")) {
      if (k.toLowerCase() === "var-bucket") continue;
      if (!vars[k]) vars[k] = [];
      vars[k].push(v);
    }
  }

  return {
    uid,
    panelId,
    hours: isFinite(hours) ? hours : 6,
    height: isFinite(height) ? height : 900,
    kiosk,
    vars,
  };
}

function writeUrlState({ uid, panelId, hours, height, kiosk, vars }) {
  const sp = new URLSearchParams();
  if (uid) sp.set("uid", uid);
  if (panelId && panelId !== "dashboard") sp.set("pid", String(panelId));
  if (hours) sp.set("h", String(hours));
  if (height) sp.set("ht", String(height));
  if (kiosk) sp.set("kiosk", "1");

  // vars (skip var-bucket)
  if (vars && typeof vars === "object") {
    for (const key of Object.keys(vars)) {
      if (key.toLowerCase() === "var-bucket") continue;
      const arr = Array.isArray(vars[key]) ? vars[key] : [vars[key]];
      for (const v of arr) sp.append(key, String(v));
    }
  }

  const qs = sp.toString();
  const next = `${window.location.pathname}${qs ? "?" + qs : ""}${window.location.hash || ""}`;
  window.history.replaceState({}, "", next);
}

/* --------------- main component --------------- */

export default function GrafanaBrowser({ orgId = 1, grafanaBase }) {
  const boot = readUrlState();

  const [dashList, setDashList] = useState([]);
  const [uid, setUid] = useState(boot.uid || "");
  const [slug, setSlug] = useState("");

  const [panels, setPanels] = useState([]);
  const [panelId, setPanelId] = useState(boot.panelId || "dashboard");

  const [height, setHeight] = useState(boot.height || 900);
  const [hours, setHours] = useState(boot.hours || 6);
  const [{ from, to }, setRange] = useState(nowRange(boot.hours || 6));

  const [kiosk, setKiosk] = useState(boot.kiosk || false);

  // Dashboard variables (bucket excluded)
  const [varsMeta, setVarsMeta] = useState([]);
  // URL vars map (keys: 'var-<name>' -> string[])
  const [vars, setVars] = useState(boot.vars || {});
  // Custom options typed by user when Grafana JSON has no options
  const [customOpts, setCustomOpts] = useState({}); // { [varName]: string[] }
  const [pendingAdd, setPendingAdd] = useState({}); // { [varName]: string } input text

  const [error, setError] = useState("");

  /* ---------- dashboards ---------- */
  useEffect(() => {
    (async () => {
      setError("");
      try {
        const r = await fetch(`/api/grafana/dashboards`);
        const data = await r.json();
        if (!r.ok) throw new Error(data?.error || `HTTP ${r.status}`);
        const list = Array.isArray(data) ? data : (data.dashboards || []);
        setDashList(list);
        const still = list.find(d => d.uid === uid);
        if (!still) {
          setUid(list[0]?.uid || "");
          setPanels([]);
          setSlug("");
          setPanelId("dashboard");
          setVarsMeta([]);
          setCustomOpts({});
          setPendingAdd({});
        }
      } catch (e) {
        setError(`Dashboards error: ${e.message || e}`);
        setDashList([]);
      }
    })();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  /* ---------- panels + slug ---------- */
  useEffect(() => {
    if (!uid) return;
    (async () => {
      setError("");
      try {
        const r = await fetch(`/api/grafana/dashboards/${encodeURIComponent(uid)}/panels`);
        const d = await r.json();
        if (!r.ok) throw new Error(d?.error || `HTTP ${r.status}`);
        setPanels(d.panels || []);
        setSlug(d.slug || "");
        if (panelId !== "dashboard" && !d.panels?.find(p => String(p.id) === String(panelId))) {
          setPanelId("dashboard");
        }
      } catch (e) {
        setError(`Panels error: ${e.message || e}`);
        setPanels([]);
        setSlug("");
      }
    })();
  }, [uid]); // eslint-disable-line react-hooks/exhaustive-deps

  /* ---------- variables (exclude "bucket") ---------- */
  useEffect(() => {
    if (!uid) return;
    (async () => {
      try {
        const r = await fetch(`/api/grafana/dashboards/${encodeURIComponent(uid)}/variables`);
        const d = await r.json();
        if (!r.ok) throw new Error(d?.error || `HTTP ${r.status}`);
        const metaAll = d.variables || [];
        const meta = metaAll.filter(v => String(v?.name || "").toLowerCase() !== "bucket");
        setVarsMeta(meta);

        // Initialize vars and reset custom options
        setCustomOpts({});
        setPendingAdd({});
        setVars(prev => {
          const next = { ...prev };
          Object.keys(next).forEach(k => {
            if (k.toLowerCase() === "var-bucket") delete next[k];
          });
          for (const v of meta) {
            const key = `var-${v.name}`;
            const haveUrl = Array.isArray(prev[key]) && prev[key].length > 0;
            if (haveUrl) continue;

            const cur = v?.current?.value;
            if (cur == null) {
              next[key] = [];
            } else if (Array.isArray(cur)) {
              next[key] = cur.map(x => String(x));
            } else {
              const curText = v?.current?.text;
              if (v.includeAll && (cur === "$__all" || String(curText).toLowerCase() === "all")) {
                next[key] = ["All"];
              } else {
                next[key] = [String(cur)];
              }
            }
          }
          return next;
        });
      } catch (e) {
        console.warn("variables:", e);
        setVarsMeta([]);
      }
    })();
  }, [uid]);

  /* ---------- hours -> time range ---------- */
  useEffect(() => {
    setRange(nowRange(hours));
  }, [hours]);

  /* ---------- keep URL in sync ---------- */
  useEffect(() => {
    writeUrlState({ uid, panelId, hours, height, kiosk, vars });
  }, [uid, panelId, hours, height, kiosk, vars]);

  /* ---------- var helpers ---------- */

  function setVarValues(name, valuesArray) {
    const key = `var-${name}`;
    if (name.toLowerCase() === "bucket") return; // guard
    const clean = Array.from(new Set(valuesArray.map(v => String(v)).filter(Boolean)));
    setVars(v => ({ ...v, [key]: clean }));
  }

  function resetVarsToCurrent() {
    const next = { ...vars };
    Object.keys(next).forEach(k => {
      if (k.toLowerCase() === "var-bucket") delete next[k];
    });
    for (const v of varsMeta) {
      const key = `var-${v.name}`;
      const cur = v?.current?.value;
      if (cur == null)       next[key] = [];
      else if (Array.isArray(cur)) next[key] = cur.map(x => String(x));
      else {
        const t = v?.current?.text;
        next[key] = (v.includeAll && (cur === "$__all" || String(t).toLowerCase() === "all")) ? ["All"] : [String(cur)];
      }
    }
    setVars(next);
  }

  function clearVars() {
    const next = { ...vars };
    for (const v of varsMeta) delete next[`var-${v.name}`];
    Object.keys(next).forEach(k => { if (k.toLowerCase() === "var-bucket") delete next[k]; });
    setVars(next);
    setCustomOpts({});
    setPendingAdd({});
  }

  function addCustomValues(varName) {
    const raw = (pendingAdd[varName] || "").trim();
    if (!raw) return;
    const values = raw.split(",").map(s => s.trim()).filter(Boolean);
    if (values.length === 0) return;

    setCustomOpts(prev => {
      const cur = prev[varName] || [];
      const merged = Array.from(new Set([...cur, ...values]));
      return { ...prev, [varName]: merged };
    });
    // also select them
    setVarValues(varName, Array.from(new Set([...(vars[`var-${varName}`] || []), ...values])));
    setPendingAdd(prev => ({ ...prev, [varName]: "" }));
  }

  // iframe src (force light theme)
  const src = useMemo(() => {
    if (!uid || !slug) return "";
    const params = new URLSearchParams({
      orgId: String(orgId),
      from: String(from),
      to: String(to),
      theme: "light",
    });
    if (panelId !== "dashboard") params.set("panelId", String(panelId));
    if (kiosk) params.set("kiosk", "1");
    for (const k of Object.keys(vars)) {
      const arr = Array.isArray(vars[k]) ? vars[k] : [vars[k]];
      for (const v of arr) params.append(k, String(v));
    }
    const base = panelId !== "dashboard"
      ? `${grafanaBase}/d-solo/${uid}/${slug}`
      : `${grafanaBase}/d/${uid}/${slug}`;
    return `${base}?${params.toString()}`;
  }, [uid, slug, panelId, orgId, grafanaBase, from, to, kiosk, vars]);

  return (
    <div className="container-fluid">
      {error && <div className="alert alert-danger mb-3">{error}</div>}

      <div className="row g-2 align-items-end">
        <div className="col-md-6">
          <label className="form-label">Dashboard</label>
          <select className="form-select" value={uid} onChange={(e)=>setUid(e.target.value)}>
            {dashList.map(d => <option key={d.uid} value={d.uid}>{d.title || d.uid}</option>)}
          </select>
        </div>

        <div className="col-md-4">
          <label className="form-label">Panel</label>
          <select className="form-select" value={panelId} onChange={(e)=>setPanelId(e.target.value)}>
            <option value="dashboard">— Full dashboard —</option>
            {panels.map(p => <option key={p.id} value={p.id}>{p.title} (#{p.id})</option>)}
          </select>
        </div>

        <div className="col-md-1">
          <label className="form-label">Hours</label>
          <input
            type="number"
            min="1"
            value={hours}
            className="form-control"
            onChange={(e)=>setHours(Math.max(1, Number(e.target.value||6)))}
          />
        </div>

        <div className="col-md-1">
          <label className="form-label">Height</label>
          <input
            type="number"
            min="200"
            value={height}
            className="form-control"
            onChange={(e)=>setHeight(Math.max(200, Number(e.target.value||900)))}
          />
        </div>
      </div>

      {/* --- Variables: ALWAYS multi-select, with Add-values when options are missing --- */}
      {varsMeta.length > 0 && (
        <div className="card mt-3">
          <div className="card-body">
            <div className="d-flex justify-content-between align-items-center mb-2">
              <strong>Variables</strong>
              <div className="d-flex gap-2">
                <button className="btn btn-sm btn-outline-primary" onClick={resetVarsToCurrent}>
                  Reset to dashboard defaults
                </button>
                <button className="btn btn-sm btn-outline-secondary" onClick={clearVars}>
                  Clear selections
                </button>
              </div>
            </div>

            <div className="row g-3">
              {varsMeta.map(v => {
                const key = `var-${v.name}`;
                const selected = (vars[key] ?? []).map(String);

                // Build list: All (if any) + Grafana options + custom user-added options
                const grafOpts = Array.isArray(v.options) ? v.options : [];
                const base = grafOpts.map(o => {
                  const val = o?.value ?? o?.text ?? "";
                  const txt = o?.text ?? String(val);
                  return { value: String(val), text: String(txt) };
                });
                const user = (customOpts[v.name] || []).map(val => ({ value: val, text: val }));

                const options = [];
                if (v.includeAll) options.push({ value: "All", text: "All" });
                // de-dup while preserving order
                const seen = new Set(options.map(o => o.value));
                for (const o of [...base, ...user]) {
                  if (!seen.has(o.value)) { options.push(o); seen.add(o.value); }
                }

                return (
                  <div className="col-md-4" key={v.name}>
                    <label className="form-label">{v.label || v.name}</label>

                    <select
                      className="form-select"
                      multiple
                      size={Math.min(10, Math.max(4, options.length || 4))}
                      value={selected}
                      onChange={(e) => {
                        const arr = Array.from(e.target.selectedOptions).map(o => o.value);
                        setVarValues(v.name, arr);
                      }}
                    >
                      {options.map(o => (
                        <option key={o.value} value={o.value}>{o.text}</option>
                      ))}
                    </select>

                    {/* Add values when the dashboard didn't persist options, or to add custom entries */}
                    <div className="input-group mt-2">
                      <input
                        type="text"
                        className="form-control"
                        placeholder="Add values (comma-separated)"
                        value={pendingAdd[v.name] || ""}
                        onChange={(e) => setPendingAdd(prev => ({ ...prev, [v.name]: e.target.value }))}
                        onKeyDown={(e) => { if (e.key === "Enter") addCustomValues(v.name); }}
                      />
                      <button className="btn btn-outline-secondary" type="button" onClick={() => addCustomValues(v.name)}>
                        Add
                      </button>
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        </div>
      )}

      <div className="row g-2 mt-2">
        <div className="col-md-3 d-flex align-items-center">
          <div className="form-check">
            <input
              className="form-check-input"
              type="checkbox"
              id="kiosk"
              checked={kiosk}
              onChange={(e)=>setKiosk(e.target.checked)}
            />
            <label className="form-check-label" htmlFor="kiosk">Kiosk mode</label>
          </div>
        </div>
        <div className="col-md-9 d-flex justify-content-end gap-2">
          <a className="btn btn-outline-primary" href={src || "#"} target="_blank" rel="noopener noreferrer">
            Open in new tab
          </a>
          <button
            className="btn btn-outline-secondary"
            onClick={()=>navigator.clipboard?.writeText(src || "")}
          >
            Copy iframe URL
          </button>
        </div>
      </div>

      <div className="mt-3 border rounded">
        {src
          ? <iframe title="Grafana" src={src} width="100%" height={height} frameBorder="0" allow="fullscreen" />
          : <div className="p-4 text-muted">Select a dashboard to begin…</div>
        }
      </div>
    </div>
  );
}
