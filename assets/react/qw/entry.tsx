// assets/react/qw/entry.tsx

import React from 'react';
import { createRoot } from 'react-dom/client';
import QuestionnaireBuilder from './QuestionnaireBuilder';
import QuestionnaireEditor from './QuestionnaireEditor';

const el = document.getElementById('qw-root');
if (el) {
  const root = createRoot(el);
  const page = el.getAttribute('data-page') || 'builder';

  if (page === 'editor') {
    const tenantId = Number(el.getAttribute('data-tenant') || '1');
    root.render(<QuestionnaireEditor tenantId={Number.isNaN(tenantId) ? 1 : tenantId} />);
  } else {
    const qid = Number(el.getAttribute('data-qid') || '0');
    root.render(<QuestionnaireBuilder qid={qid} />);
  }
}
