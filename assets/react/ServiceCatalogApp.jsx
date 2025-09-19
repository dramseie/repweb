import React, { useEffect, useState } from "react";

/** Small helpers */
const fmtMoney = (n, ccy) => `${Number(n).toFixed(2)} ${ccy}`;
const Input = ({ label, ...p }) => (
  <label className="d-block mb-2">
    <span className="form-label small d-block mb-1">{label}</span>
    <input className="form-control" {...p} />
  </label>
);
const Select = ({ label, children, ...p }) => (
  <label className="d-block mb-2">
    <span className="form-label small d-block mb-1">{label}</span>
    <select className="form-select" {...p}>{children}</select>
  </label>
);
const Toggle = ({ label, ...p }) => (
  <div className="form-check mt-3">
    <input className="form-check-input" type="checkbox" id={p.name} {...p} />
    <label className="form-check-label" htmlFor={p.name}>{label}</label>
  </div>
);

/** Draw rule JSON in a friendly way */
function RuleViewer({ tenant, ci }) {
  const [rule, setRule] = useState(null);
  const [open, setOpen] = useState(false);
  useEffect(() => { setRule(null); setOpen(false); }, [ci]); // reset when rule changes

  const load = async () => {
    setOpen(true);
    if (!ci) return;
    const r = await fetch(`/api/catalog/rule/${encodeURIComponent(ci)}?tenant=${tenant}`);
    if (r.ok) setRule(await r.json());
  };

  if (!ci) return null;
  return (
    <div className="mb-2">
      <button className="btn btn-sm btn-outline-secondary" onClick={load}>
        {open ? "Refresh" : "Load"} rule {ci}
      </button>
      {open && rule && (
        <pre className="bg-light rounded p-2 mt-2 small mb-0">{JSON.stringify(rule, null, 2)}</pre>
      )}
    </div>
  );
}

