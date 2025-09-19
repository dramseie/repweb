import React, { useMemo } from 'react';

/**
 * Simple Bootstrap modal rendered via conditional classes.
 * Props:
 *  - show: boolean
 *  - defs: array of {type,title,defaults,minW,minH,w,h,description?}
 *  - onClose(): void
 *  - onChoose(def): void
 */
export default function AddWidgetModal({ show, defs = [], onClose, onChoose }) {
  const [query, setQuery] = React.useState('');

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return defs;
    return defs.filter(d =>
      String(d.type).toLowerCase().includes(q) ||
      String(d.title || '').toLowerCase().includes(q)
    );
  }, [defs, query]);

  const typeIcon = (type) => {
    switch (String(type)) {
      case 'plotly':    return <i className="bi bi-graph-up" />;
      case 'datatable': return <i className="bi bi-table" />;
      case 'pivot':     return <i className="bi bi-grid-3x3-gap" />;
      case 'grafana':   return <i className="bi bi-speedometer2" />;
      case 'kpi':       return <i className="bi bi-123" />;
      case 'markdown':  return <i className="bi bi-markdown" />;
      case 'nifi':      return <i className="bi bi-diagram-3" />; // 👈 NiFi widget icon
      default:          return <i className="bi bi-puzzle" />;
    }
  };

  const renderDefaultsLine = (def) => {
    const size = `Default size: ${def.w ?? 4}×${def.h ?? 5} (min ${def.minW ?? 2}×${def.minH ?? 2})`;
    // Show useful defaults if provided (e.g., NiFi refreshSec / title)
    const extras = [];
    if (def?.defaults) {
      if (def.defaults.title) extras.push(`title: ${def.defaults.title}`);
      if (def.defaults.refreshSec != null) extras.push(`refresh: ${def.defaults.refreshSec}s`);
    }
    return extras.length ? `${size} — ${extras.join(' · ')}` : size;
  };

  // Pressing Enter in the search box selects the first result (convenience)
  const onSearchKeyDown = (e) => {
    if (e.key === 'Enter' && filtered.length > 0) {
      onChoose(filtered[0]);
    }
  };

  return (
    <>
      <div
        className={`modal ${show ? 'show' : ''}`}
        tabIndex="-1"
        style={{ display: show ? 'block' : 'none' }}
        aria-modal={show}
        role="dialog"
      >
        <div className="modal-dialog modal-lg modal-dialog-scrollable">
          <div className="modal-content">
            <div className="modal-header">
              <h5 className="modal-title">Add widget</h5>
              <button type="button" className="btn-close" onClick={onClose} aria-label="Close" />
            </div>

            <div className="modal-body">
              <div className="input-group mb-3">
                <span className="input-group-text"><i className="bi bi-search" /></span>
                <input
                  className="form-control"
                  placeholder="Search type or title…"
                  value={query}
                  onChange={(e) => setQuery(e.target.value)}
                  onKeyDown={onSearchKeyDown}
                />
              </div>

              <div className="row g-3">
                {filtered.map(def => (
                  <div className="col-12 col-md-6" key={def.type}>
                    <div className="card h-100 shadow-sm">
                      <div className="card-body d-flex flex-column">
                        <div className="d-flex align-items-center gap-2 mb-2">
                          <span className="fs-4">{typeIcon(def.type)}</span>
                          <div>
                            <div className="fw-semibold">{def.title || def.type}</div>
                            <div className="text-muted small">{def.type}</div>
                          </div>
                        </div>

                        {def.description && (
                          <div className="text-muted small mb-2">{def.description}</div>
                        )}

                        <div className="text-muted small mb-3">
                          {renderDefaultsLine(def)}
                        </div>

                        <div className="mt-auto d-flex justify-content-end">
                          <button className="btn btn-primary btn-sm" onClick={() => onChoose(def)}>
                            Add
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                ))}

                {!filtered.length && (
                  <div className="text-center text-muted py-5">No widgets match “{query}”.</div>
                )}
              </div>
            </div>

            <div className="modal-footer">
              <button className="btn btn-outline-secondary" onClick={onClose}>Close</button>
            </div>
          </div>
        </div>
      </div>
      {show && <div className="modal-backdrop fade show" />}
    </>
  );
}
