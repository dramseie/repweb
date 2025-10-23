import React from 'react';
import { RAG_LABEL, WEATHER_ICON, WEATHER_LABEL } from './constants';

export function RagBadge({v}:{v:0|1|2|3}) {
  const cls = ['bg-gray-400','bg-green-600','bg-amber-500','bg-red-600'][v] + ' text-white px-2 py-0.5 rounded';
  return <span className={cls}>{RAG_LABEL[v]}</span>;
}
export function WeatherGlyph({v}:{v:1|2|3|4|5}) {
  return <span title={WEATHER_LABEL[v]}>{WEATHER_ICON[v]}</span>;
}
export function ProgressBar({value}:{value:number}) {
  const v = Math.max(0, Math.min(100, Math.round(value)));
  return (
    <div className="w-full h-2 bg-gray-200 rounded">
      <div className="h-2 rounded bg-blue-600" style={{width:`${v}%`}} />
    </div>
  );
}
