// assets/react/qw/entry.tsx

import React from 'react';
import { createRoot } from 'react-dom/client';
import QuestionnaireBuilder from './QuestionnaireBuilder';

const el = document.getElementById('qw-root');
if (el) {
  const root = createRoot(el);
  const qid = Number(el.getAttribute('data-qid') || '0');
  root.render(<QuestionnaireBuilder qid={qid} />);
}
