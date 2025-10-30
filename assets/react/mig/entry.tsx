import React from 'react';
import { createRoot } from 'react-dom/client';
import PlanningApp from './PlanningApp';

const el = document.getElementById('migration-manager-root');
if (el) {
  const root = createRoot(el);
  root.render(<PlanningApp />);
}
