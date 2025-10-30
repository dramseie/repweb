import React from 'react';
import { createRoot } from 'react-dom/client';
import QuestionnaireRunner from './QuestionnaireRunner';

const el = document.getElementById('qw-runner-root');

if (el) {
  const ciKey = el.getAttribute('data-ci') || '';
  const root = createRoot(el);
  root.render(<QuestionnaireRunner ciKey={ciKey} />);
}
