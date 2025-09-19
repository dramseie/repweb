// assets/react/components/widgets/CaraLuckyWidget.jsx
import React, { useEffect, useState } from 'react';

const GAMES = [
  { id: 'euromillions', label: 'EuroMillions' },
  { id: 'swisslotto',   label: 'Swiss Lotto' },
  { id: 'powerball',    label: 'Powerball' },
  { id: 'eurojackpot',  label: 'EuroJackpot' },
  { id: 'custom',       label: 'Custom' },
];

export default function CaraLuckyWidget({ config = {} }) {
  const [game, setGame] = useState(config.game || 'euromillions');
  const [seed, setSeed] = useState('');
  const [saving, setSaving] = useState(false);
  const [draw, setDraw] = useState(null);
  const [hist, setHist] = useState([]);

  // Custom game params
  const [pool, setPool] = useState(60);
  const [picks, setPicks] = useState(6);
  const [bonusPool, setBonusPool] = useState(0);
  const [bonusPicks, setBonusPicks] = useState(0);

  const apiBase = '/api/lucky';

  const doDraw = async () => {
    const params = new URLSearchParams();
    if (seed) params.set('seed', seed);
    if (saving) params.set('save', '1');
    if (game === 'custom') {
      if (pool) params.set('pool', String(pool));
      if (picks) params.set('picks', String(picks));
      if (bonusPool) params.set('bonus_pool', String(bonusPool));
      if (bonusPicks) params.set('bonus_picks', String(bonusPicks));
    }
    const r = await fetch(`${apiBase}/${game}/draw?${params.toString()}`);
    const j = await r.json();
    setDraw(j);
    loadHistory();
  };

  const loadHistory = async () => {
    const r = await fetch(`${apiBase}/history?limit=10${game ? `&game=${game}` : ''}`);
    const j = await r.json();
    setHist(j.items || []);
  };

  useEffect(() => { loadHistory(); }, [game]);

  return (
    <div className="p-4 rounded-2xl shadow-md bg-white/70 border border-gray-100">
      <div className="flex items-center justify-between mb-3">
        <div className="flex items-center gap-3">
          <span className="text-2xl">🐾</span>
          <h3 className="text-xl font-semibold">Caraméow Lucky Numbers</h3>
        </div>
        <select
          className="border rounded px-2 py-1"
          value={game}
          onChange={(e) => setGame(e.target.value)}
        >
          {GAMES.map((g) => (
            <option key={g.id} value={g.id}>{g.label}</option>
          ))}
        </select>
      </div>

      {game === 'custom' && (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-2 mb-3">
          <label className="text-sm flex flex-col gap-1">
            Pool
            <input
              type="number"
              className="w-full border rounded px-2 py-1"
              value={pool}
              onChange={(e) => setPool(parseInt(e.target.value || '0', 10))}
            />
          </label>
          <label className="text-sm flex flex-col gap-1">
            Picks
            <input
              type="number"
              className="w-full border rounded px-2 py-1"
              value={picks}
              onChange={(e) => setPicks(parseInt(e.target.value || '0', 10))}
            />
          </label>
          <label className="text-sm flex flex-col gap-1">
            Bonus pool
            <input
              type="number"
              className="w-full border rounded px-2 py-1"
              value={bonusPool}
              onChange={(e) => setBonusPool(parseInt(e.target.value || '0', 10))}
            />
          </label>
          <label className="text-sm flex flex-col gap-1">
            Bonus picks
            <input
              type="number"
              className="w-full border rounded px-2 py-1"
              value={bonusPicks}
              onChange={(e) => setBonusPicks(parseInt(e.target.value || '0', 10))}
            />
          </label>
        </div>
      )}

      <div className="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3">
        <label className="text-sm flex flex-col gap-1">
          Seed (optional)
          <input
            className="w-full border rounded px-2 py-1"
            placeholder="carameow-42"
            value={seed}
            onChange={(e) => setSeed(e.target.value)}
          />
        </label>

        <label className="text-sm flex items-center gap-2">
          <input
            type="checkbox"
            checked={saving}
            onChange={(e) => setSaving(e.target.checked)}
          />
          Save to history
        </label>

        <button
          onClick={doDraw}
          className="border rounded-lg px-3 py-2 shadow hover:shadow-lg transition"
        >
          🎲 Draw now
        </button>
      </div>

      {draw && (
        <div className="mb-4">
          <div className="text-sm text-gray-600">
            {draw.label} {draw.seed ? `(seed: ${draw.seed})` : ''}
          </div>
          <div className="flex items-center gap-2 mt-2 flex-wrap">
            {draw.numbers?.map((n) => (
              <span key={n} className="px-3 py-2 rounded-full border shadow-sm font-semibold">
                {n}
              </span>
            ))}
            {draw.bonus && draw.bonus.length > 0 && (
              <>
                <span className="ml-2 text-sm text-gray-500">+ Bonus</span>
                {draw.bonus.map((b) => (
                  <span key={`b${b}`} className="px-3 py-2 rounded-full border shadow-sm font-semibold">
                    ★ {b}
                  </span>
                ))}
              </>
            )}
          </div>
        </div>
      )}

      <div>
        <div className="font-medium mb-2">Recent draws</div>
        <ul className="space-y-1 max-h-48 overflow-auto pr-1">
          {hist.map((h) => (
            <li key={h.id} className="text-sm text-gray-700 flex items-center gap-2">
              <span className="text-gray-500">
                {new Date(h.created_at.replace(' ', 'T')).toLocaleString()}
              </span>
              <span className="ml-2">{h.game}</span>
              <span className="ml-2">
                [{(h.numbers || []).join(', ')}
                {h.bonus && h.bonus.length ? ` | ★ ${h.bonus.join(', ')}` : ''}]
              </span>
              {h.seed && (
                <span className="ml-2 text-gray-400">seed: {h.seed}</span>
              )}
            </li>
          ))}
          {hist.length === 0 && (
            <li className="text-sm text-gray-400">No history yet.</li>
          )}
        </ul>
      </div>
    </div>
  );
}
