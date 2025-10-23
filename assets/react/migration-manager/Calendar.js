import React, { useEffect, useState } from "react";

export default function CapacityCalendar() {
  const [windows, setWindows] = useState([]);
  const [err, setErr] = useState(null);

  useEffect(() => {
    fetch('/api/mig/windows')
      .then(r => r.json())
      .then(j => setWindows(j?.data?.items ?? []))
      .catch(e => setErr(String(e)));
  }, []);

  if (err) return <div className="text-red-600">Error: {err}</div>;

  return (
    <div className="mt-6">
      <h2 className="text-xl font-semibold mb-3">Capacity calendar</h2>
      <div className="grid gap-3">
        {windows.map(w => {
          const used = w.capacity_used ?? 0;
          const total = w.capacity_total ?? 0;
          const pct = total ? Math.round((used/total)*100) : 0;
          return (
            <div key={w.id} className="p-4 rounded-2xl shadow">
              <div className="flex justify-between items-center">
                <div>
                  <div className="text-sm text-gray-500">{w.kind}</div>
                  <div className="font-medium">
                    {new Date(w.starts_at).toLocaleString()} → {new Date(w.ends_at).toLocaleString()}
                  </div>
                </div>
                <div className="text-sm">{used}/{total} slots used</div>
              </div>
              <div className="w-full bg-gray-100 h-2 rounded mt-2">
                <div className="h-2 rounded" style={{ width: pct + '%' }} />
              </div>
              {w.resource_matrix && (
                <div className="text-xs text-gray-600 mt-2">
                  {Object.entries(w.resource_matrix).map(([k,v]) => <span key={k} className="mr-3">{k}:{v}</span>)}
                </div>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}
