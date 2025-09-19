import React from 'react';

function toLocalInputValue(d) {
  // yyyy-MM-ddTHH:mm (local)
  try { return new Date(d).toISOString().slice(0, 16); } catch { return ''; }
}
function fromLocalInputValue(s) {
  // Treat value as local time
  return new Date(s);
}

export default function SimpleDateTimeRange({
  start,
  end,
  onChange,
  onApply,                // optional callback (e.g., "Load")
  presets = [
    { label: 'Last 12h', hours: 12 },
    { label: 'Last 24h', hours: 24 },
    { label: 'Last 48h', hours: 48 },
    { label: 'Last 7d',  hours: 24 * 7 },
  ],
  className = '',
  disabled = false,
}) {
  const setStart = (d) => onChange?.({ start: d, end });
  const setEnd   = (d) => onChange?.({ start, end: d });

  return (
    <div className={`d-flex flex-wrap align-items-end gap-2 ${className}`}>
      <div className="d-flex flex-column">
        <label className="small fw-medium">Oldest</label>
        <div className="input-group">
          <input
            type="datetime-local"
            className="form-control"
            value={toLocalInputValue(start)}
            onChange={(e) => setStart(fromLocalInputValue(e.target.value))}
            disabled={disabled}
          />
          <span className="input-group-text"><i className="bi bi-calendar-event" /></span>
        </div>
      </div>

      <div className="d-flex flex-column">
        <label className="small fw-medium">Latest</label>
        <div className="input-group">
          <input
            type="datetime-local"
            className="form-control"
            value={toLocalInputValue(end)}
            onChange={(e) => setEnd(fromLocalInputValue(e.target.value))}
            disabled={disabled}
          />
          <span className="input-group-text"><i className="bi bi-calendar-event" /></span>
        </div>
      </div>

      {onApply && (
        <button className="btn btn-outline-secondary" onClick={onApply} disabled={disabled}>
          Load
        </button>
      )}

      <div className="d-flex gap-1">
        {presets.map((p) => (
          <button
            key={p.label}
            type="button"
            className="btn btn-sm btn-outline-secondary"
            onClick={() => {
              const endD = new Date();
              const startD = new Date(endD);
              startD.setHours(endD.getHours() - p.hours);
              onChange?.({ start: startD, end: endD });
              onApply?.();
            }}
            disabled={disabled}
            title={`Set ${p.label}`}
          >
            {p.label}
          </button>
        ))}
      </div>
    </div>
  );
}
