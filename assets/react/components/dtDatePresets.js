// assets/react/components/dtDatePresets.js
// Utilities to compute ranges and build a SearchBuilder "details" object
export function computePresetRange(preset, now = new Date()) {
  const start = new Date(now);
  const end   = new Date(now);

  const startOfWeek = (d) => {
    // Monday as first day
    const day = d.getDay() || 7; // 1..7 (Mon..Sun)
    const r = new Date(d);
    r.setHours(0,0,0,0);
    r.setDate(r.getDate() - (day - 1));
    return r;
  };
  const endOfWeek = (d) => {
    const s = startOfWeek(d);
    const r = new Date(s);
    r.setDate(s.getDate() + 7);
    r.setMilliseconds(-1);
    return r;
  };

  switch (preset) {
    case 'last24h':
      start.setHours(start.getHours() - 24);
      return { start, end: now };
    case 'last7d':
      start.setDate(start.getDate() - 7);
      return { start, end: now };
    case 'last30d':
      start.setDate(start.getDate() - 30);
      return { start, end: now };
    case 'thisWeek':
      return { start: startOfWeek(now), end: endOfWeek(now) };
    case 'lastWeek': {
      const thisWStart = startOfWeek(now);
      const lastWStart = new Date(thisWStart);
      lastWStart.setDate(thisWStart.getDate() - 7);
      const lastWEnd = new Date(thisWStart);
      lastWEnd.setMilliseconds(-1);
      return { start: lastWStart, end: lastWEnd };
    }
    default:
      return null;
  }
}

/**
 * Build a minimal SearchBuilder "details" structure that says:
 *   dateColumn BETWEEN [start, end]
 * columnRef can be either column index (number) or column title (string) found in DataTables columns().
 */
export function buildSearchBuilderBetween(dateColumnRef, start, end) {
  const fmt = (d) => d.toISOString(); // SearchBuilder’s DateTime parser can handle ISO
  const col = typeof dateColumnRef === 'number'
    ? { dataIdx: dateColumnRef } // index reference
    : { data: dateColumnRef };   // title / data key (if you use objects)

  return {
    logic: 'AND',
    criteria: [{
      condition: 'between',
      origData: col,  // used by rebuild()
      type: 'date',
      value: [fmt(start), fmt(end)],
      value2: null
    }]
  };
}
