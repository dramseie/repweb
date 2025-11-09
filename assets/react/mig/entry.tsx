import React from 'react';
import { createRoot } from 'react-dom/client';
import PlanningApp from './PlanningApp';
import MailApp from './MailApp';

const el = document.getElementById('migration-manager-root');
if (el) {
  const root = createRoot(el);
  const view = (el.getAttribute('data-view') || 'planning').toLowerCase();
  root.render(view === 'mail' ? <MailApp /> : <PlanningApp />);
}