/** Main app */
export default function ServiceCatalogApp({ defaultTenant = "cmdb" }) {
  const [tenant, setTenant] = useState(defaultTenant);
  const [services, setServices] = useState([]);
  const [serviceCode, setServiceCode] = useState("");
  const [svc, setSvc] = useState(null);

  // NEW: countries + auto currency
  const [countries, setCountries] = useState([]);
  const [billCcyDirty, setBillCcyDirty] = useState(false);

  // NEW: auto-detected levels from level_rule
  const [levels, setLevels] = useState(["Bronze", "Silver", "Gold"]);

  const [form, setForm] = useState({
    tenant_code: defaultTenant,
    service_code: "",
    country: "FR",
    level: "Bronze",
    usage: 50,
    years: 1,
    bill_ccy: "",
    vat_included: false,
    as_of: new Date().toISOString().slice(0, 10),
    customer_ref: "",
    who: "web-ui"
  });

  const [quote, setQuote] = useState(null);
  const [loading, setLoading] = useState(false);
  const [err, setErr] = useState(null);

  /** Load services for tenant */
  useEffect(() => {
    (async () => {
      try {
        const r = await fetch(`/api/catalog/services?tenant=${tenant}`);
        const j = await r.json();
        const list = j.services ?? [];
        setServices(list);
        if (!serviceCode && list.length) setServiceCode(list[0].code);
      } catch (_) {}
    })();
  }, [tenant]);

  /** NEW: Load countries from loc tenant */
  useEffect(() => {
    (async () => {
      try {
        const r = await fetch(`/api/catalog/countries?tenant=loc`);
        const j = await r.json();
        setCountries(j.countries ?? []);
      } catch (_) {}
    })();
  }, []);

  /** Load service details when service changes + auto-detect levels */
  useEffect(() => {
    (async () => {
      setSvc(null); setQuote(null);
      if (!serviceCode) return;
      const r = await fetch(`/api/catalog/service/${encodeURIComponent(serviceCode)}?tenant=${tenant}`);
      if (!r.ok) return;
      const j = await r.json();
      setSvc(j);
      setForm(f => ({
        ...f,
        tenant_code: tenant,
        service_code: serviceCode,
        bill_ccy: f.bill_ccy || j.base_ccy || f.bill_ccy
      }));

      // Auto-detect levels from the level_rule payload
      if (j.level_rule) {
        try {
          const rr = await fetch(`/api/catalog/rule/${encodeURIComponent(j.level_rule)}?tenant=${tenant}`);
          if (rr.ok) {
            const rule = await rr.json();
            if (rule.kind === "level-multiplier" && Array.isArray(rule.payload)) {
              const lvls = rule.payload
                .map(x => (typeof x.level === "string" ? x.level : null))
                .filter(Boolean);
              if (lvls.length) {
                setLevels(lvls);
                setForm(f => ({ ...f, level: lvls.includes(f.level) ? f.level : lvls[0] }));
              }
            }
          }
        } catch (_) {}
      } else {
        setLevels(["Bronze", "Silver", "Gold"]);
      }
    })();
  }, [serviceCode, tenant]);

  /** NEW: when country changes, default bill_ccy from country currency unless user overrode */
  useEffect(() => {
    const c = countries.find(x => x.iso2 === form.country);
    if (c && !billCcyDirty) {
      setForm(f => ({ ...f, bill_ccy: c.currency || f.bill_ccy }));
    }
  }, [form.country, countries, billCcyDirty]);

  const onChange = (e) => {
    const { name, value, type, checked } = e.target;
    if (name === "bill_ccy") setBillCcyDirty(true);
    setForm(f => ({ ...f, [name]: type === "number" ? Number(value) : (type === "checkbox" ? checked : value) }));
  };

  const runQuote = async () => {
    setLoading(true); setErr(null); setQuote(null);
    try {
      const res = await fetch("/api/catalog/quote", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(form)
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const json = await res.json();
      setQuote(json);
    } catch (e) {
      setErr(String(e.message || e));
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="container my-3">
      <div className="d-flex align-items-end gap-3 mb-3">
        <Input label="Tenant" name="tenant" value={tenant} onChange={(e)=>setTenant(e.target.value)} />
        <div className="flex-grow-1">
          <Select label="Service" name="service_code" value={serviceCode} onChange={(e)=>setServiceCode(e.target.value)}>
            {services.map(s => <option key={s.code} value={s.code}>{s.code} — {s.name}</option>)}
          </Select>
        </div>
      </div>

      {svc && (
        <div className="card mb-3">
          <div className="card-body">
            <h5 className="card-title mb-2">{svc.name} <small className="text-muted">({svc.service_ci})</small></h5>
            <div className="row g-3">
              <div className="col-md-2"><div className="small text-muted">Base CCY</div><div className="fw-semibold">{svc.base_ccy || "-"}</div></div>
              <div className="col-md-2"><div className="small text-muted">Base Amount</div><div className="fw-semibold">{svc.base_amt ?? "-"}</div></div>
              <div className="col-md-2"><div className="small text-muted">Unit</div><div className="fw-semibold">{svc.unit || "-"}</div></div>
              <div className="col-md-2"><div className="small text-muted">VAT Category</div><div className="fw-semibold">{svc.vat_category || "-"}</div></div>
            </div>
            <hr />
            <div className="row g-2">
              <div className="col-md-3"><div className="small text-muted">Level Rule</div><code>{svc.level_rule || "-"}</code><RuleViewer tenant={tenant} ci={svc.level_rule} /></div>
              <div className="col-md-3"><div className="small text-muted">Country Rule</div><code>{svc.country_rule || "-"}</code><RuleViewer tenant={tenant} ci={svc.country_rule} /></div>
              <div className="col-md-3"><div className="small text-muted">Term Rule</div><code>{svc.term_rule || "-"}</code><RuleViewer tenant={tenant} ci={svc.term_rule} /></div>
              <div className="col-md-3"><div className="small text-muted">Usage Rule</div><code>{svc.usage_rule || "-"}</code><RuleViewer tenant={tenant} ci={svc.usage_rule} /></div>
            </div>
          </div>
        </div>
      )}

      {/* Quote form */}
      <div className="card">
        <div className="card-body">
          <h6 className="card-title">Get a quote</h6>
          <div className="row g-3">
            <div className="col-md-3">
              {/* NEW: Country dropdown */}
              <Select label="Country (ISO2)" name="country" value={form.country} onChange={onChange}>
                {countries.map(c => (
                  <option key={c.iso2} value={c.iso2}>
                    {c.iso2} — {c.name} ({c.currency})
                  </option>
                ))}
              </Select>
            </div>
            <div className="col-md-3">
              {/* NEW: Levels from rule */}
              <Select label="Level" name="level" value={form.level} onChange={onChange}>
                {levels.map(l => <option key={l}>{l}</option>)}
              </Select>
            </div>
            <div className="col-md-2">
              <Input label={`Usage (${svc?.unit || 'units'})`} type="number" step="0.01" name="usage" value={form.usage} onChange={onChange} />
            </div>
            <div className="col-md-2">
              <Input label="Years" type="number" min="1" name="years" value={form.years} onChange={onChange} />
            </div>
            <div className="col-md-2">
              <Input label="Bill Currency" name="bill_ccy" value={form.bill_ccy} onChange={onChange} placeholder={svc?.base_ccy || "EUR"} />
            </div>
            <div className="col-md-2">
              <Input label="As Of" type="date" name="as_of" value={form.as_of} onChange={onChange} />
            </div>
            <div className="col-md-3">
              <Input label="Customer Ref" name="customer_ref" value={form.customer_ref} onChange={onChange} />
            </div>
            <div className="col-md-3">
              <Input label="Who" name="who" value={form.who} onChange={onChange} />
            </div>
            <div className="col-md-3">
              <Toggle label="Prices include VAT" name="vat_included" checked={form.vat_included} onChange={onChange} />
            </div>
            <div className="col-md-3 d-flex align-items-end">
              <button className="btn btn-primary w-100" disabled={!serviceCode || loading}
                      onClick={runQuote}>{loading ? "Calculating…" : "Get quote"}</button>
            </div>
          </div>

          {err && <div className="alert alert-danger mt-3">{err}</div>}

          {quote && (
            <div className="mt-4">
              <h6>Totals</h6>
              <div className="row g-2">
                <div className="col-md-4">
                  <div className="border rounded p-2">
                    <div className="small text-muted">Ex-VAT</div>
                    <div className="fw-semibold">{fmtMoney(quote.totals.ex_vat.amount, quote.totals.ex_vat.currency)}</div>
                  </div>
                </div>
                <div className="col-md-4">
                  <div className="border rounded p-2">
                    <div className="small text-muted">VAT</div>
                    <div className="fw-semibold">{fmtMoney(quote.totals.vat.amount, quote.totals.vat.currency)} ({quote.calc.vat_pct}%)</div>
                  </div>
                </div>
                <div className="col-md-4">
                  <div className="border rounded p-2">
                    <div className="small text-muted">Inc-VAT</div>
                    <div className="fw-semibold">{fmtMoney(quote.totals.inc_vat.amount, quote.totals.inc_vat.currency)}</div>
                  </div>
                </div>
              </div>

              <h6 className="mt-3">Breakdown</h6>
              <pre className="bg-light p-3 rounded small">{JSON.stringify(quote, null, 2)}</pre>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
