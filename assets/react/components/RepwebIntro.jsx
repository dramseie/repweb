// assets/react/components/RepwebIntro.jsx
import React, { useEffect, useState } from "react";

/**
 * Repweb Intro — modal & full-page mini-presentation
 * - Includes favicon logo in header
 * - Uses “cost-efficient” wording
 * - Ends with a “Who am I” slide (Solution Builder)
 */

const slides = [
  {
    title: "Repweb — Where data comes together",
    kicker: "Swiss-crafted ecosystem for service reporting",
    points: [
      "Contract-aware reporting for as-a-service models",
      "Financials + KPI/SLA + Quality in one place",
      "Ingests APIs, CSV/Excel, logs, and monitoring data",
    ],
  },
  {
    title: "The problem it solves",
    points: [
      "Big tools each see only their domain (ServiceNow, OpsRamp, CheckMK, NetBox, Sentinel…)",
      "Contracts are complex: clauses, exceptions, penalties, earn-backs",
      "Reporting is a cost center → must be lean, automated, beautiful",
    ],
  },
  {
    title: "How Repweb works",
    points: [
      "Apache NiFi pipelines collect & normalize (APIs + CSV/Excel + DB)",
      "MariaDB + EAV model for flexible multi-tenant schemas",
      "Symfony backend + React front; secure APIs with RBAC/JWT",
      "Grafana embeds; CheckMK/Influx integrations",
    ],
  },
  {
    title: "Key capabilities",
    points: [
      "Financial reporting by tenant/service/customer",
      "KPI/SLA compliance with contract logic",
      "Pivot tables, exports, and customer-ready PDFs",
      "RBAC, JWT SSO, multi-tenant isolation",
    ],
  },
  {
    title: "Architecture (high-level)",
    custom: (
      <svg viewBox="0 0 800 340" className="rw-arch-svg" aria-label="Architecture diagram">
        <defs>
          <filter id="rwShadow" x="-20%" y="-20%" width="140%" height="140%">
            <feDropShadow dx="0" dy="2" stdDeviation="4" floodOpacity=".2" />
          </filter>
          <marker id="rwArrow" markerWidth="10" markerHeight="10" refX="8" refY="3" orient="auto" markerUnits="strokeWidth">
            <path d="M0,0 L10,3 L0,6 Z" />
          </marker>
        </defs>

        {/* Sources */}
        <g>
          {["ServiceNow", "OpsRamp", "CheckMK", "NetBox", "OneView", "Sentinel", "CSV/Excel"].map((label, i) => (
            <g key={label} transform={`translate(${40 + i * 105}, 30)`}>
              <rect width="95" height="46" rx="10" filter="url(#rwShadow)" className="rw-box" />
              <text x="47.5" y="28" textAnchor="middle" className="rw-txt-xs">
                {label}
              </text>
            </g>
          ))}
        </g>

        {/* NiFi */}
        <g transform="translate(100, 120)">
          <rect width="600" height="52" rx="12" className="rw-accent" filter="url(#rwShadow)" />
          <text x="300" y="32" textAnchor="middle" className="rw-txt">
            Apache NiFi — ingestion • mapping • quality
          </text>
        </g>

        {/* DB + Backend + UI */}
        <g transform="translate(60, 200)">
          <rect width="300" height="90" rx="14" className="rw-box" filter="url(#rwShadow)" />
          <text x="150" y="36" textAnchor="middle" className="rw-txt">
            MariaDB (EAV, tenants)
          </text>
          <text x="150" y="64" textAnchor="middle" className="rw-txt-xs">
            Contracts • KPIs • Finance
          </text>
        </g>
        <g transform="translate(380, 200)">
          <rect width="160" height="90" rx="14" className="rw-box" filter="url(#rwShadow)" />
          <text x="80" y="36" textAnchor="middle" className="rw-txt">
            Symfony API
          </text>
          <text x="80" y="64" textAnchor="middle" className="rw-txt-xs">
            RBAC • JWT
          </text>
        </g>
        <g transform="translate(560, 200)">
          <rect width="180" height="90" rx="14" className="rw-box" filter="url(#rwShadow)" />
          <text x="90" y="36" textAnchor="middle" className="rw-txt">
            React UI
          </text>
          <text x="90" y="64" textAnchor="middle" className="rw-txt-xs">
            Grafana embeds • Pivot
          </text>
        </g>

        {/* Flows */}
        <g className="rw-arrows">
          <path d="M 90 80 L 90 120" markerEnd="url(#rwArrow)" />
          <path d="M 195 80 L 195 120" markerEnd="url(#rwArrow)" />
          <path d="M 300 80 L 300 120" markerEnd="url(#rwArrow)" />
          <path d="M 405 80 L 405 120" markerEnd="url(#rwArrow)" />
          <path d="M 510 80 L 510 120" markerEnd="url(#rwArrow)" />
          <path d="M 615 80 L 615 120" markerEnd="url(#rwArrow)" />
          <path d="M 720 80 L 720 120" markerEnd="url(#rwArrow)" />
          <path d="M 400 172 L 210 200" markerEnd="url(#rwArrow)" />
          <path d="M 460 172 L 460 200" markerEnd="url(#rwArrow)" />
          <path d="M 520 172 L 650 200" markerEnd="url(#rwArrow)" />
        </g>
      </svg>
    ),
  },
  {
    title: "Results",
    points: [
      "Faster, cost-efficient reporting that still looks premium",
      "One place for truth: financials, KPIs, quality — per tenant",
      "Extensible by design — bring any source, even Excel",
    ],
  },
  // ——— Final slide — Who am I ————————————————————————————————
  {
    title: "Who am I",
    kicker: "The mind behind Repweb",
    custom: (
      <div className="rw-about">
        <p><strong>David Ramseier</strong> — creator of Repweb.</p>
        <p>Swiss IT architect & full-stack craftsman: precision and elegance belong together.</p>
        <p>I connect data, design, and logic — from pipelines (NiFi / MariaDB) to portals (Symfony / React / Grafana).</p>
        <p className="rw-identity">
          I’m not a Developer.<br/>
          I’m not a Project Manager.<br/>
          I’m not a Solution Architect.<br/>
          <strong>I am a Solution Builder.</strong>
        </p>
      </div>
    ),
  },
];

