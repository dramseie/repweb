import React, { useEffect, useState } from 'react';

export default function ProgressHUD() {
  const [md, setMd] = useState('# No summary yet');

  useEffect(() => {
    fetch('/progress-summary.md', { cache: 'no-store' })
      .then(r => r.ok ? r.text() : '# No summary yet')
      .then(setMd)
      .catch(() => setMd('# No summary yet'));
  }, []);

  return (
    <div className="card shadow-sm">
      <div className="card-body">
        <h5 className="card-title">Project Summary</h5>
        <pre style={{ whiteSpace: 'pre-wrap', fontFamily: 'ui-monospace, SFMono-Regular, Menlo, monospace' }}>
          {md}
        </pre>
      </div>
    </div>
  );
}
