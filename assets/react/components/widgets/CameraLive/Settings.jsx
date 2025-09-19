import React from 'react';

export default function CameraLiveSettings({ value = {}, onChange }) {
  const update = (k, v) => onChange({ ...value, [k]: v });

  return (
    <div className="space-y-3">
      <label className="block">
        <span className="text-sm font-medium">HLS URL</span>
        <input className="input" type="text"
               value={value.src ?? '/hlsdisk/sms18/index.m3u8'}
               onChange={e => update('src', e.target.value)} />
      </label>
      <label className="block">
        <span className="text-sm font-medium">Poster URL (optional)</span>
        <input className="input" type="text"
               value={value.poster ?? ''}
               onChange={e => update('poster', e.target.value)} />
      </label>
      <div className="flex gap-4">
        <label className="inline-flex items-center gap-2">
          <input type="checkbox"
                 checked={value.muted ?? true}
                 onChange={e => update('muted', e.target.checked)} />
          <span>Muted</span>
        </label>
        <label className="inline-flex items-center gap-2">
          <input type="checkbox"
                 checked={value.autoPlay ?? true}
                 onChange={e => update('autoPlay', e.target.checked)} />
          <span>Autoplay</span>
        </label>
        <label className="inline-flex items-center gap-2">
          <input type="checkbox"
                 checked={value.controls ?? true}
                 onChange={e => update('controls', e.target.checked)} />
          <span>Controls</span>
        </label>
      </div>

      <label className="block">
        <span className="text-sm font-medium">Object Fit</span>
        <select className="input"
                value={value.objectFit ?? 'cover'}
                onChange={e => update('objectFit', e.target.value)}>
          <option value="cover">cover</option>
          <option value="contain">contain</option>
          <option value="fill">fill</option>
          <option value="scale-down">scale-down</option>
        </select>
      </label>
    </div>
  );
}
