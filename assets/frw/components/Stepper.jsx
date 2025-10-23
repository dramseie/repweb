import React from 'react';

export default function Stepper({ steps, cursor, onJump }) {
  return (
    <nav style={{display:'flex', flexDirection:'column', gap:'8px', width:'100%'}}>
      {steps.map((s, i) => (
        <button
          key={s.key || i}
          type="button"
          onClick={() => onJump(i)}
          style={{
            display:'block',
            width:'100%',
            height:40,
            textAlign:'left',
            padding:'0 12px',
            borderRadius:8,
            border:'1px solid ' + (i === cursor ? '#2563eb' : '#d1d5db'),
            background: i === cursor ? '#2563eb' : '#ffffff',
            color: i === cursor ? '#ffffff' : '#111827',
            fontWeight: i === cursor ? 600 : 400,
            cursor:'pointer'
          }}
        >
          {s.title}
        </button>
      ))}
    </nav>
  );
}
