import React from 'react';
import { createRoot } from 'react-dom/client';
import ProjectList from './ProjectList';
import ProjectDetail from './ProjectDetail';
import VersionCompare from './VersionCompare';

function bootstrap() {
  const el = document.getElementById('psr-root');
  if (!el) return;
  const page = el.getAttribute('data-page');
  const mode = (el.getAttribute('data-mode') || '').toLowerCase(); // '' | 'present'
  const root = createRoot(el);
  if (page === 'list') root.render(<ProjectList />);
  else if (page?.startsWith('detail:')) {
    const id = page.replace('detail:','');
    root.render(<ProjectDetail projectId={id} readOnly={mode==='present'} />);
  } else if (page === 'compare') root.render(<VersionCompare />);
}
bootstrap();