function useKey(handler) {
  useEffect(() => {
    const onKey = (e) => {
      if (["ArrowRight", "ArrowLeft", "Escape"].includes(e.key)) handler(e.key);
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [handler]);
}

export default function RepwebIntro({ mode = "modal", onClose }) {
  const [i, setI] = useState(0);
  const isLast = i === slides.length - 1;
  const slide = slides[i];

  useKey((key) => {
    if (key === "ArrowRight") setI((v) => Math.min(v + 1, slides.length - 1));
    if (key === "ArrowLeft") setI((v) => Math.max(v - 1, 0));
    if (key === "Escape" && mode === "modal" && onClose) onClose();
  });

  return (
    <div className={mode === "modal" ? "rw-modal" : "rw-page"}>
      {mode === "modal" && <div className="rw-backdrop" onClick={onClose} />}
      <div className="rw-card" role="dialog" aria-modal={mode === "modal"}>
        <header className="rw-head">
          <div className="rw-brand">
            {/* Favicon/logo next to “repweb” */}
            <img
              src="/images/favicon.png"
              alt="Repweb logo"
              className="rw-logo-img"
              width="32"
              height="32"
            />
            <div>
              <div className="rw-title">repweb</div>
              <div className="rw-tag">Where data comes together</div>
            </div>
          </div>
          <div className="rw-actions">
            <a className="rw-link" href="/what-is-repweb">Open full page</a>
            {mode === "modal" && (
              <button className="rw-close" onClick={onClose} aria-label="Close">×</button>
            )}
          </div>
        </header>

        <main className="rw-slide">
          <h2 className="rw-h2">{slide.title}</h2>
          {slide.kicker && <p className="rw-kicker">{slide.kicker}</p>}
          {slide.custom ? (
            <div className="rw-custom">{slide.custom}</div>
          ) : (
            <ul className="rw-points">
              {slide.points?.map((p) => <li key={p}>{p}</li>)}
            </ul>
          )}
        </main>

        <footer className="rw-foot">
          <div className="rw-progress">
            {slides.map((_, idx) => (
              <span key={idx} className={idx <= i ? "dot on" : "dot"} />
            ))}
          </div>
          <div className="rw-nav">
            <button
              className="rw-btn"
              onClick={() => setI((v) => Math.max(v - 1, 0))}
              disabled={i === 0}
            >
              Back
            </button>
            {!isLast ? (
              <button
                className="rw-btn-primary"
                onClick={() => setI((v) => Math.min(v + 1, slides.length - 1))}
              >
                Next
              </button>
            ) : (
              <a className="rw-btn-primary" href="/login">Let’s get started</a>
            )}
          </div>
        </footer>
      </div>
    </div>
  );
}
